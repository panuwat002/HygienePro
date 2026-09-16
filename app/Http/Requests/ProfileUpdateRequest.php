<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // The email address is a login identifier (LoginRequest authenticates on
            // email or employee_code), so changing it is an account-takeover primitive:
            // anyone with a briefly unattended session could repoint the account and
            // then drive the password-reset flow without ever knowing the password.
            // Only required when the address actually changes, so ordinary name edits
            // stay frictionless. ProfileController::destroy already works this way.
            'current_password' => [
                Rule::requiredIf(fn () => $this->emailIsChanging()),
                'current_password',
            ],
        ];
    }

    private function emailIsChanging(): bool
    {
        $submitted = $this->input('email');

        if (! is_string($submitted)) {
            return false;
        }

        return strcasecmp(trim($submitted), (string) $this->user()->email) !== 0;
    }
}
