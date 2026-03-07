<?php

namespace App\Http\Requests;

use App\Enums\ClientType;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      // User fields
      'name'     => ['required', 'string', 'max:255'],
      'email'    => ['required', 'email'],
      'phones'   => ['nullable', 'array'],
      'phones.*.id' => ['nullable', 'integer'],
      'phones.*.label' => ['required', 'string', 'max:50'],
      'phones.*.phone' => ['required', 'string', 'max:30'],
      'phones.*.is_default' => ['nullable', 'boolean'],

      // Profile fields
      'type'         => ['required', new EnumValue(ClientType::class)],
      'company_name' => ['nullable', 'string', 'max:255', 'required_if:type,' . ClientType::Company],
      'region'       => ['nullable', 'string', 'max:255'],
      'address'      => ['nullable', 'string'],
      'notes'        => ['nullable', 'string'],
      'credit_limit' => ['nullable', 'numeric', 'min:0'],
    ];
  }
}
