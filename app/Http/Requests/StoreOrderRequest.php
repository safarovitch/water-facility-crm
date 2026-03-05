<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'user_id'          => ['required', 'exists:users,id'],
      'delivery_date'    => ['nullable', 'date', 'after_or_equal:today'],
      'delivery_address' => ['nullable', 'string'],
      'notes'            => ['nullable', 'string'],

      'items'                  => ['required', 'array', 'min:1'],
      'items.*.product_id'     => ['required', 'exists:products,id'],
      'items.*.quantity'       => ['required', 'integer', 'min:1'],
    ];
  }
}
