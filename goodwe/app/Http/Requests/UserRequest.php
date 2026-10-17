<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.($userId ? $userId->id : null),
            'phone' => 'required|string|regex:/^[0-9+\-\s()]+$/|min:10|max:20',
            'password' => 'required|string|min:6',
            'password_confirmation' => 'required|string|same:password',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('validation.name_required'),
            'email.required' => __('validation.email_required'),
            'email.email' => __('validation.email_invalid'),
            'email.unique' => __('validation.email_unique'),
            'phone.required' => __('validation.phone_required'),
            'phone.regex' => __('validation.phone_invalid'),
            'phone.min' => __('validation.phone_min'),
            'password.required' => __('validation.password_required'),
            'password.min' => __('validation.password_min'),
            'password_confirmation.required' => __('validation.confirm_required'),
            'password_confirmation.same' => __('validation.confirm_same'),
        ];
    }
}
