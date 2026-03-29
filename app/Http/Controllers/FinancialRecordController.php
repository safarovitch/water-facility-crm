<?php

namespace App\Http\Controllers;

use App\Enums\FinancialTransactionCategory;
use App\Enums\FinancialTransactionType;
use App\Models\FinancialRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialRecordController extends Controller
{
    public function index(Request $request): Response
    {
        $query = FinancialRecord::with('recorder');

        // Filtering
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

        // Clone query for totals before pagination
        $totalsQuery = clone $query;
        
        $totalIncome = (float) $totalsQuery->where('type', FinancialTransactionType::Income)->sum('amount');
        
        // Reset type for expense calculation
        $totalsQuery = clone $query;
        $totalExpense = (float) $totalsQuery->where('type', FinancialTransactionType::Expense)->sum('amount');

        $records = $query->latest('transaction_date')->latest('id')->paginate(50);

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
            'summary'      => [
                'total_income'  => $totalIncome,
                'total_expense' => $totalExpense,
                'balance'       => $totalIncome - $totalExpense,
            ],
            'filters'      => $request->only(['type', 'category', 'from', 'to']),
            'types'        => FinancialTransactionType::asSelectArray(),
            'categories'   => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'type'             => ['required', 'string', 'in:' . implode(',', FinancialTransactionType::getValues())],
            'category'         => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'transaction_date' => ['required', 'date'],
            'receipt'          => ['nullable', 'image', 'max:5120'],
        ]);

        $data['recorded_by_id'] = auth()->id();

        $record = FinancialRecord::create($data);

        if ($request->hasFile('receipt')) {
            $record->addMediaFromRequest('receipt')->toMediaCollection('receipts');
        }

        return back()->with('success', 'Financial record added successfully.');
    }

    public function update(Request $request, FinancialRecord $financialRecord)
    {
        $data = $request->validate([
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'type'             => ['required', 'string', 'in:' . implode(',', FinancialTransactionType::getValues())],
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
}
