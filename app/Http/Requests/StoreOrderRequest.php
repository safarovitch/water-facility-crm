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
      // user_id is optional when the admin provides a new_contact block —
      // we'll resolve or create the user in the controller. Phone is the
      // primary identifier; email is optional.
      'user_id'                 => ['nullable', 'required_without:new_contact.phone', 'exists:users,id'],
      'scheduled_delivery_at'   => ['nullable', 'date', 'after_or_equal:today'],
      'delivery_address'        => ['nullable', 'string'],
      'notes'                   => ['nullable', 'string'],
      'custom_total'            => ['nullable', 'numeric', 'min:0'],

      'items'                   => ['required', 'array', 'min:1'],
      'items.*.product_id'      => ['required', 'exists:products,id'],
      'items.*.quantity'        => ['required', 'integer', 'min:1'],
      'items.*.unit_price'      => ['required', 'numeric', 'min:0'],
      'items.*.is_gift'         => ['nullable', 'boolean'],

      'new_contact'             => ['nullable', 'array'],
      'new_contact.name'        => ['required_with:new_contact.phone', 'string', 'max:255'],
      'new_contact.phone'       => ['nullable', 'string', 'max:32'],
      'new_contact.email'       => ['nullable', 'email', 'max:255'],
    ];
  }
}
