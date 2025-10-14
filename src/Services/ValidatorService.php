<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Validator Service
 *
 * Validates and sanitizes input according to doctrine standards.
 * All validators return ['value', 'is_valid', 'errors'] format.
 * Use ONLY in templates/controllers - managers receive pre-validated data.
 */
final class ValidatorService
{
    public function __construct(private SanitizerService $sanitizer)
    {
    }

    /**
     * Validate and sanitize email
     *
     * @return array{value: string, is_valid: bool, errors: array<string>}
     */
    public function email(string $email): array
    {
        $sanitized = $this->sanitizer->email($email);
        $isValid = filter_var($sanitized, FILTER_VALIDATE_EMAIL) !== false;

        return [
            'value' => $sanitized,
            'is_valid' => $isValid,
            'errors' => $isValid ? [] : ['Invalid email format'],
        ];
    }

    /**
     * Validate and sanitize URL
     *
     * @return array{value: string, is_valid: bool, errors: array<string>}
     */
    public function url(string $url): array
    {
        $sanitized = $this->sanitizer->url($url);
        $isValid = filter_var($sanitized, FILTER_VALIDATE_URL) !== false;

        return [
            'value' => $sanitized,
            'is_valid' => $isValid,
            'errors' => $isValid ? [] : ['Invalid URL format'],
        ];
    }

