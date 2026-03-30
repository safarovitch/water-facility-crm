<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\FinancialRecord;
use App\Enums\FinancialTransactionCategory;
use App\Enums\FinancialTransactionType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryItemController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryItem::query();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('serial_number', 'like', '%' . $request->search . '%');
            });
        }

        $items = $query->latest()->paginate(50)->withQueryString();
        
        $categories = InventoryItem::distinct()->pluck('category')->toArray();
        $units = InventoryItem::distinct()->pluck('unit')->toArray();

        return Inertia::render('Inventory/Index', [
            'items' => $items,
            'filters' => $request->only(['category', 'status', 'search']),
            'categories' => $categories,
            'units' => $units,
            'statuses' => ['active', 'in_repair', 'lost', 'retired'],
            'summary' => [
                'total_items' => InventoryItem::count(),
                'active_items' => InventoryItem::where('status', 'active')->count(),
                'total_value' => InventoryItem::sum('purchase_price'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'category'       => ['required', 'string', 'max:255'],
            'quantity'       => ['required', 'numeric', 'min:0'],
            'unit'           => ['required', 'string', 'max:50'],
            'status'         => ['required', 'string', 'max:50'],
            'location'       => ['nullable', 'string', 'max:255'],
            'serial_number'  => ['nullable', 'string', 'max:255'],
            'purchase_date'  => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'photo'          => ['nullable', 'image', 'max:5120'], // 5MB limit
            'add_to_expense' => ['nullable', 'boolean'],
        ]);

        unset($data['add_to_expense']);
        $item = InventoryItem::create($data);

        if ($request->boolean('add_to_expense') && $item->purchase_price > 0) {
            $item->financialRecords()->create([
                'amount'           => $item->purchase_price,
                'type'             => FinancialTransactionType::Expense,
                'category'         => FinancialTransactionCategory::Inventory,
                'description'      => "Purchased inventory: " . $item->name,
                'transaction_date' => $item->purchase_date ?? now(),
                'recorded_by_id'   => auth()->id(),
            ]);
        }

        if ($request->hasFile('photo')) {
            $item->addMediaFromRequest('photo')->toMediaCollection('photo');
        }

        return back()->with('success', 'Inventory item added successfully.');
    }

    public function update(Request $request, InventoryItem $inventoryItem)
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'category'       => ['required', 'string', 'max:255'],
            'quantity'       => ['required', 'numeric', 'min:0'],
            'unit'           => ['required', 'string', 'max:50'],
            'status'         => ['required', 'string', 'max:50'],
            'location'       => ['nullable', 'string', 'max:255'],
            'serial_number'  => ['nullable', 'string', 'max:255'],
            'purchase_date'  => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'photo'          => ['nullable', 'image', 'max:5120'],
            'add_to_expense' => ['nullable', 'boolean'],
        ]);

        unset($data['add_to_expense']);
        $inventoryItem->update($data);

        if ($request->boolean('add_to_expense') && $inventoryItem->purchase_price > 0) {
            // Use relationship to ensure recordable_id and recordable_type are set
            $exists = $inventoryItem->financialRecords()->exists();
            
            if (!$exists) {
                $inventoryItem->financialRecords()->create([
                    'amount'           => $inventoryItem->purchase_price,
                    'type'             => FinancialTransactionType::Expense,
                    'category'         => FinancialTransactionCategory::Inventory,
                    'description'      => "Purchased inventory: " . $inventoryItem->name,
                    'transaction_date' => $inventoryItem->purchase_date ?? now(),
                    'recorded_by_id'   => auth()->id(),
                ]);
            }
        }

        if ($request->hasFile('photo')) {
            $inventoryItem->clearMediaCollection('photo');
            $inventoryItem->addMediaFromRequest('photo')->toMediaCollection('photo');
        }

        return back()->with('success', 'Inventory item updated successfully.');
    }

    public function destroy(InventoryItem $inventoryItem)
    {
        $inventoryItem->delete();
        return back()->with('success', 'Inventory item deleted.');
    }
}
