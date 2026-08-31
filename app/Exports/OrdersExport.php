<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\XlsxWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Builds the back-office spreadsheet of orders.
 *
 * One row per order — not per line item — so the money columns stay summable:
 * a per-item sheet would repeat each order's total on every line and any SUM
 * over it would be wrong. The line items, the returned empties and the deposit
 * breakdown are therefore spelled out inside their own text columns, which
 * keeps "all the detail" on the row it belongs to.
 *
 * The query arrives already scoped (a plain courier only ever sees their own
 * orders), filtered and sorted, so the sheet matches the screen exactly.
 */
class OrdersExport
{
    /**
     * The app has no server-side translation files — the UI dictionary lives
     * in resources/js/i18n. The export only needs a few dozen strings, so it
     * carries its own Russian labels rather than duplicating the whole
     * frontend dictionary on the backend. Mirrors FinancialRecordsExport.
     *
     * @var array<string, string>
     */
    private const RU = [
        'Orders report' => 'Отчёт по заказам',
        'Period' => 'Период',
        'Delivery date' => 'Дата доставки',
        'All time' => 'За всё время',
        'Generated' => 'Сформирован',
        'Filters' => 'Фильтры',
        'Orders' => 'Заказов',
        'Total' => 'Итого',
        'Unknown' => 'Неизвестно',
        // Column headers
        'Order #' => 'Номер заказа',
        'Order date' => 'Дата заказа',
        'Status' => 'Статус',
        'Client' => 'Клиент',
        'Phone' => 'Телефон',
        'Client type' => 'Тип клиента',
        'Region' => 'Регион',
        'Delivery address' => 'Адрес доставки',
        'Coordinates' => 'Координаты',
        'Scheduled delivery' => 'Плановая доставка',
        'Actual delivery' => 'Фактическая доставка',
        'Courier' => 'Курьер',
        'Created by' => 'Кто оформил',
        'Source' => 'Источник',
        'Items' => 'Позиции',
        'Units' => 'Единиц',
        'Delivered units' => 'Доставлено единиц',
        'Subtotal' => 'Сумма позиций',
        'Discount' => 'Скидка',
        'Order total' => 'Сумма заказа',
        'Deposit charge' => 'Залог за тару',
        'Grand total' => 'Всего к оплате',
        'Paid' => 'Оплачено',
        'Balance due' => 'Остаток к оплате',
        'Payment' => 'Оплата',
        'Empties expected' => 'Тара ожидается',
        'Empties returned' => 'Тара возвращена',
        'Empties deferred' => 'Тара отложена',
        'Empties unreturned' => 'Тара не возвращена',
        'Empties detail' => 'Детализация тары',
        'Notes' => 'Примечания',
        'Cancellation reason' => 'Причина отмены',
        'Cancelled at' => 'Дата отмены',
        // Values
        'Individual' => 'Физлицо',
        'Company' => 'Компания',
        'Subscription' => 'Подписка',
        'Backorder of' => 'Дозаказ по',
        'Manual' => 'Вручную',
        'gift' => 'подарок',
        'delivered' => 'доставлено',
        'expected' => 'ожид.',
        'returned' => 'возвр.',
        'deferred' => 'отлож.',
        'unreturned' => 'не возвр.',
        'All statuses' => 'Все статусы',
        'Unpaid only' => 'Только неоплаченные',
        'Search' => 'Поиск',
        // Payment statuses
        'unpaid' => 'Не оплачен',
        'partial' => 'Частично',
        'paid' => 'Оплачен',
    ];

    /**
     * @param  Builder<Order>  $orders  Already scoped, filtered and sorted.
     * @param  array<string, string|null>  $filters  The filters in effect.
     */
    public function __construct(
        private readonly Builder $orders,
        private readonly array $filters,
        private readonly string $locale = 'ru',
        private readonly string $currency = '',
    ) {}

