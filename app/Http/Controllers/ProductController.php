<?php

namespace App\Http\Controllers;

use App\Enums\ProductLowStockAction;
use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index(Request $request): Response
  {
    $query = Product::query();

    if ($request->filled('search')) {
      $query->where('sku', 'like', '%' . $request->search . '%')
            ->orWhere('name', 'like', '%' . $request->search . '%');
    }

    $products = $query->latest()->paginate(50)->withQueryString();

    return Inertia::render('products/Index')->with([
      'products' => $products,
      'filters'  => $request->only(['search']),
    ]);
  }

  /**
   * Show the form for creating a new resource.
   */
  public function create(): Response
  {
    return Inertia::render('products/Create')->with([
      'statuses'       => ProductStatus::asSelectArray(),
      'lowStockActions' => ProductLowStockAction::asSelectArray(),
      'availableRawMaterials' => \App\Models\RawMaterial::select('id', 'name', 'unit', 'sku')->get(),
    ]);
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(Request $request): RedirectResponse
  {
    $locales = config('app.available_locales', ['ru', 'tg']);
    
    $rules = [
      'name'                => 'required|array',
      'sku'                 => 'required|string|max:100|unique:products,sku',
      'price'               => 'required|numeric|min:0',
      'sale_price'          => 'nullable|numeric|min:0',
      'cost'                => 'nullable|numeric|min:0',
      'weight'              => 'nullable|numeric|min:0',
      'quantity'            => 'required|integer|min:0',
      'currency'            => 'nullable|string|max:10',
      'manage_stock'        => 'boolean',
      'low_stock_threshold' => 'nullable|integer|min:0',
      'low_stock_action'    => 'nullable|string',
      'status'              => 'required|string',
      'short_description'   => 'nullable|array',
      'description'         => 'nullable|array',
      'dimensions'          => 'nullable|array',
      'raw_materials'       => 'nullable|array',
      'raw_materials.*.id'  => 'required|exists:raw_materials,id',
      'raw_materials.*.quantity' => 'required|numeric|min:0.0001',
    ];

    foreach ($locales as $locale) {
      $rules["name.{$locale}"] = 'required|string|max:255';
    }

    $validated = $request->validate($rules);

    $validated['sale_price']  = $validated['sale_price']  ?? 0;
    $validated['cost']        = $validated['cost']        ?? 0;
    $validated['weight']      = $validated['weight']      ?? 0;
    $validated['low_stock_threshold'] = $validated['low_stock_threshold'] ?? 0;
    $validated['low_stock_action']    = $validated['low_stock_action']    ?? ProductLowStockAction::None;
    $validated['dimensions']  = $validated['dimensions']  ?? [];
    $validated['currency']    = $validated['currency'] ?? config('app.currency', env('APP_CURRENCY', 'UZS'));

    $product = Product::create($validated);

    if ($request->has('raw_materials') && is_array($request->raw_materials)) {
      $syncData = [];
      foreach ($request->raw_materials as $rm) {
        $syncData[$rm['id']] = ['quantity' => $rm['quantity']];
      }
      $product->rawMaterials()->sync($syncData);
    }

    if ($request->hasFile('image')) {
      $product->addMediaFromRequest('image')->toMediaCollection('image');
    }

    return redirect()->route('products.index')
      ->with('success', 'Product created successfully.');
  }

  /**
   * Show the form for editing the specified resource.
   */
  public function edit(Product $product): Response
  {
    $product->load('rawMaterials');
    return Inertia::render('products/Edit')->with([
      'product'        => $product,
      'statuses'       => ProductStatus::asSelectArray(),
      'lowStockActions' => ProductLowStockAction::asSelectArray(),
      'availableRawMaterials' => \App\Models\RawMaterial::select('id', 'name', 'unit', 'sku')->get(),
    ]);
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, Product $product): RedirectResponse
  {
    $locales = config('app.available_locales', ['ru', 'tg']);

    $rules = [
      'name'                => 'required|array',
      'sku'                 => 'required|string|max:100|unique:products,sku,' . $product->id,
      'price'               => 'required|numeric|min:0',
      'sale_price'          => 'nullable|numeric|min:0',
      'cost'                => 'nullable|numeric|min:0',
      'weight'              => 'nullable|numeric|min:0',
      'quantity'            => 'required|integer|min:0',
      'currency'            => 'nullable|string|max:10',
      'manage_stock'        => 'boolean',
      'low_stock_threshold' => 'nullable|integer|min:0',
      'low_stock_action'    => 'nullable|string',
      'status'              => 'required|string',
      'short_description'   => 'nullable|array',
      'description'         => 'nullable|array',
      'dimensions'          => 'nullable|array',
      'raw_materials'       => 'nullable|array',
      'raw_materials.*.id'  => 'required|exists:raw_materials,id',
      'raw_materials.*.quantity' => 'required|numeric|min:0.0001',
    ];

    foreach ($locales as $locale) {
      $rules["name.{$locale}"] = 'required|string|max:255';
    }

    $validated = $request->validate($rules);
    
    $validated['currency'] = $validated['currency'] ?? config('app.currency', env('APP_CURRENCY', 'UZS'));

    $product->update($validated);

    if ($request->has('raw_materials') && is_array($request->raw_materials)) {
      $syncData = [];
      foreach ($request->raw_materials as $rm) {
        $syncData[$rm['id']] = ['quantity' => $rm['quantity']];
      }
      $product->rawMaterials()->sync($syncData);
    } else {
      $product->rawMaterials()->sync([]);
    }

    if ($request->hasFile('image')) {
      $product->clearMediaCollection('image');
      $product->addMediaFromRequest('image')->toMediaCollection('image');
    }

    return redirect()->route('products.index')
      ->with('success', 'Product updated successfully.');
  }

  /**
   * Remove the specified resource from storage.
   */
  public function destroy(Product $product): RedirectResponse
  {
    $product->delete();

    return redirect()->route('products.index')
      ->with('success', 'Product deleted.');
  }
}
