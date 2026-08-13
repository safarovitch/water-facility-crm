<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\FinancialTransactionType;
use App\Models\FinancialRecord;
use App\Support\XlsxWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Builds the accountant-facing spreadsheet of financial records.
 *
 * The sheet mirrors what the Accounting screen shows for the current user:
 * managers/admins get income and expense columns plus the company totals,
 * couriers get only the expenses they recorded themselves.
 */
class FinancialRecordsExport
{
    /**
     * The app has no server-side translation files — the UI dictionary lives
     * in resources/js/i18n. The export only needs a couple of dozen strings,
     * so it carries its own Russian labels rather than duplicating the whole
     * frontend dictionary on the backend.
     *
     * @var array<string, string>
     */
    private const RU = [
        'Accounting report' => 'Бухгалтерский отчёт',
        'My expenses' => 'Мои расходы',
        'Period' => 'Период',
        'All time' => 'За всё время',
        'Generated' => 'Сформирован',
        'Filters' => 'Фильтры',
        'Date' => 'Дата',
        'Type' => 'Тип',
        'Category' => 'Категория',
        'Description' => 'Описание',
        'Recorded by' => 'Кто внёс',
        'Income' => 'Приход',
        'Expense' => 'Расход',
        'Amount' => 'Сумма',
        'Receipt' => 'Чек',
        'Records' => 'Записей',
        'Total' => 'Итого',
        'Net balance' => 'Чистый баланс',
        'Revenue from delivered orders' => 'Выручка по доставленным заказам',
        'Unknown' => 'Неизвестно',
        // Transaction categories
        'wages' => 'Зарплата',
        'rent' => 'Аренда',
        'partner_payoff' => 'Выплаты партнёрам',
        'sales' => 'Продажи',
        'maintenance' => 'Обслуживание',
        'utilities' => 'Коммунальные услуги',
        'inventory' => 'Инвентарь',
        'transport' => 'Транспорт',
        'other' => 'Прочее',
    ];

    /**
     * @param  Builder<FinancialRecord>  $records  Already scoped, filtered and sorted.
     * @param  array<string, string|null>  $filters  The type/category/from/to filters in effect.
     * @param  float|null  $revenue  Delivered-order revenue for the period; null when hidden.
     */
    public function __construct(
        private readonly Builder $records,
        private readonly array $filters,
        private readonly bool $courierScoped,
        private readonly ?float $revenue,
        private readonly string $locale = 'ru',
        private readonly string $currency = '',
    ) {}

    public function build(): XlsxWriter
    {
        $title = $this->courierScoped ? 'My expenses' : 'Accounting report';

        $xlsx = new XlsxWriter($this->t($title), $this->currency);
        $xlsx->addTitle($this->t($title));
        $xlsx->addTextRow([$this->t('Period').': '.$this->periodLabel()]);

        if ($describedFilters = $this->filterLabel()) {
            $xlsx->addTextRow([$this->t('Filters').': '.$describedFilters]);
        }

        $xlsx->addTextRow([$this->t('Generated').': '.Carbon::now()->format('d.m.Y H:i')]);
        $xlsx->addBlankRow();

        $xlsx->setColumns($this->columns());

        $income = 0.0;
        $expense = 0.0;
        $count = 0;

        foreach ($this->records->cursor() as $record) {
            $amount = (float) $record->amount;
            $isIncome = $record->type->is(FinancialTransactionType::Income());

            $isIncome ? $income += $amount : $expense += $amount;
            $count++;

            $xlsx->addRow($this->row($record, $isIncome, $amount));
        }

        $this->appendTotals($xlsx, $income, $expense, $count);

        return $xlsx;
    }

    public function filename(): string
    {
        $prefix = $this->courierScoped ? 'my-expenses' : 'accounting';

        $from = $this->filters['from'] ?? null;
        $to = $this->filters['to'] ?? null;

        $period = match (true) {
            $from && $to => $from.'_'.$to,
            (bool) $from => 'from-'.$from,
            (bool) $to => 'to-'.$to,
            default => Carbon::now()->format('Y-m-d'),
        };

        return $prefix.'-'.$period.'.xlsx';
    }

