<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        ->with(['items:id,order_id,product_id,quantity,is_gift', 'items.product:id,name'])])
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

      $guard = 0;
      while ($occurrence->lte($monthEnd) && $guard++ < 60) {
        if ($occurrence->gte($today)) {
          $predictions[] = [
            'client_id'    => $client->id,
            'client_name'  => $client->name,
            'date'         => $occurrence->toDateString(),
            'overdue'      => $overdue,
            'confidence'   => $confidence,
            'last_order'   => $lastOrder->toDateString(),
            'cadence_days' => $cadence,
            'order_count'  => $orders->count(),
            'basket'       => $basket,
          ];
        }
        $occurrence->addDays($cadence);
      }
    }

    return Inertia::render('forecasts/Index')->with([
      'month'       => $month->format('Y-m'),
      'predictions' => $predictions,
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
        $byProduct[$item->product_id]['name'] ??= $item->product->name;
        $byProduct[$item->product_id]['qtys'][] = (int) $item->quantity;
      }
    }

    $threshold = (int) ceil($recent->count() / 2);
    $basket = [];
    foreach ($byProduct as $data) {
      if (count($data['qtys']) >= $threshold) {
        $basket[] = [
          'name' => $data['name'],
          'qty'  => max(1, (int) round($this->median($data['qtys']))),
        ];
      }
    }

    if (empty($basket)) {
      foreach ($recent->first()->items as $item) {
        if ($item->is_gift || ! $item->product) {
          continue;
        }
        $basket[] = ['name' => $item->product->name, 'qty' => (int) $item->quantity];
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
}
