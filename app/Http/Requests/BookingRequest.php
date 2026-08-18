<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bicycle_id' => [
                'required',
                Rule::exists('bicycles', 'id')->where('user_id', $this->user()->id),
            ],
            'bicycle_parts' => ['required', 'array', 'min:1'],
            'bicycle_parts.*' => ['integer', 'exists:bicycle_parts,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bicycle_id.required' => 'Please choose which bicycle this is for.',
            'bicycle_id.exists' => 'Please choose one of your registered bicycles.',
            'bicycle_parts.required' => 'Select at least one area that needs attention.',
            'bicycle_parts.min' => 'Select at least one area that needs attention.',
            'appointment_date.after_or_equal' => 'Please choose a date from today onward.',
        ];
    }
}
