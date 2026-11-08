<?php

namespace App\Http\Requests\Settings;

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...RegisteredUserController::profileRules(),
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
        ];
    }

    public function messages(): array
    {
        return RegisteredUserController::profileMessages();
    }

    protected function passedValidation(): void
    {
        $this->merge([
            'phone_e164' => preg_replace('/\s+/', '', $this->input('phone_e164')),
            'tiktok_username' => User::normalizeTiktok($this->input('tiktok_username')),
            'name' => trim($this->input('first_name').' '.$this->input('last_name')),
        ]);
    }
}
