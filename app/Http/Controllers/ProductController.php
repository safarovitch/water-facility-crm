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
  public function index(): Response
  {
    $pagination = request()->has('pagination')
      ? request()->input('pagination')
      : ['limit' => 50, 'page' => 1];

    return Inertia::render('products/Index')->with([
      'products' => Product::query()->paginate(
        $pagination['limit'],
        ['*'],
        'page',
        $pagination['page']
      )
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
    ]);
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(Request $request): RedirectResponse
  {
    $validated = $request->validate([
      'name'               => 'required|array',
      'name.en'            => 'required|string|max:255',
      'sku'                => 'required|string|max:100|unique:products,sku',
      'price'              => 'required|numeric|min:0',
      'sale_price'         => 'nullable|numeric|min:0',
      'cost'               => 'nullable|numeric|min:0',
      'weight'             => 'nullable|numeric|min:0',
      'quantity'           => 'required|integer|min:0',
      'currency'           => 'required|string|max:10',
      'manage_stock'       => 'boolean',
      'low_stock_threshold' => 'nullable|integer|min:0',
      'low_stock_action'   => 'nullable|string',
      'status'             => 'required|string',
      'short_description'  => 'nullable|array',
      'description'        => 'nullable|array',
      'dimensions'         => 'nullable|array',
    ]);

    $validated['sale_price']  = $validated['sale_price']  ?? 0;
    $validated['cost']        = $validated['cost']        ?? 0;
    $validated['weight']      = $validated['weight']      ?? 0;
    $validated['low_stock_threshold'] = $validated['low_stock_threshold'] ?? 0;
    $validated['low_stock_action']    = $validated['low_stock_action']    ?? ProductLowStockAction::None;
    $validated['dimensions']  = $validated['dimensions']  ?? [];

    Product::create($validated);

    return redirect()->route('products.index')
      ->with('success', 'Product created successfully.');
  }

  /**
   * Show the form for editing the specified resource.
   */
  public function edit(Product $product): Response
  {
    return Inertia::render('products/Edit')->with([
      'product'        => $product,
      'statuses'       => ProductStatus::asSelectArray(),
      'lowStockActions' => ProductLowStockAction::asSelectArray(),
    ]);
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, Product $product): RedirectResponse
  {
    $validated = $request->validate([
      'name'               => 'required|array',
      'name.en'            => 'required|string|max:255',
      'sku'                => 'required|string|max:100|unique:products,sku,' . $product->id,
      'price'              => 'required|numeric|min:0',
      'sale_price'         => 'nullable|numeric|min:0',
      'cost'               => 'nullable|numeric|min:0',
      'weight'             => 'nullable|numeric|min:0',
      'quantity'           => 'required|integer|min:0',
      'currency'           => 'required|string|max:10',
      'manage_stock'       => 'boolean',
      'low_stock_threshold' => 'nullable|integer|min:0',
      'low_stock_action'   => 'nullable|string',
      'status'             => 'required|string',
      'short_description'  => 'nullable|array',
      'description'        => 'nullable|array',
      'dimensions'         => 'nullable|array',
    ]);

    $product->update($validated);

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
