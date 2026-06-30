<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ForecastController extends Controller
{
  /**
   * Demand forecast: for every repeat client (>2 non-cancelled orders) we
   * learn their ordering cadence from the gaps between order-placement dates
   * (created_at), then project probable future orders onto the requested
   * month so staff can see, per day, who is likely to order and roughly what.
   */
  /**
   * Create an order from a forecast prediction.
   */
  public function createOrder(Request $request): \Illuminate\Http\RedirectResponse
  {
    $data = $request->validate([
      'client_id'          => ['required', 'exists:users,id'],
      'items'              => ['required', 'array', 'min:1'],
      'items.*.product_id' => ['required', 'exists:products,id'],
      'items.*.quantity'   => ['required', 'integer', 'min:1'],
    ]);

    $order = DB::transaction(function () use ($data) {
      $items = collect($data['items'])->map(function ($item) {
        $product   = Product::findOrFail($item['product_id']);
        $unitPrice = (float) ($product->sale_price > 0 ? $product->sale_price : $product->price);
        return [
          'product_id' => $item['product_id'],
          'quantity'   => $item['quantity'],
          'unit_price' => $unitPrice,
          'subtotal'   => $unitPrice * $item['quantity'],
          'is_gift'    => false,
        ];
      });

      $order = Order::create([
        'user_id'        => $data['client_id'],
        'status'         => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
        'total_amount'   => $items->sum('subtotal'),
        'created_by'     => auth()->id(),
      ]);

      $order->items()->createMany($items->toArray());

      return $order;
    });

    return redirect()->route('admin.orders.show', $order);
  }

  public function index(): Response
  {
    $month = $this->resolveMonth(request('month'));
    $monthStart = $month->copy()->startOfMonth();
    $monthEnd   = $month->copy()->endOfMonth();
    $today      = today();

    // Clients with more than two non-cancelled orders — enough history to
    // have at least two intervals to learn a cadence from.
    $clientIds = Order::query()
      ->where('status', '!=', OrderStatus::Cancelled)
      ->selectRaw('user_id, COUNT(*) as c')
      ->groupBy('user_id')
      ->havingRaw('COUNT(*) > 2')
      ->pluck('user_id');

    $clients = User::query()
      ->whereIn('id', $clientIds)
      ->with(['orders' => fn ($q) => $q
        ->where('status', '!=', OrderStatus::Cancelled)
        ->orderBy('created_at')
        ->with(['items:id,order_id,product_id,quantity,is_gift', 'items.product:id,name,price,sale_price'])])
      ->get(['id', 'name']);

    $predictions = [];

    foreach ($clients as $client) {
      $orders = $client->orders;
      if ($orders->count() < 3) {
        continue;
      }

      $intervals = $this->intervals($orders);
      if (empty($intervals)) {
        continue;
      }

      $cadence = max(1, (int) round($this->median($intervals)));
      $lastOrder = $orders->last()->created_at->copy()->startOfDay();

      // First probable next order; roll forward in whole cadence steps until
      // it lands today or later. A client whose date had to roll forward is
      // "overdue" — past due on their usual cycle and worth chasing.
      $next = $lastOrder->copy()->addDays($cadence);
      $overdue = false;
      while ($next->lt($today)) {
        $next->addDays($cadence);
        $overdue = true;
      }

      // Project the recurring cadence across the requested month so any
      // selected day in that month surfaces the clients due on it.
      $occurrence = $next->copy();
      while ($occurrence->lt($monthStart)) {
        $occurrence->addDays($cadence);
      }

      $basket = $this->typicalBasket($orders);
      $confidence = $this->confidence($intervals, $cadence, $orders->count());
      $trend = $this->trend($intervals);

      // Churn detection: if overdue by more than 2x the cadence, mark as churned
      $daysOverdue = $today->diffInDays($next->copy()->subDays($cadence));
      $churned = $daysOverdue > $cadence * 2;

      $expectedValue = $this->expectedValue($basket);

      $guard = 0;
      while ($occurrence->lte($monthEnd) && $guard++ < 60) {
        if ($occurrence->gte($today)) {
          $predictions[] = [
            'client_id'    => $client->id,
            'client_name'  => $client->name,
            'date'         => $occurrence->toDateString(),
            'overdue'      => $overdue,
            'churned'      => $churned,
            'confidence'   => $confidence,
            'trend'        => $trend,
            'last_order'   => $lastOrder->toDateString(),
            'cadence_days' => $cadence,
            'order_count'  => $orders->count(),
            'basket'       => $basket,
            'expected_value' => $expectedValue,
          ];
        }
        $occurrence->addDays($cadence);
      }
    }

    $summary = [
      'total_clients'   => count(array_unique(array_column($predictions, 'client_id'))),
      'total_value'     => array_sum(array_column($predictions, 'expected_value')),
      'overdue_count'   => count(array_filter($predictions, fn($p) => $p['overdue'])),
      'churned_count'   => count(array_filter($predictions, fn($p) => $p['churned'] ?? false)),
    ];

    return Inertia::render('forecasts/Index')->with([
      'month'       => $month->format('Y-m'),
      'predictions' => $predictions,
      'summary'     => $summary,
    ]);
  }

  /**
   * Parse the ?month=YYYY-MM param into a Carbon date, falling back to the
   * current month when it's missing or malformed.
   */
  private function resolveMonth($value): Carbon
  {
    if (is_string($value) && preg_match('/^\d{4}-\d{2}$/', $value)) {
      try {
        return Carbon::createFromFormat('Y-m-d', $value . '-01')->startOfMonth();
      } catch (\Throwable) {
        // fall through to default
      }
    }

    return now()->startOfMonth();
  }

  /**
   * Whole-day gaps between consecutive order-placement dates.
   *
   * @return int[]
   */
  private function intervals(Collection $orders): array
  {
    $intervals = [];
    $prev = null;
    foreach ($orders as $order) {
      $current = $order->created_at->copy()->startOfDay();
      if ($prev !== null) {
        $gap = (int) $prev->diffInDays($current);
        if ($gap > 0) {
          $intervals[] = $gap;
        }
      }
      $prev = $current;
    }

    return $intervals;
  }

  /**
   * The client's typical basket: across their last up-to-5 orders, keep each
   * (non-gift) product that appears in at least half of them, with the median
   * ordered quantity. Falls back to the most recent order's items.
   */
  private function typicalBasket(Collection $orders): array
  {
    $recent = $orders->sortByDesc('created_at')->take(5)->values();

    $byProduct = [];
    foreach ($recent as $order) {
      foreach ($order->items as $item) {
        if ($item->is_gift || ! $item->product) {
          continue;
        }
        $byProduct[$item->product_id]['product_id'] ??= $item->product_id;
        $byProduct[$item->product_id]['name'] ??= $item->product->name;
        $byProduct[$item->product_id]['price'] ??= (float) ($item->product->sale_price > 0 ? $item->product->sale_price : $item->product->price);
        $byProduct[$item->product_id]['qtys'][] = (int) $item->quantity;
      }
    }

    $threshold = (int) ceil($recent->count() / 2);
    $basket = [];
    foreach ($byProduct as $data) {
      if (count($data['qtys']) >= $threshold) {
        $basket[] = [
          'product_id' => $data['product_id'],
          'name'       => $data['name'],
          'qty'        => max(1, (int) round($this->median($data['qtys']))),
          'unit_price' => $data['price'],
        ];
      }
    }

    if (empty($basket)) {
      foreach ($recent->first()->items as $item) {
        if ($item->is_gift || ! $item->product) {
          continue;
        }
        $price = (float) ($item->product->sale_price > 0 ? $item->product->sale_price : $item->product->price);
        $basket[] = [
          'product_id' => $item->product_id,
          'name'       => $item->product->name,
          'qty'        => (int) $item->quantity,
          'unit_price' => $price,
        ];
      }
    }

    return $basket;
  }

  /**
   * Confidence in the cadence from how regular the intervals are
   * (coefficient of variation against the cadence).
   */
  private function confidence(array $intervals, int $cadence, int $orderCount): string
  {
    $count = count($intervals);
    $mean = array_sum($intervals) / $count;
    $variance = 0.0;
    foreach ($intervals as $value) {
      $variance += ($value - $mean) ** 2;
    }
    $std = sqrt($variance / $count);
    $cv = $cadence > 0 ? $std / $cadence : 1.0;

    if ($orderCount >= 5 && $cv < 0.35) {
      return 'high';
    }
    if ($cv < 0.6) {
      return 'medium';
    }

    return 'low';
  }

  /**
   * Detect trend in ordering frequency. Compare first-half and second-half
   * mean intervals. If insufficient history (< 4 intervals), return 'stable'.
   */
  private function trend(array $intervals): string
  {
    if (count($intervals) < 4) {
      return 'stable';
    }

    $mid = (int) ceil(count($intervals) / 2);
    $firstHalf  = array_slice($intervals, 0, $mid);
    $secondHalf = array_slice($intervals, $mid);

    $firstMean  = array_sum($firstHalf) / count($firstHalf);
    $secondMean = array_sum($secondHalf) / count($secondHalf);

    if ($firstMean > 0) {
      $ratio = $secondMean / $firstMean;
      if ($ratio < 0.85) {
        return 'up'; // ordering more frequently
      }
      if ($ratio > 1.15) {
        return 'down'; // ordering less frequently
      }
    }

    return 'stable';
  }

  /**
   * Median of a numeric array. Returns 0 for an empty array.
   */
  private function median(array $values): float
  {
    sort($values);
    $n = count($values);
    if ($n === 0) {
      return 0.0;
    }
    $mid = intdiv($n, 2);

    return $n % 2 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
  }

  /**
   * Sum of basket item quantities × unit prices.
   */
  private function expectedValue(array $basket): float
  {
    $total = 0.0;
    foreach ($basket as $item) {
      $total += $item['qty'] * (float) $item['unit_price'];
    }
    return round($total, 2);
  }
}
