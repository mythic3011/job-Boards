<?php

namespace App\Rules;

use App\Services\CvFileService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCvFile implements ValidationRule
{
    public function __construct(
        private readonly CvFileService $cvFileService
    ) {
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof \Illuminate\Http\UploadedFile) {
            $fail('The :attribute must be a valid file.');
            return;
        }

        try {
            $validation = $this->cvFileService->validateFile($value);

            if (!$validation['valid']) {
                $fail($validation['error'] ?? 'The :attribute is invalid.');
            }
        } catch (\Throwable $e) {
            // Log unexpected errors but don't expose them to users
            \Illuminate\Support\Facades\Log::error('CV file validation error', [
                'attribute' => $attribute,
                'error' => $e->getMessage(),
            ]);

            $fail('An error occurred while validating the file. Please try again.');
        }
    }
}