    public function build(): XlsxWriter
    {
        $xlsx = new XlsxWriter($this->t('Orders'), $this->currency);
        $xlsx->addTitle($this->t('Orders report'));
        $xlsx->addTextRow([$this->t('Period').': '.$this->periodLabel('from', 'to')]);

        // The delivery window is a separate filter from the order date, so it
        // gets its own line rather than being folded into "Period".
        if (($this->filters['delivery_from'] ?? null) || ($this->filters['delivery_to'] ?? null)) {
            $xlsx->addTextRow([
                $this->t('Delivery date').': '.$this->periodLabel('delivery_from', 'delivery_to'),
            ]);
        }

        if ($describedFilters = $this->filterLabel()) {
            $xlsx->addTextRow([$this->t('Filters').': '.$describedFilters]);
        }

        $xlsx->addTextRow([$this->t('Generated').': '.Carbon::now()->format('d.m.Y H:i')]);
        $xlsx->addBlankRow();

        $xlsx->setColumns($this->columns());

        $count = 0;
        $totals = array_fill_keys([
            'units', 'delivered', 'subtotal', 'discount', 'total', 'deposit',
            'grand', 'paid', 'due', 'expected', 'returned', 'deferred', 'missing',
        ], 0.0);

        // lazy(), not cursor(): the deposit breakdown needs the whole
        // items → product → raw materials chain, and cursor() cannot eager
        // load, which would turn every row into a fistful of queries.
        foreach ($this->orders->lazy() as $order) {
            $count++;
            $xlsx->addRow($this->row($order, $totals));
        }

        $this->appendTotals($xlsx, $totals, $count);

        return $xlsx;
    }

    public function filename(): string
    {
        $from = $this->filters['from'] ?? null;
        $to = $this->filters['to'] ?? null;

        $period = match (true) {
            $from && $to => $from.'_'.$to,
            (bool) $from => 'from-'.$from,
            (bool) $to => 'to-'.$to,
            default => Carbon::now()->format('Y-m-d'),
        };

        return 'orders-'.$period.'.xlsx';
    }

    /**
     * @return list<array{label: string, type: string, width: float}>
     */
    private function columns(): array
    {
        return [
            ['label' => $this->t('Order #'), 'type' => XlsxWriter::TEXT, 'width' => 16],
            ['label' => $this->t('Order date'), 'type' => XlsxWriter::DATE, 'width' => 18],
            ['label' => $this->t('Status'), 'type' => XlsxWriter::TEXT, 'width' => 20],
            ['label' => $this->t('Client'), 'type' => XlsxWriter::TEXT, 'width' => 26],
            ['label' => $this->t('Phone'), 'type' => XlsxWriter::TEXT, 'width' => 18],
            ['label' => $this->t('Client type'), 'type' => XlsxWriter::TEXT, 'width' => 14],
            ['label' => $this->t('Region'), 'type' => XlsxWriter::TEXT, 'width' => 16],
            ['label' => $this->t('Delivery address'), 'type' => XlsxWriter::TEXT, 'width' => 40],
            ['label' => $this->t('Coordinates'), 'type' => XlsxWriter::TEXT, 'width' => 20],
            ['label' => $this->t('Scheduled delivery'), 'type' => XlsxWriter::DATE, 'width' => 18],
            ['label' => $this->t('Actual delivery'), 'type' => XlsxWriter::DATE, 'width' => 18],
            ['label' => $this->t('Courier'), 'type' => XlsxWriter::TEXT, 'width' => 20],
            ['label' => $this->t('Created by'), 'type' => XlsxWriter::TEXT, 'width' => 20],
            ['label' => $this->t('Source'), 'type' => XlsxWriter::TEXT, 'width' => 18],
            ['label' => $this->t('Items'), 'type' => XlsxWriter::TEXT, 'width' => 52],
            ['label' => $this->t('Units'), 'type' => XlsxWriter::NUMBER, 'width' => 10],
            ['label' => $this->t('Delivered units'), 'type' => XlsxWriter::NUMBER, 'width' => 12],
            ['label' => $this->t('Subtotal'), 'type' => XlsxWriter::MONEY, 'width' => 15],
            ['label' => $this->t('Discount'), 'type' => XlsxWriter::MONEY, 'width' => 13],
            ['label' => $this->t('Order total'), 'type' => XlsxWriter::MONEY, 'width' => 15],
            ['label' => $this->t('Deposit charge'), 'type' => XlsxWriter::MONEY, 'width' => 15],
            ['label' => $this->t('Grand total'), 'type' => XlsxWriter::MONEY, 'width' => 15],
            ['label' => $this->t('Paid'), 'type' => XlsxWriter::MONEY, 'width' => 15],
            ['label' => $this->t('Balance due'), 'type' => XlsxWriter::MONEY, 'width' => 15],
            ['label' => $this->t('Payment'), 'type' => XlsxWriter::TEXT, 'width' => 14],
            ['label' => $this->t('Empties expected'), 'type' => XlsxWriter::NUMBER, 'width' => 12],
            ['label' => $this->t('Empties returned'), 'type' => XlsxWriter::NUMBER, 'width' => 12],
            ['label' => $this->t('Empties deferred'), 'type' => XlsxWriter::NUMBER, 'width' => 12],
            ['label' => $this->t('Empties unreturned'), 'type' => XlsxWriter::NUMBER, 'width' => 14],
            ['label' => $this->t('Empties detail'), 'type' => XlsxWriter::TEXT, 'width' => 44],
            ['label' => $this->t('Notes'), 'type' => XlsxWriter::TEXT, 'width' => 34],
            ['label' => $this->t('Cancellation reason'), 'type' => XlsxWriter::TEXT, 'width' => 30],
            ['label' => $this->t('Cancelled at'), 'type' => XlsxWriter::DATE, 'width' => 18],
        ];
    }