    /**
     * @return list<array{label: string, type: string, width: float}>
     */
    private function columns(): array
    {
        if ($this->courierScoped) {
            return [
                ['label' => $this->t('Date'), 'type' => XlsxWriter::DATE, 'width' => 18],
                ['label' => $this->t('Category'), 'type' => XlsxWriter::TEXT, 'width' => 20],
                ['label' => $this->t('Description'), 'type' => XlsxWriter::TEXT, 'width' => 42],
                ['label' => $this->t('Amount'), 'type' => XlsxWriter::MONEY, 'width' => 16],
                ['label' => $this->t('Receipt'), 'type' => XlsxWriter::TEXT, 'width' => 30],
            ];
        }

        return [
            ['label' => $this->t('Date'), 'type' => XlsxWriter::DATE, 'width' => 18],
            ['label' => $this->t('Type'), 'type' => XlsxWriter::TEXT, 'width' => 12],
            ['label' => $this->t('Category'), 'type' => XlsxWriter::TEXT, 'width' => 20],
            ['label' => $this->t('Description'), 'type' => XlsxWriter::TEXT, 'width' => 42],
            ['label' => $this->t('Recorded by'), 'type' => XlsxWriter::TEXT, 'width' => 22],
            ['label' => $this->t('Income'), 'type' => XlsxWriter::MONEY, 'width' => 16],
            ['label' => $this->t('Expense'), 'type' => XlsxWriter::MONEY, 'width' => 16],
            ['label' => $this->t('Receipt'), 'type' => XlsxWriter::TEXT, 'width' => 30],
        ];
    }

    /**
     * @return list<mixed>
     */
    private function row(FinancialRecord $record, bool $isIncome, float $amount): array
    {
        $receipt = $record->getFirstMediaUrl('receipts');

        if ($this->courierScoped) {
            return [
                $record->transaction_date,
                $this->category($record->category),
                $record->description,
                $amount,
                $receipt,
            ];
        }

        return [
            $record->transaction_date,
            $this->t($isIncome ? 'Income' : 'Expense'),
            $this->category($record->category),
            $record->description,
            $record->recorder?->name ?? $this->t('Unknown'),
            $isIncome ? $amount : null,
            $isIncome ? null : $amount,
            $receipt,
        ];
    }

    /**
     * Totals sit directly under the matching money columns so the sheet reads
     * like a ledger; the derived figures follow on their own lines.
     */
    private function appendTotals(XlsxWriter $xlsx, float $income, float $expense, int $count): void
    {
        if ($this->courierScoped) {
            $xlsx->addSummaryRow([null, null, $this->t('Total'), $expense, null]);
            $xlsx->addBlankRow();
            $xlsx->addTextRow([$this->t('Records').': '.$count]);

            return;
        }

        $xlsx->addSummaryRow([null, null, null, null, $this->t('Total'), $income, $expense, null]);
        $xlsx->addBlankRow();
        $xlsx->addSummaryRow([null, null, null, $this->t('Net balance'), null, $income - $expense]);

        if ($this->revenue !== null) {
            $xlsx->addSummaryRow([null, null, null, $this->t('Revenue from delivered orders'), null, $this->revenue]);
        }

        $xlsx->addBlankRow();
        $xlsx->addTextRow([$this->t('Records').': '.$count]);
    }

    private function periodLabel(): string
    {
        $from = $this->filters['from'] ?? null;
        $to = $this->filters['to'] ?? null;

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

        if ($type = $this->filters['type'] ?? null) {
            $parts[] = $this->t('Type').': '.$this->t($type === FinancialTransactionType::Income ? 'Income' : 'Expense');
        }

        if ($category = $this->filters['category'] ?? null) {
            $parts[] = $this->t('Category').': '.$this->category($category);
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function date(string $value): string
    {
        return Carbon::parse($value)->format('d.m.Y');
    }

    /**
     * Categories are free text: known keys get a label, anything else is shown
     * as the user typed it.
     */
    private function category(string $category): string
    {
        if ($this->locale === 'ru' && isset(self::RU[$category])) {
            return self::RU[$category];
        }

        return ucfirst(str_replace('_', ' ', $category));
    }

    private function t(string $key): string
    {
        if ($this->locale !== 'ru') {
            return $key;
        }

        return self::RU[$key] ?? $key;
    }
}
