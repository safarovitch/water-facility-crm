<?php

namespace App\Http\Controllers;

use App\Enums\FinancialTransactionCategory;
use App\Enums\FinancialTransactionType;
use App\Enums\OrderStatus;
use App\Exports\FinancialRecordsExport;
use App\Http\Controllers\Concerns\SortsQueries;
use App\Models\FinancialRecord;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use BenSampo\Enum\Rules\EnumValue;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinancialRecordController extends Controller
{
    use SortsQueries;

    public function index(Request $request): Response
    {
        // Couriers only see the expenses they recorded themselves and never
        // company-wide revenue/income figures.
        $courierScoped = $request->user()->isCourierStaff();

        $query = $this->filteredQuery($request)->with('recorder');

        // Clone query for totals before pagination
        $totalsQuery = clone $query;

        $totalIncome = (float) $totalsQuery->where('type', FinancialTransactionType::Income)->sum('amount');

        // Reset type for expense calculation
        $totalsQuery = clone $query;
        $totalExpense = (float) $totalsQuery->where('type', FinancialTransactionType::Expense)->sum('amount');

        // Calculate total order revenue (from delivered orders) — hidden
        // from courier staff.
        $totalRevenue = $courierScoped
            ? null
            : (float) Order::where('status', OrderStatus::Delivered)->sum('total_amount');

        $this->applyRecordSort($query);

        $records = $query->paginate(50)->withQueryString();

        $dbCategories = FinancialRecord::distinct()->pluck('category')->toArray();
        $categories = FinancialTransactionCategory::asSelectArray();
        
        foreach ($dbCategories as $dbCat) {
            if (!array_key_exists($dbCat, $categories)) {
                // If it's a new custom string, just use it as both key and label
                $categories[$dbCat] = $dbCat;
            }
        }

        return Inertia::render('Financial/Index', [
            'records'      => $records,
            'summary'      => $courierScoped ? [
                // Own expenses only — no revenue/income/balance for couriers.
                'total_expense' => $totalExpense,
            ] : [
                'total_revenue' => $totalRevenue,
                'total_income'  => $totalIncome,
                'total_expense' => $totalExpense,
                'balance'       => $totalIncome - $totalExpense,
            ],
            'courierScoped' => $courierScoped,
            'filters'      => $request->only(['type', 'category', 'from', 'to']),
            'types'        => FinancialTransactionType::asSelectArray(),
            'categories'   => $categories,
        ]);
    }

    /**
     * Download the filtered records as an .xlsx sheet for the accountant.
     *
     * The export deliberately reuses the screen's scoping and filters, so a
     * courier only ever exports their own expenses and what lands in the file
     * is exactly what the user is looking at.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $courierScoped = $request->user()->isCourierStaff();

        $query = $this->filteredQuery($request)->with(['recorder', 'media']);
        $this->applyRecordSort($query);

        $export = new FinancialRecordsExport(
            records: $query,
            filters: $request->only(['type', 'category', 'from', 'to']),
            courierScoped: $courierScoped,
            revenue: $courierScoped ? null : $this->deliveredOrderRevenue($request),
            locale: $request->string('locale')->toString() === 'en' ? 'en' : 'ru',
            currency: (string) config('app.currency'),
        );

        $path = $export->build()->writeToTempFile();

        return response()
            ->download($path, $export->filename(), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'type'             => ['required', 'string', new EnumValue(FinancialTransactionType::class)],
            'category'         => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'transaction_date' => ['required', 'date'],
            'receipt'          => ['nullable', 'image', 'max:5120'],
        ]);

        // Couriers may only record expenses, never income entries.
        if ($request->user()->isCourierStaff()
            && $data['type'] !== FinancialTransactionType::Expense) {
            abort(403, 'Couriers can only record expenses.');
        }

        $data['recorded_by_id'] = auth()->id();

        $record = FinancialRecord::create($data);

        if ($request->hasFile('receipt')) {
            $record->addMediaFromRequest('receipt')->toMediaCollection('receipts');
        }

        return back()->with('success', 'Financial record added successfully.');
    }

    public function update(Request $request, FinancialRecord $financialRecord)
    {
        // Couriers may only edit their own expense records.
        if ($request->user()->isCourierStaff()
            && $financialRecord->recorded_by_id !== $request->user()->id) {
            abort(404);
        }

        $data = $request->validate([
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'type'             => ['required', 'string', new EnumValue(FinancialTransactionType::class)],
            'category'         => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'transaction_date' => ['required', 'date'],
            'receipt'          => ['nullable', 'image', 'max:5120'],
        ]);

        $financialRecord->update($data);

        if ($request->hasFile('receipt')) {
            $financialRecord->clearMediaCollection('receipts');
            $financialRecord->addMediaFromRequest('receipt')->toMediaCollection('receipts');
        }

        return back()->with('success', 'Financial record updated successfully.');
    }

    public function destroy(FinancialRecord $financialRecord)
    {
        $financialRecord->delete();

        return back()->with('success', 'Financial record deleted.');
    }

    /**
     * The record set behind both the Accounting screen and its export: scoped
     * to what the current user may see, narrowed by the request's filters.
     *
     * @return Builder<FinancialRecord>
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = FinancialRecord::query();

        // Couriers only see the expenses they recorded themselves.
        if ($request->user()->isCourierStaff()) {
            $query->where('recorded_by_id', $request->user()->id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('from')) {
            $query->whereDate('transaction_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('transaction_date', '<=', $request->to);
        }

        return $query;
    }

    /**
     * Whitelisted sorting; defaults to newest transaction first.
     */
    private function applyRecordSort(Builder $query): void
    {
        if (! $this->applySort($query, [
            'transaction_date' => 'transaction_date',
            'amount'           => 'amount',
            'category'         => 'category',
        ])) {
            $query->latest('transaction_date')->latest('id');
        }
    }

    /**
     * Revenue from delivered orders. A dated report counts the orders actually
     * delivered inside the window; without a period it matches the all-time
     * figure shown on the screen.
     */
    private function deliveredOrderRevenue(Request $request): float
    {
        $orders = Order::where('status', OrderStatus::Delivered);

        if ($request->filled('from')) {
            $orders->whereDate('actual_delivery_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $orders->whereDate('actual_delivery_at', '<=', $request->to);
        }

        return (float) $orders->sum('total_amount');
    }
}
