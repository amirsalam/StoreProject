<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public form
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            // Honeypot: a real person never sees this field, so anything
            // in it means a bot filled the form in.
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'website.prohibited' => 'This submission looks automated.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function contactMessageAttributes(): array
    {
        return [
            'user_id' => $this->user()?->id,
            'name' => $this->validated('name'),
            'email' => $this->validated('email'),
            'subject' => $this->validated('subject'),
            'message' => $this->validated('message'),
        ];
    }
}
