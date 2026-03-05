<?php

namespace App\Http\Requests;

use App\Enums\ClientType;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    $userId = $this->route('client')?->id;

    return [
      // User fields
      'name'  => ['required', 'string', 'max:255'],
      'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($userId)],
      'phone' => ['nullable', 'string', 'max:30'],

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
