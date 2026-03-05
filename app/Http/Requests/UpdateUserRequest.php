<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
  /**
   * Determine if the user is authorized to make this request.
   */
  public function authorize(): bool
  {
    return true;
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
   */
  public function rules(): array
  {
    return [
      'name' => ['required', 'string', 'max:255'],
      'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $this->user->id],
      'phone' => ['required', 'string', 'max:255', 'unique:users,phone,' . $this->user->id],
      'password' => ['nullable', 'confirmed', 'string', 'min:8'],
      'roles' => ['sometimes', 'array'],
      'roles.*' => ['string', 'exists:roles,name'],
      'avatar' => ['nullable', 'file', 'mimes:jpeg,png,webp', 'min:8'],
      'avatar_remove' => ['nullable', 'in:0,1'],
      'status' => ['required', 'string'],
      'sip_extension' => ['nullable', 'string', 'max:255'],
      'sip_password' => ['nullable', 'string', 'max:255'],
    ];
  }
}