    /**
     * @param  array<string, float>  $totals  Accumulated in place.
     * @return list<mixed>
     */
    private function row(Order $order, array &$totals): array
    {
        $units = 0;
        $delivered = 0;
        $lines = [];

        foreach ($order->items as $item) {
            $units += (int) $item->quantity;
            // Before delivery there is no recorded count yet; the ordered
            // quantity is the best estimate, matching how the deposit summary
            // treats an undelivered order.
            $delivered += (int) ($item->delivered_quantity ?? $item->quantity);
            $lines[] = $this->itemLine($item);
        }

        $deposits = $order->reusableDepositSummary();

        $expected = 0.0;
        $returned = 0;
        $deferred = 0;
        $missing = 0.0;
        $depositLines = [];

        foreach ($deposits as $entry) {
            $expected += (float) $entry['expected'];
            $returned += (int) $entry['returned'];
            $deferred += (int) $entry['deferred'];
            $missing += (float) $entry['missing'];
            $depositLines[] = $entry['raw_material']->name.' — '
                .$this->t('expected').' '.$this->quantity($entry['expected']).', '
                .$this->t('returned').' '.$entry['returned'].', '
                .$this->t('deferred').' '.$entry['deferred'].', '
                .$this->t('unreturned').' '.$this->quantity($entry['missing']);
        }

        $total = (float) $order->total_amount;
        $discount = (float) $order->discount_amount;
        $deposit = (float) $order->deposit_charge;
        $paid = (float) $order->paid_amount;

        $totals['units'] += $units;
        $totals['delivered'] += $delivered;
        $totals['subtotal'] += $total + $discount;
        $totals['discount'] += $discount;
        $totals['total'] += $total;
        $totals['deposit'] += $deposit;
        $totals['grand'] += $order->grand_total;
        $totals['paid'] += $paid;
        $totals['due'] += $order->balance_due;
        $totals['expected'] += $expected;
        $totals['returned'] += $returned;
        $totals['deferred'] += $deferred;
        $totals['missing'] += $missing;

        $profile = $order->client?->userProfile;

        return [
            $order->order_number,
            $order->created_at,
            $this->status($order),
            $order->contact_name ?: ($order->client?->name ?? $this->t('Unknown')),
            $order->contact_phone ?: $this->clientPhone($order),
            $profile?->type ? $this->t(ucfirst($profile->type->value)) : null,
            $profile?->region,
            $order->delivery_address,
            $order->lat !== null && $order->lng !== null ? $order->lat.', '.$order->lng : null,
            $order->scheduled_delivery_at,
            $order->actual_delivery_at,
            $order->courier?->name,
            $order->creator?->name,
            $this->source($order),
            implode('; ', $lines),
            $units,
            $delivered,
            round($total + $discount, 2),
            $discount,
            $total,
            $deposit,
            $order->grand_total,
            $paid,
            $order->balance_due,
            $this->t($order->payment_status?->value ?? 'unpaid'),
            $expected,
            $returned,
            $deferred,
            $missing,
            implode('; ', $depositLines),
            $order->notes,
            $order->cancellation_reason,
            $order->cancelled_at,
        ];
    }

    /**
     * A single line item, complete enough to reconstruct the order's maths:
     * "Вода 19л ×3 (доставлено 2) × 15.00 = 45.00".
     */
    private function itemLine(OrderItem $item): string
    {
        $line = $this->productName($item->product?->name).' ×'.(int) $item->quantity;

        if ($item->delivered_quantity !== null && (int) $item->delivered_quantity !== (int) $item->quantity) {
            $line .= ' ('.$this->t('delivered').' '.(int) $item->delivered_quantity.')';
        }

        // Gifts carry a unit price but are excluded from every total, so
        // printing their money would not add up against the columns.
        if ($item->is_gift) {
            return $line.' — '.$this->t('gift');
        }

        return $line.' × '.$this->money($item->unit_price).' = '.$this->money($item->subtotal);
    }

