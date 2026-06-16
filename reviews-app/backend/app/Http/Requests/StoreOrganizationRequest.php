<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'url',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $hasOrgPath   = preg_match('#^https?://(yandex\.ru|maps\.yandex\.ru|yandex\.com)/maps/.*org/[^/?#]*/?\d+#', $value);
                    $hasBusinessId = preg_match('#[?&]businessId=\d+#', $value);
                    if (!$hasOrgPath && !$hasBusinessId) {
                        $fail('The URL must be a valid Yandex Maps organization link (e.g. https://yandex.ru/maps/org/name/123456789/).');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'url.required' => 'Organization URL is required.',
            'url.url' => 'Please provide a valid URL.',
        ];
    }
}