    /**
     * Validate text field with length constraints
     *
     * @return array{value: string, is_valid: bool, errors: array<string>}
     */
    public function textField(string $input, int $minLength = 0, int $maxLength = 255): array
    {
        $sanitized = $this->sanitizer->textField($input);
        $length = strlen($sanitized);
        $errors = [];

        if ($length < $minLength) {
            $errors[] = "Text must be at least {$minLength} characters long";
        }

        if ($length > $maxLength) {
            $errors[] = "Text cannot exceed {$maxLength} characters";
        }

        return [
            'value' => $sanitized,
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate textarea with length constraints
     *
     * @return array{value: string, is_valid: bool, errors: array<string>}
     */
    public function textarea(string $input, int $maxLength = 5000): array
    {
        $sanitized = $this->sanitizer->textarea($input);
        $length = strlen($sanitized);
        $errors = [];

        if ($length > $maxLength) {
            $errors[] = "Text cannot exceed {$maxLength} characters";
        }

        return [
            'value' => $sanitized,
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate rich text content
     *
     * @return array{value: string, is_valid: bool, errors: array<string>}
     */
    public function richText(string $input, int $maxLength = 10000): array
    {
        $sanitized = $this->sanitizer->richText($input);
        $textLength = strlen(strip_tags($sanitized));
        $errors = [];

        if ($textLength > $maxLength) {
            $errors[] = "Content cannot exceed {$maxLength} characters";
        }

        return [
            'value' => $sanitized,
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate integer with range
     *
     * @return array{value: int, is_valid: bool, errors: array<string>}
     */
    public function integer($input, ?int $min = null, ?int $max = null): array
    {
        $sanitized = $this->sanitizer->integer($input);
        $errors = [];

        if ($min !== null && $sanitized < $min) {
            $errors[] = "Value must be at least {$min}";
        }

        if ($max !== null && $sanitized > $max) {
            $errors[] = "Value cannot exceed {$max}";
        }

        return [
            'value' => $sanitized,
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate float with range
     *
     * @return array{value: float, is_valid: bool, errors: array<string>}
     */
    public function float($input, ?float $min = null, ?float $max = null): array
    {
        $sanitized = $this->sanitizer->float($input);
        $errors = [];

        if ($min !== null && $sanitized < $min) {
            $errors[] = "Value must be at least {$min}";
        }

        if ($max !== null && $sanitized > $max) {
            $errors[] = "Value cannot exceed {$max}";
        }

        return [
            'value' => $sanitized,
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate required field
     *
     * @return array{value: string, is_valid: bool, errors: array<string>}
     */
    public function required(string $input, string $fieldName = 'Field'): array
    {
        $sanitized = trim($input);
        $isEmpty = empty($sanitized);

        return [
            'value' => $sanitized,
            'is_valid' => !$isEmpty,
            'errors' => $isEmpty ? ["{$fieldName} is required"] : [],
        ];
    }

    /**
     * Validate slug format
     *
     * @return array{value: string, is_valid: bool, errors: array<string>}
     */
    public function slug(string $input): array
    {
        $sanitized = $this->sanitizer->slug($input);
        $isValid = !empty($sanitized) && preg_match('/^[a-z0-9-]+$/', $sanitized);

        return [
            'value' => $sanitized,
            'is_valid' => $isValid,
            'errors' => $isValid ? [] : ['Invalid slug format (use lowercase letters, numbers, and dashes only)'],
        ];
    }

    /**
     * Validate phone number
     *
     * @return array{value: string, is_valid: bool, errors: array<string>}
     */
    public function phoneNumber(string $input): array
    {
        $sanitized = $this->sanitizer->phoneNumber($input);
        $isValid = preg_match('/^[\+]?[0-9\-\(\)\s]{7,20}$/', $sanitized);

        return [
            'value' => $sanitized,
            'is_valid' => $isValid,
            'errors' => $isValid ? [] : ['Invalid phone number format'],
        ];
    }

    /**
     * Validate password strength
     *
     * @return array{value: string, is_valid: bool, errors: array<string>}
     */
    public function password(string $password, int $minLength = 8): array
    {
        $errors = [];

        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters long";
        }

        return [
            'value' => $password, // Don't sanitize passwords
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate password with complexity requirements
     *
     * @return array{value: string, is_valid: bool, errors: array<string>}
     */
    public function passwordStrict(string $password, int $minLength = 8): array
    {
        $errors = [];

        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters long";
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }

        return [
            'value' => $password, // Don't sanitize passwords
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate username format
     *
     * @return array{value: string, is_valid: bool, errors: array<string>}
     */
    public function username(string $input, int $minLength = 3, int $maxLength = 30): array
    {
        $sanitized = $this->sanitizer->textField($input);
        $errors = [];

        if (strlen($sanitized) < $minLength) {
            $errors[] = "Username must be at least {$minLength} characters long";
        }

        if (strlen($sanitized) > $maxLength) {
            $errors[] = "Username cannot exceed {$maxLength} characters";
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $sanitized)) {
            $errors[] = "Username can only contain letters, numbers, underscores, and dashes";
        }

        return [
            'value' => $sanitized,
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate array of inputs
     *
     * @param array<string,array{method:string,params?:array<mixed>}> $rules
     * @param array<string,mixed> $input
     * @return array{fields: array<string,array{value:mixed,is_valid:bool,errors:array<string>}>, is_valid: bool, errors: array<string,array<string>>}
     */
    public function validateArray(array $rules, array $input): array
    {
        $results = [];
        $allValid = true;

        foreach ($rules as $field => $rule) {
            $value = $input[$field] ?? '';
            $method = $rule['method'] ?? 'textField';
            $params = $rule['params'] ?? [];

            $result = $this->{$method}($value, ...$params);
            $results[$field] = $result;

            if (!$result['is_valid']) {
                $allValid = false;
            }
        }

        return [
            'fields' => $results,
            'is_valid' => $allValid,
            'errors' => $this->collectErrors($results),
        ];
    }

    /**
     * Quick escaping methods (for output in templates)
     */
    public function escHtml(string $text): string
    {
        return $this->sanitizer->escapeHtml($text);
    }

    public function escAttr(string $text): string
    {
        return $this->sanitizer->escapeAttribute($text);
    }

    public function escUrl(string $url): string
    {
        return $this->sanitizer->escapeUrl($url);
    }

    /**
     * Validate file upload
     *
     * @param array<string,mixed> $file
     * @param array<string> $allowedTypes
     * @return array{is_valid: bool, errors: array<string>, filename?: string, size?: int, type?: string}
     */
    public function fileUpload(array $file, array $allowedTypes = [], int $maxSize = 5242880): array
    {
        $errors = [];

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'No valid file uploaded';
            return ['is_valid' => false, 'errors' => $errors];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error: ' . $this->getUploadErrorMessage($file['error']);
        }

        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size of ' . ($maxSize / 1024 / 1024) . 'MB';
        }

        if (!empty($allowedTypes)) {
            $fileType = mime_content_type($file['tmp_name']);
            if (!in_array($fileType, $allowedTypes, true)) {
                $errors[] = 'File type not allowed';
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'filename' => $this->sanitizer->filename($file['name'] ?? ''),
            'size' => $file['size'] ?? 0,
            'type' => $file['type'] ?? '',
        ];
    }

    /**
     * Collect all errors from validation results
     *
     * @param array<string,array{is_valid:bool,errors:array<string>}> $results
     * @return array<string,array<string>>
     */
    private function collectErrors(array $results): array
    {
        $errors = [];
        foreach ($results as $field => $result) {
            if (!$result['is_valid']) {
                $errors[$field] = $result['errors'];
            }
        }
        return $errors;
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'File too large (php.ini limit)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'No temporary directory',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension',
            default => 'Unknown upload error',
        };
    }
}