    /**
     * The bold totals line. Values are keyed by column header rather than
     * positioned by hand — a 33-column row padded with counted nulls silently
     * slides one column sideways the moment a column is added.
     *
     * @param  array<string, float>  $totals
     */
    private function appendTotals(XlsxWriter $xlsx, array $totals, int $count): void
    {
        $xlsx->addSummaryRow($this->rowByColumn([
            'Order #' => $this->t('Total'),
            'Units' => $totals['units'],
            'Delivered units' => $totals['delivered'],
            'Subtotal' => round($totals['subtotal'], 2),
            'Discount' => round($totals['discount'], 2),
            'Order total' => round($totals['total'], 2),
            'Deposit charge' => round($totals['deposit'], 2),
            'Grand total' => round($totals['grand'], 2),
            'Paid' => round($totals['paid'], 2),
            'Balance due' => round($totals['due'], 2),
            'Empties expected' => $totals['expected'],
            'Empties returned' => $totals['returned'],
            'Empties deferred' => $totals['deferred'],
            'Empties unreturned' => $totals['missing'],
        ]));

        $xlsx->addBlankRow();
        $xlsx->addTextRow([$this->t('Orders').': '.$count]);
    }

    /**
     * Expand a header-keyed map into a full-width row, nulls everywhere else.
     *
     * @param  array<string, mixed>  $values  Keyed by the *untranslated* header.
     * @return list<mixed>
     */
    private function rowByColumn(array $values): array
    {
        $positions = array_flip(array_column($this->columns(), 'label'));
        $row = array_fill(0, count($positions), null);

        foreach ($values as $header => $value) {
            $row[$positions[$this->t($header)]] = $value;
        }

        return $row;
    }

    private function status(Order $order): string
    {
        $value = $order->status?->value ?? '';

        if ($this->locale === 'ru') {
            return OrderStatus::label($value);
        }

        return ucfirst(str_replace('_', ' ', $value));
    }

    /**
     * Where the order came from — a subscription run, a partial delivery
     * rolled forward, or a person typing it in.
     */
    private function source(Order $order): string
    {
        if ($order->subscription_id) {
            return $this->t('Subscription').' #'.$order->subscription_id;
        }

        if ($order->parentOrder) {
            return $this->t('Backorder of').' '.$order->parentOrder->order_number;
        }

        return $this->t('Manual');
    }

    private function clientPhone(Order $order): ?string
    {
        $phones = $order->client?->phones;

        if (! $phones || $phones->isEmpty()) {
            return null;
        }

        return ($phones->firstWhere('is_default', true) ?? $phones->first())->phone;
    }

    /**
     * Product names are JSON per locale; fall back through the configured
     * locales before giving up on whatever key exists.
     */
    private function productName(mixed $name): string
    {
        if (is_string($name)) {
            return $name;
        }

        if (! is_array($name) || $name === []) {
            return $this->t('Unknown');
        }

        foreach ([$this->locale, ...config('app.available_locales', ['ru', 'tg'])] as $locale) {
            if (! empty($name[$locale])) {
                return (string) $name[$locale];
            }
        }

        return (string) (array_values($name)[0] ?? $this->t('Unknown'));
    }

    private function periodLabel(string $fromKey, string $toKey): string
    {
        $from = $this->filters[$fromKey] ?? null;
        $to = $this->filters[$toKey] ?? null;

        return match (true) {
            $from && $to => $this->date($from).' — '.$this->date($to),
            (bool) $from => $this->date($from).' — …',
            (bool) $to => '… — '.$this->date($to),
            default => $this->t('All time'),
        };
    }

    private function filterLabel(): ?string
    {
        $parts = [];

        if ($status = $this->filters['status'] ?? null) {
            $parts[] = $this->t('Status').': '.($this->locale === 'ru'
                ? OrderStatus::label((string) $status)
                : ucfirst(str_replace('_', ' ', (string) $status)));
        }

        if (($this->filters['payment'] ?? null) === 'unpaid') {
            $parts[] = $this->t('Unpaid only');
        }

        if ($search = $this->filters['search'] ?? null) {
            $parts[] = $this->t('Search').': '.$search;
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function date(string $value): string
    {
        return Carbon::parse($value)->format('d.m.Y');
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * BOM quantities are fractional (0.5 caps per bottle); show the decimals
     * only when there are any.
     */
    private function quantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }

    private function t(string $key): string
    {
        if ($this->locale !== 'ru') {
            return $key;
        }

        return self::RU[$key] ?? $key;
    }
}
