<?php

namespace App\Http\Controllers;

use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RawMaterialController extends Controller
{
    public function index(Request $request)
    {
        $query = RawMaterial::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $materials = $query->latest()->paginate(50)->withQueryString();

        $units = RawMaterial::distinct()->pluck('unit')->toArray();

        return Inertia::render('RawMaterials/Index', [
            'materials' => $materials,
            'filters' => $request->only(['search', 'status']),
            'statuses' => ['active', 'inactive'],
            'units' => $units,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'sku'           => ['nullable', 'string', 'max:255', 'unique:raw_materials'],
            'unit'          => ['required', 'string', 'max:50'],
            'current_stock' => ['required', 'numeric', 'min:0'],
            'cost_per_unit' => ['required', 'numeric', 'min:0'],
            'deposit_price' => ['nullable', 'numeric', 'min:0'],
            'status'        => ['required', 'string'],
            'is_reusable'   => ['nullable', 'boolean'],
        ]);

        RawMaterial::create($data);

        return back()->with('success', 'Raw material added successfully.');
    }

    public function update(Request $request, RawMaterial $rawMaterial)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'sku'           => ['nullable', 'string', 'max:255', 'unique:raw_materials,sku,' . $rawMaterial->id],
            'unit'          => ['required', 'string', 'max:50'],
            'current_stock' => ['required', 'numeric', 'min:0'],
            'cost_per_unit' => ['required', 'numeric', 'min:0'],
            'deposit_price' => ['nullable', 'numeric', 'min:0'],
            'status'        => ['required', 'string'],
            'is_reusable'   => ['nullable', 'boolean'],
        ]);

        $rawMaterial->update($data);

        return back()->with('success', 'Raw material updated successfully.');
    }

    public function destroy(RawMaterial $rawMaterial)
    {
        $rawMaterial->delete();
        return back()->with('success', 'Raw material deleted.');
    }
}
