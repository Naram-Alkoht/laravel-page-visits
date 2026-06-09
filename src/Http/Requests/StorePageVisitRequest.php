<?php

declare(strict_types=1);

namespace Naram\PageVisits\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Naram\PageVisits\Services\PageVisitSigner;

final class StorePageVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'page_key' => ['required', 'string', 'max:255'],
            'signature' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $signatureIsValid = resolve(PageVisitSigner::class)->verify(
                $this->string('page_key')->toString(),
                $this->string('signature')->toString(),
            );

            if (! $signatureIsValid) {
                $validator->errors()->add('signature', 'The page visit signature is invalid.');
            }
        });
    }
}
