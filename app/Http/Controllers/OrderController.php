<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Order;
use App\Services\OrderAccountingService;
use App\Services\WalletService;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
  /**
   * Given the calculated item total and an optional admin-supplied custom
   * total, return [final_total, discount]. If custom is unset or equal to
   * the calculated total, discount is 0. If custom is lower, the gap is the
   * discount. If custom is higher, treat it as a surcharge (negative
   * discount) so the math still balances.
   */
  private function resolveTotals(float $calculatedTotal, $customTotal): array
  {
    if ($customTotal === null || $customTotal === '') {
      return [round($calculatedTotal, 2), 0.0];
    }
    $custom = round((float) $customTotal, 2);
    $discount = round($calculatedTotal - $custom, 2);
    return [$custom, $discount];
  }

  /**
   * Use the admin-entered unit price when present; otherwise fall back to
   * the product's current catalog price (sale_price if set, else price).
   */
  private function resolveItemUnitPrice(array $item, Product $product): float
  {
    if (array_key_exists('unit_price', $item) && $item['unit_price'] !== null && $item['unit_price'] !== '') {
      return round((float) $item['unit_price'], 2);
    }

    return (float) ($product->sale_price > 0 ? $product->sale_price : $product->price);
  }

  /**
   * Apply inventory changes for a set of order items. Decrements when
   * $direction is -1 (new order / new items on edit), restores when +1
   * (cancellation / replaced items on edit).
   *
   * Two layers move together so totals stay consistent:
   *   • products.quantity — the finished-goods count on the product itself
   *     (only adjusted when manage_stock = true, so non-stocked items like
   *     digital products or one-off services aren't affected).
   *   • raw_materials.current_stock — the BOM consumables that the product
   *     uses up per unit (caps, labels, water litres, etc.).
   *
   * Items can be either OrderItem models or raw arrays carrying
   * product_id + quantity. Gifts still consume inventory — a free bottle
   * is still a real bottle.
   */
  private function adjustRawMaterialStock(iterable $items, int $direction): void
  {
    $productIds = collect($items)
      ->map(fn($i) => is_array($i) ? $i['product_id'] : $i->product_id)
      ->unique()
      ->values();

    if ($productIds->isEmpty()) {
      return;
    }

    $products = Product::with('rawMaterials')
      ->whereIn('id', $productIds)
      ->get()
      ->keyBy('id');

    foreach ($items as $item) {
      $productId = is_array($item) ? $item['product_id'] : $item->product_id;
      $quantity  = (int) (is_array($item) ? $item['quantity'] : $item->quantity);
      $product   = $products[$productId] ?? null;
      if (!$product || $quantity <= 0) continue;

      // 1) Finished-goods stock on the product row.
      if ($product->manage_stock) {
        if ($direction < 0) {
          Product::where('id', $product->id)->decrement('quantity', $quantity);
        } else {
          Product::where('id', $product->id)->increment('quantity', $quantity);
        }
      }

      // 2) Raw-material consumables per the product's BOM.
      foreach ($product->rawMaterials as $material) {
        $perUnit = (float) ($material->pivot->quantity ?? 0);
        if ($perUnit <= 0) continue;

        $delta = $perUnit * $quantity;
        if ($direction < 0) {
          RawMaterial::where('id', $material->id)->decrement('current_stock', $delta);
        } else {
          RawMaterial::where('id', $material->id)->increment('current_stock', $delta);
        }
      }
    }
  }

  public function index()
  {
    $pagination = request()->has('pagination')
      ? request()->input('pagination')
      : ['limit' => 50, 'page' => 1];

    // Client-facing /orders route MUST be scoped to the signed-in user.
    // Only the admin /admin/orders path may list across users.
    $isAdminPath = request()->is('admin/*');
    $authUserId = auth()->id();

    // Clients now have a single-page home (/profile) that shows their orders
    // inline. Redirect them there if they hit /orders directly.
    if (!$isAdminPath && auth()->user()?->hasRole('Client')
        && !auth()->user()?->hasAnyRole(['Admin', 'Manager', 'Operator', 'Currier'])) {
      return redirect()->route('dashboard');
    }

    $orders = Order::with(['client.phones', 'creator'])
      ->when(
        !$isAdminPath && $authUserId,
        fn($q) => $q->where('user_id', $authUserId)
      )
      ->when(
        request('status'),
        fn($q, $status) =>
        $q->where('status', $status)
      )
      ->when(
        $isAdminPath && request('user_id'),
        fn($q, $userId) =>
        $q->where('user_id', $userId)
      )
      ->when(
        request('from'),
        fn($q, $from) =>
        $q->whereDate('created_at', '>=', $from)
      )
      ->when(
        request('to'),
        fn($q, $to) =>
        $q->whereDate('created_at', '<=', $to)
      )
      ->latest()
      ->paginate($pagination['limit'], ['*'], 'page', $pagination['page']);

    return Inertia::render('orders/Index')->with([
      'orders'   => $orders,
      'statuses' => OrderStatus::getValues(),
    ]);
  }

  public function assignments(): Response
  {
    $couriers = User::role('Currier')->withCount(['orders' => function ($q) {
      $q->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled]);
    }])->get();

    $orders = Order::whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled])
      ->with(['client', 'courier', 'items.product'])
      ->latest()
      ->get();

    $statuses = OrderStatus::asArray();
    // Exclude delivered/cancelled from the list of selectable filters
    unset($statuses['Delivered'], $statuses['Cancelled']);

    return Inertia::render('orders/Assignments', [
      'couriers' => $couriers,
      'orders'   => $orders,
      'statuses' => $statuses,
    ]);
  }

  public function create(): Response
  {
    return Inertia::render('orders/Create')->with([
      'clients'  => User::role('Client')->with(['userProfile', 'addresses'])->get(['id', 'name', 'email']),
      'products' => Product::select(['id', 'name', 'price', 'sale_price', 'quantity', 'status'])->get(),
    ]);
  }

  /**
   * Client-facing "order water" form. Pre-fills the signed-in user's
   * addresses so they can pick one; no client picker, no price overrides,
   * no gifts — those are admin-only concerns.
   */
  public function clientCreate(): Response
  {
    $user = auth()->user();
    $user->load('addresses');

    return Inertia::render('orders/ClientCreate')->with([
      'addresses' => $user->addresses,
      'products'  => Product::where('status', 'active')
        ->select(['id', 'name', 'price', 'sale_price', 'quantity', 'status'])
        ->get(),
    ]);
  }

  /**
   * Store a client-placed order. Validates the simpler client payload
   * (no custom_total, no gifts, no walk-in contact), forces the order
   * onto the signed-in user, and lets the existing inventory deduction
   * pipeline run.
   */
  public function clientStore(\Illuminate\Http\Request $request)
  {
    $data = $request->validate([
      'scheduled_delivery_at' => ['nullable', 'date', 'after_or_equal:today'],
      'delivery_address'      => ['nullable', 'string'],
      'new_address'           => ['nullable', 'string'],
      'new_address_label'     => ['nullable', 'string', 'max:50'],
      'notes'                 => ['nullable', 'string'],
      'items'                 => ['required', 'array', 'min:1'],
      'items.*.product_id'    => ['required', 'exists:products,id'],
      'items.*.quantity'      => ['required', 'integer', 'min:1'],
    ]);

    $userId = auth()->id();

    $order = DB::transaction(function () use ($data, $userId) {
      $items = collect($data['items'])->map(function ($item) {
        $product   = Product::findOrFail($item['product_id']);
        $unitPrice = $product->sale_price > 0 ? $product->sale_price : $product->price;
        $subtotal  = $unitPrice * $item['quantity'];

        return [
          'product_id' => $item['product_id'],
          'quantity'   => $item['quantity'],
          'unit_price' => $unitPrice,
          'subtotal'   => $subtotal,
          'is_gift'    => false,
        ];
      });

      $total = (float) $items->sum('subtotal');

      $deliveryAddress = $data['delivery_address'] ?? null;
      if (!empty($data['new_address'])) {
        $address = UserAddress::create([
          'user_id'      => $userId,
          'label'        => $data['new_address_label'] ?? 'New Address',
          'address_line' => $data['new_address'],
        ]);
        $deliveryAddress = $address->address_line;
      }

      $order = Order::create([
        'user_id'               => $userId,
        'scheduled_delivery_at' => $data['scheduled_delivery_at'] ?? null,
        'delivery_address'      => $deliveryAddress,
        'notes'                 => $data['notes'] ?? null,
        'total_amount'          => $total,
        'discount_amount'       => 0,
        'payment_status'        => $total <= 0 ? PaymentStatus::Paid : PaymentStatus::Unpaid,
        'created_by'            => $userId,
      ]);

      $order->items()->createMany($items->toArray());
      $this->adjustRawMaterialStock($items, -1);

      return $order;
    });

    return redirect()->route('dashboard')
      ->with('success', "Order #{$order->order_number} placed. We'll be in touch shortly.");
  }

  /**
   * Resolve which user the order should belong to. If the request includes a
   * `new_contact` block (admin entered a walk-in client), find or create a
   * shell user (Client role, claimed_at = null) so a future self-registration
   * with the same phone/email can adopt the row.
   */
  private function resolveOrderUserId(StoreOrderRequest $request): int
  {
    if ($request->user_id) {
      return (int) $request->user_id;
    }

    $contact = $request->input('new_contact');
    $phone = $contact['phone'] ?? null;
    $email = $contact['email'] ?? null;
    $name  = $contact['name']  ?? null;

    if (!$phone && !$email) {
      // StoreOrderRequest should have caught this, but guard anyway.
      abort(422, 'A client or new contact (with phone) is required.');
    }

    // Try to match an existing user by phone first, then email.
    $user = null;
    if ($phone) {
      $existingPhone = UserPhone::where('phone', $phone)->first();
      if ($existingPhone) {
        $user = User::find($existingPhone->user_id);
      }
    }
    if (!$user && $email) {
      $user = User::where('email', $email)->first();
    }

    if (!$user) {
      $user = User::create([
        'name'     => $name ?: 'Walk-in client',
        'email'    => $email,
        'password' => Hash::make(Str::random(32)),
        'status'   => 'active',
        // claimed_at stays null → shell user, adoptable on later registration.
      ]);
      $user->assignRole('Client');

      if ($phone) {
        UserPhone::create([
          'user_id'    => $user->id,
          'phone'      => $phone,
          'label'      => 'Primary',
          'is_default' => true,
        ]);
      }
    }

    return $user->id;
  }

  public function store(StoreOrderRequest $request)
  {
    $userId = $this->resolveOrderUserId($request);
    $request->merge(['user_id' => $userId]);

    $order = DB::transaction(function () use ($request) {
      $items = collect($request->items)->map(function ($item) {
        $product   = Product::findOrFail($item['product_id']);
        $unitPrice = $this->resolveItemUnitPrice($item, $product);
        $isGift    = (bool) ($item['is_gift'] ?? false);
        $subtotal  = $isGift ? 0 : $unitPrice * $item['quantity'];

        return [
          'product_id' => $item['product_id'],
          'quantity'   => $item['quantity'],
          'unit_price' => $unitPrice,
          'subtotal'   => $subtotal,
          'is_gift'    => $isGift,
        ];
      });

      $calculatedTotal = (float) $items->sum('subtotal');
      [$finalTotal, $discount] = $this->resolveTotals($calculatedTotal, $request->input('custom_total'));

      $deliveryAddress = $request->delivery_address;

      if ($request->filled('new_address')) {
        $address = UserAddress::create([
          'user_id'      => $request->user_id,
          'label'        => $request->new_address_label ?? 'New Address',
          'address_line' => $request->new_address,
        ]);
        $deliveryAddress = $address->address_line;
      }

      // When the admin typed a walk-in contact, keep the typed name/phone
      // on the order itself. The order may still be linked to an existing
      // user (matched by phone) — that's fine; we just don't want the
      // existing user's name to replace what the admin typed.
      $contact     = $request->input('new_contact');
      $contactName = $contact['name']  ?? null;
      $contactPhone = $contact['phone'] ?? null;

      $order = Order::create([
        'user_id'               => $request->user_id,
        'contact_name'          => $contactName,
        'contact_phone'         => $contactPhone,
        'scheduled_delivery_at' => $request->scheduled_delivery_at,
        'delivery_address'      => $deliveryAddress,
        'notes'                 => $request->notes,
        'total_amount'          => $finalTotal,
        'discount_amount'       => $discount,
        'payment_status'        => $finalTotal <= 0 ? PaymentStatus::Paid : PaymentStatus::Unpaid,
        'created_by'            => auth()->id(),
      ]);

      $order->items()->createMany($items->toArray());

      // Deduct raw materials from inventory for the BOM of each line item.
      $this->adjustRawMaterialStock($items, -1);

      return $order;
    });

    return redirect()->route('admin.orders.show', $order)
      ->with('success', 'Order created successfully.');
  }

  public function show(Order $order)
  {
    // Non-admin path → only the order owner may view it. Staff/admin land
    // here via /admin/orders/{order} and bypass the check.
    $isAdminPath = request()->is('admin/*');
    if (!$isAdminPath && $order->user_id !== auth()->id()) {
      abort(404);
    }

    // Clients use the single-page home which opens a read-only modal for
    // order details. Send them there so they don't see the sidebar-wrapped
    // admin order page.
    if (!$isAdminPath && auth()->user()?->hasRole('Client')
        && !auth()->user()?->hasAnyRole(['Admin', 'Manager', 'Operator', 'Currier'])) {
      return redirect()->route('dashboard');
    }

    $order->load([
      'client.userProfile',
      'creator',
      'canceller',
      'items.product.rawMaterials',
      'courier',
      'returnedMaterials',
      'parentOrder:id,order_number,status',
      'backorders:id,parent_order_id,order_number,status,total_amount,scheduled_delivery_at',
    ]);

    return Inertia::render('orders/Show')->with([
      'order'    => $order,
      'statuses' => OrderStatus::getValues(),
      'reusable_materials' => \App\Models\RawMaterial::where('is_reusable', true)->get(),
      // Expected vs. returned reusable containers, plus the deposit owed for
      // any shortfall. The admin sees this on delivery to know what to bill.
      'reusable_summary' => array_values($order->reusableDepositSummary()),
      'couriers' => User::role('Currier')->withCount(['orders' => function ($q) {
        $q->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled]);
      }])->get(),
    ]);
  }

  public function edit(Order $order): Response
  {
    $order->load(['items.product']);

    $order->load('client');

    return Inertia::render('orders/Edit')->with([
      'order'    => $order,
      'clients'  => User::role('Client')->with('userProfile')->get(['id', 'name', 'email']),
      'products' => Product::select(['id', 'name', 'price', 'sale_price', 'quantity', 'status'])->get(),
    ]);
  }

  /**
   * Credit the client's wallet for any amount paid above the current bill.
   */
  private function refundOrderOverpayment(Order $order, WalletService $walletService): float
  {
    $amount = $order->overpaymentAmount();
    if ($amount <= 0) {
      return 0.0;
    }

    $order->loadMissing('client');
    $walletService->refund($order->client, $amount, Order::class, $order->id, [
      'order_number' => $order->order_number,
      'reason'       => 'order_price_adjustment',
    ]);
    $order->update(['paid_amount' => $order->grand_total]);

    return $amount;
  }

  public function update(UpdateOrderRequest $request, Order $order, WalletService $walletService)
  {
    DB::transaction(function () use ($request, $order) {
      $isDelivered = $order->status->value === OrderStatus::Delivered;

      $items = collect($request->items)->map(function ($item) use ($isDelivered) {
        $product   = Product::findOrFail($item['product_id']);
        $unitPrice = $this->resolveItemUnitPrice($item, $product);
        $isGift    = (bool) ($item['is_gift'] ?? false);
        $subtotal  = $isGift ? 0 : $unitPrice * $item['quantity'];

        return [
          'product_id' => $item['product_id'],
          'quantity'   => $item['quantity'],
          // Editing a delivered order is a correction to what was handed
          // over, so new lines are treated as fully delivered. For non-
          // delivered orders this stays NULL (set later at delivery time).
          'delivered_quantity' => $isDelivered ? (int) $item['quantity'] : null,
          'unit_price' => $unitPrice,
          'subtotal'   => $subtotal,
          'is_gift'    => $isGift,
        ];
      });

      $calculatedTotal = (float) $items->sum('subtotal');
      [$finalTotal, $discount] = $this->resolveTotals($calculatedTotal, $request->input('custom_total'));

      $updates = [
        'user_id'               => $request->user_id,
        'scheduled_delivery_at' => $request->scheduled_delivery_at,
        'delivery_address'      => $request->delivery_address,
        'notes'                 => $request->notes,
        'total_amount'          => $finalTotal,
        'discount_amount'       => $discount,
      ];

      $order->update($updates);

      // Restore inventory by what was *actually* deducted, not what was
      // ordered. After a partial delivery the live deduction equals
      // delivered_quantity; only that much was taken from stock and only
      // that much should be returned before the new items are deducted.
      $existingItems = $order->items()->get();
      $restoreSet = $existingItems->map(fn($i) => [
        'product_id' => $i->product_id,
        'quantity'   => (int) ($i->delivered_quantity ?? $i->quantity),
      ])->all();
      $this->adjustRawMaterialStock($restoreSet, +1);

      $order->items()->delete();
      $order->items()->createMany($items->toArray());

      $this->adjustRawMaterialStock($items, -1);

      $order->refresh()->reconcilePaymentStatus();
    });

    $order->refresh();

    $refunded = 0.0;
    if ($request->boolean('refund_overpayment')) {
      $refunded = DB::transaction(fn () => $this->refundOrderOverpayment($order, $walletService));
      $order->refresh()->reconcilePaymentStatus();
    }

    app(OrderAccountingService::class)->syncPaymentRecord($order->fresh());

    $redirect = redirect()->route('admin.orders.show', $order);

    if ($refunded > 0) {
      return $redirect->with('success', "Order updated. {$refunded} refunded to the client's wallet.");
    }

    $overpayment = $order->overpaymentAmount();
    if ($overpayment > 0 && ! $request->boolean('skip_pending_overpayment_refund')) {
      return $redirect
        ->with('success', 'Order updated successfully.')
        ->with('pending_overpayment_refund', [
          'amount'       => $overpayment,
          'paid_amount'  => (float) $order->paid_amount,
          'grand_total'  => $order->grand_total,
        ]);
    }

    return $redirect->with('success', 'Order updated successfully.');
  }

  public function refundOverpayment(Order $order, WalletService $walletService)
  {
    $order->refresh();

    if ($order->overpaymentAmount() <= 0) {
      return back()->with('error', 'This order has no overpayment to refund.');
    }

    $refunded = DB::transaction(fn () => $this->refundOrderOverpayment($order, $walletService));
    $order->refresh()->reconcilePaymentStatus();
    app(OrderAccountingService::class)->syncPaymentRecord($order);

    return back()->with('success', number_format($refunded, 2) . " refunded to the client's wallet.");
  }

  public function cancel(Order $order)
  {
    if ($order->status->value === OrderStatus::Delivered) {
      return back()->with('error', 'Delivered orders cannot be cancelled.');
    }

    $data = request()->validate([
      'cancellation_reason' => ['required', 'string', 'max:1000'],
    ]);

    DB::transaction(function () use ($order, $data) {
      // Already-cancelled orders shouldn't double-restore stock. The status
      // dropdown blocks this client-side, but guard anyway in case the cancel
      // endpoint is hit directly.
      if ($order->status->value !== OrderStatus::Cancelled) {
        $this->adjustRawMaterialStock($order->items()->get(), +1);
      }

      $order->update([
        'status'              => OrderStatus::Cancelled,
        'cancellation_reason' => $data['cancellation_reason'],
        'cancelled_at'        => now(),
        'cancelled_by'        => auth()->id(),
      ]);
    });

    return back()->with('success', 'Order cancelled.');
  }

  /**
   * Hard-delete an order. Admin escape hatch for orders created in error,
   * test data, etc. Inventory is restored based on what was actually
   * deducted (delivered_quantity if delivery happened, otherwise the full
   * ordered quantity); cancelled orders skip the restore step because
   * cancel() already returned those units. FK constraints handle the rest:
   *   - order_items   → cascadeOnDelete
   *   - order_returned_materials → cascadeOnDelete
   *   - orders.parent_order_id (child backorders) → nullOnDelete
   * Wallet transactions remain in the ledger as orphans pointing to the
   * deleted order id; their `meta` JSON carries the order_number for
   * later reconstruction.
   */
  public function destroy(Order $order)
  {
    $isCancelled = $order->status->value === OrderStatus::Cancelled;

    DB::transaction(function () use ($order, $isCancelled) {
      if (!$isCancelled) {
        $restoreSet = $order->items()->get()->map(fn($i) => [
          'product_id' => $i->product_id,
          'quantity'   => (int) ($i->delivered_quantity ?? $i->quantity),
        ])->all();
        $this->adjustRawMaterialStock($restoreSet, +1);
      }

      $order->delete();
    });

    return redirect()->route('admin.orders.index')
      ->with('success', "Order #{$order->order_number} deleted.");
  }

  public function updateStatus(Order $order)
  {
    $data = request()->validate([
      'status'             => ['required', 'in:' . implode(',', OrderStatus::getValues())],
      'actual_delivery_at' => ['nullable', 'date'],
      'returned_materials' => ['nullable', 'array'],
      'returned_materials.*.raw_material_id' => ['required', 'exists:raw_materials,id'],
      'returned_materials.*.quantity' => ['required', 'integer', 'min:1'],
      // Partial delivery: per-line actual delivered count and what to do
      // with any shortfall. Optional — when absent or empty, the existing
      // "fully delivered" behavior applies.
      'delivered_items'                      => ['nullable', 'array'],
      'delivered_items.*.order_item_id'      => ['required', 'integer'],
      'delivered_items.*.delivered_quantity' => ['required', 'integer', 'min:0'],
      'delivered_items.*.shortfall_action'   => ['nullable', 'in:dismiss,backorder'],
    ]);

    $wasAlreadyDelivered = $order->status->value === OrderStatus::Delivered;
    $isDeliveringNow     = $data['status'] === OrderStatus::Delivered && !$wasAlreadyDelivered;

    DB::transaction(function () use ($order, $data, $isDeliveringNow) {
        $order->update([
            'status' => $data['status'],
            'actual_delivery_at' => $data['actual_delivery_at'] ?? null,
        ]);

        // Partial-delivery handling — only on the first transition into
        // Delivered, so re-saves (editing returned materials) don't re-run
        // shortfall logic and double-restore inventory.
        if ($isDeliveringNow) {
            $this->applyPartialDelivery($order, $data['delivered_items'] ?? []);
        }

        if (isset($data['returned_materials']) && $data['status'] === OrderStatus::Delivered) {
            $syncData = [];
            foreach ($data['returned_materials'] as $rm) {
                $syncData[$rm['raw_material_id']] = ['quantity' => $rm['quantity']];

                \App\Models\RawMaterial::where('id', $rm['raw_material_id'])
                  ->increment('current_stock', $rm['quantity']);
            }
            $order->returnedMaterials()->sync($syncData);
        }

        // After delivery, charge the client for any reusable containers
        // (e.g. 19L bottles) they didn't return. Recomputed every time so
        // re-editing the returned-materials list keeps the bill correct.
        if ($data['status'] === OrderStatus::Delivered) {
            $this->applyDepositCharge($order->fresh(['items.product.rawMaterials', 'returnedMaterials']));
        }
    });

    $order->refresh()->load('courier');
    event(new \App\Events\OrderStatusUpdated($order));

    if (!$wasAlreadyDelivered && $data['status'] === OrderStatus::Delivered) {
        event(new \App\Events\OrderDelivered($order->fresh(['client', 'courier'])));
    }

    return back()->with('success', 'Order status updated.');
  }

  /**
   * Apply the courier's per-line delivered counts at the moment the order
   * transitions to Delivered:
   *   - Stamp delivered_quantity on each OrderItem.
   *   - Restore inventory for any shortfall.
   *   - For shortfall lines flagged "backorder", spawn a pending child
   *     order carrying the missing units and re-deduct inventory for it.
   *   - Recompute the parent order's total_amount from delivered subtotals,
   *     preserving the original discount ratio so a discounted order stays
   *     proportionally discounted.
   */
  private function applyPartialDelivery(Order $order, array $deliveredItems): void
  {
    $order->loadMissing(['items.product.rawMaterials']);
    $byId = $order->items->keyBy('id');

    // Default: no entry means the line was fully delivered.
    $resolved = [];
    foreach ($order->items as $item) {
      $resolved[$item->id] = [
        'item'               => $item,
        'delivered_quantity' => (int) $item->quantity,
        'shortfall_action'   => null,
      ];
    }
    foreach ($deliveredItems as $row) {
      $itemId = (int) $row['order_item_id'];
      if (!isset($resolved[$itemId])) continue;
      $delivered = min((int) $row['delivered_quantity'], (int) $resolved[$itemId]['item']->quantity);
      $resolved[$itemId]['delivered_quantity'] = max(0, $delivered);
      $resolved[$itemId]['shortfall_action']   = $row['shortfall_action'] ?? null;
    }

    $originalCalculated = (float) $order->items->sum(
      fn($i) => $i->is_gift ? 0 : (float) $i->unit_price * (int) $i->quantity
    );
    $originalDiscount   = (float) $order->discount_amount;

    $deliveredCalculated = 0.0;
    $restoreItems        = [];
    $backorderItems      = [];

    foreach ($resolved as $row) {
      $item      = $row['item'];
      $delivered = $row['delivered_quantity'];
      $shortfall = (int) $item->quantity - $delivered;

      $item->forceFill(['delivered_quantity' => $delivered])->save();

      if (!$item->is_gift) {
        $deliveredCalculated += (float) $item->unit_price * $delivered;
      }

      if ($shortfall > 0) {
        $restoreItems[] = ['product_id' => $item->product_id, 'quantity' => $shortfall];

        if ($row['shortfall_action'] === 'backorder') {
          $backorderItems[] = [
            'product_id' => $item->product_id,
            'quantity'   => $shortfall,
            'unit_price' => (float) $item->unit_price,
            'is_gift'    => (bool) $item->is_gift,
            'subtotal'   => $item->is_gift ? 0 : (float) $item->unit_price * $shortfall,
          ];
        }
      }
    }

    if (!empty($restoreItems)) {
      $this->adjustRawMaterialStock($restoreItems, +1);
    }

    // Recompute the parent's billable total. Preserve the original
    // discount ratio so a 20%-off order stays 20% off on whatever was
    // actually delivered, instead of letting the full discount swallow
    // a partial shipment.
    $ratio = $originalCalculated > 0
      ? round($deliveredCalculated / $originalCalculated, 6)
      : 0.0;
    $discountForDelivered = round($originalDiscount * $ratio, 2);
    $newTotal             = max(0.0, round($deliveredCalculated - $discountForDelivered, 2));

    $order->update([
      'total_amount'    => $newTotal,
      'discount_amount' => $discountForDelivered,
    ]);

    $order->refresh()->reconcilePaymentStatus();

    if (!empty($backorderItems)) {
      $backorder = Order::create([
        'user_id'               => $order->user_id,
        'parent_order_id'       => $order->id,
        'contact_name'          => $order->contact_name,
        'contact_phone'         => $order->contact_phone,
        'status'                => OrderStatus::Pending,
        // No schedule by default — admin will set when the follow-up
        // delivery is actually planned. Column is nullable per migration
        // 2026_05_24_002518.
        'scheduled_delivery_at' => null,
        'delivery_address'      => $order->delivery_address,
        'notes'                 => trim(($order->notes ? $order->notes . "\n\n" : '')
          . "Backorder for #{$order->order_number} — shortfall from delivery on "
          . now()->format('Y-m-d H:i') . '.'),
        'total_amount'          => array_sum(array_column($backorderItems, 'subtotal')),
        'discount_amount'       => 0,
        'payment_status'        => array_sum(array_column($backorderItems, 'subtotal')) > 0
          ? PaymentStatus::Unpaid
          : PaymentStatus::Paid,
        'created_by'            => auth()->id(),
      ]);
      $backorder->items()->createMany($backorderItems);

      // The shortfall was just restored to inventory; the backorder
      // reserves those units again so net inventory matches reality.
      $this->adjustRawMaterialStock($backorderItems, -1);
    }

    app(OrderAccountingService::class)->syncPaymentRecord($order);
  }

  /**
   * Refresh the order's deposit_charge from the reusable-material
   * summary, and re-evaluate payment_status so an underpaid order
   * shows as Unpaid (or Paid if the running total catches up).
   */
  private function applyDepositCharge(Order $order): void
  {
    $summary = $order->reusableDepositSummary();
    $charge = (float) collect($summary)->sum('charge');

    if ((float) $order->deposit_charge === $charge) {
        return;
    }

    $order->deposit_charge = $charge;
    $order->save();

    $order->refresh()->reconcilePaymentStatus();

    app(OrderAccountingService::class)->syncPaymentRecord($order);
  }

  public function payWithWallet(Order $order, \App\Services\WalletService $walletService, OrderAccountingService $orderAccounting)
  {
    if ($order->status->value === OrderStatus::Cancelled) {
      return back()->with('error', 'Cancelled orders cannot be paid.');
    }

    if ($order->isFullyPaid()) {
      $order->reconcilePaymentStatus();

      return back()->with('error', 'Order is already paid.');
    }

    $amountToPay = (float) $order->balance_due;
    if ($amountToPay <= 0) {
      $order->reconcilePaymentStatus();
      $orderAccounting->syncPaymentRecord($order->fresh());

      return back()->with('success', 'Order marked as paid.');
    }

    try {
      // Treat the click as "the client just paid the order's balance" — top
      // the wallet up for the full balance and immediately withdraw it for
      // this order. Net wallet movement is zero, but we get two ledger rows
      // (deposit + payment) so the audit trail tells the full story.
      DB::transaction(function () use ($order, $walletService, $amountToPay, $orderAccounting) {
        $walletService->deposit(
          $order->client,
          $amountToPay,
          Order::class,
          $order->id,
          [
            'order_number' => $order->order_number,
            'reason'       => 'auto_topup_for_order_payment',
          ],
        );

        $walletService->pay($order->client, $amountToPay, Order::class, $order->id, [
          'order_number' => $order->order_number,
        ]);

        $order->increment('paid_amount', $amountToPay);
        $order->refresh()->reconcilePaymentStatus();

        $orderAccounting->syncPaymentRecord($order);
      });

      return back()->with('success', 'Funds added and order paid.');
    } catch (\Exception $e) {
      return back()->with('error', $e->getMessage());
    }
  }

  public function assignCurrier(Order $order)
  {
      if ($order->status->value === OrderStatus::Cancelled) {
          return back()->with('error', 'Cancelled orders cannot be assigned to a courier.');
      }

      if ($order->delivery_address === 'Self Pickup') {
          return back()->with('error', 'Self-pickup orders do not need a courier.');
      }

      request()->validate([
          'courier_id' => ['nullable', 'exists:users,id'],
      ]);

      $courierId = request()->input('courier_id');

      if ($courierId) {
          $courier = User::findOrFail($courierId);
          if (!$courier->hasRole('Currier')) {
              return back()->with('error', 'The selected user is not a currier.');
          }
      }

      $order->update(['courier_id' => $courierId]);

      return back()->with('success', 'Currier updated successfully.');
  }

  /**
   * Live courier-location tracking for the signed-in client's active order.
   * Returns the assigned courier's last fix when an order is in
   * Accepted / Ready / InTransit; otherwise reports tracking: false.
   */
  public function activeTracking(\Illuminate\Http\Request $request)
  {
    $user = $request->user();

    $order = Order::where('user_id', $user->id)
      ->whereIn('status', [
        OrderStatus::Accepted,
        OrderStatus::Ready,
        OrderStatus::InTransit,
      ])
      ->whereNotNull('courier_id')
      ->with(['courier.lastLocation'])
      ->latest()
      ->first();

    if (! $order || ! $order->courier || ! $order->courier->lastLocation) {
      return response()
        ->json(['tracking' => false])
        ->header('Cache-Control', 'no-store, max-age=0');
    }

    $loc = $order->courier->lastLocation;

    return response()->json([
      'tracking' => true,
      'order' => [
        'id' => $order->id,
        'status' => (string) $order->status,
      ],
      'courier' => [
        'lat' => (float) $loc->lat,
        'lng' => (float) $loc->lng,
        'updated_at' => $loc->updated_at->toIso8601String(),
      ],
    ])->header('Cache-Control', 'no-store, max-age=0');
  }
}
