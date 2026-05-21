<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'user_id'               => ['required', 'exists:users,id'],
      'scheduled_delivery_at' => ['nullable', 'date'],
      'delivery_address'      => ['nullable', 'string'],
      'notes'            => ['nullable', 'string'],
      'custom_total'     => ['nullable', 'numeric', 'min:0'],

      'items'              => ['required', 'array', 'min:1'],
      'items.*.product_id' => ['required', 'exists:products,id'],
      'items.*.quantity'   => ['required', 'integer', 'min:1'],
      'items.*.is_gift'    => ['nullable', 'boolean'],
    ];
  }
}
