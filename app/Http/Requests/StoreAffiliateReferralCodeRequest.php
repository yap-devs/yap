<?php

namespace App\Http\Requests;

use App\Models\AffiliateReferralCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAffiliateReferralCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'min:4',
                'max:24',
                'regex:/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\z/',
                Rule::notIn(config('affiliate.reserved_referral_codes')),
                Rule::unique(AffiliateReferralCode::class, 'code'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.min' => __('messages.affiliate.code_validation.length'),
            'code.max' => __('messages.affiliate.code_validation.length'),
            'code.regex' => __('messages.affiliate.code_validation.format'),
            'code.not_in' => __('messages.affiliate.code_validation.reserved'),
            'code.unique' => __('messages.affiliate.code_validation.taken'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::lower(trim((string) $this->input('code'))),
        ]);
    }
}
