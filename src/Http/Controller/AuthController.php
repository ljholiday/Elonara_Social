<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\AuthService;
use App\Services\ValidatorService;

final class AuthController
{
    public function __construct(
        private AuthService $auth,
        private ValidatorService $validator
    ) {
    }

    public function landing(): array
    {
        $redirect = $this->sanitizeRedirect($this->request()->query('redirect_to'));

        return $this->buildView(
            loginInput: ['redirect_to' => $redirect],
            registerInput: ['redirect_to' => $redirect]
        );
    }

    /**
     * @return array{redirect?: string, active?: string, login?: array<string,mixed>, register?: array<string,mixed>}
     */
    public function login(): array
    {
        $request = $this->request();
        $identifierRaw = (string)$request->input('identifier', '');
        $passwordRaw = (string)$request->input('password', '');
        $redirect = $this->sanitizeRedirect($request->input('redirect_to'));
        $remember = (string)$request->input('remember', '') === '1';

        // Validate inputs
        $identifierValidation = $this->validator->required($identifierRaw, 'Email or username');
        $passwordValidation = $this->validator->required($passwordRaw, 'Password');

        $errors = [];
        if (!$identifierValidation['is_valid']) {
            $errors['identifier'] = $identifierValidation['errors'][0] ?? 'Email or username is required.';
        }
        if (!$passwordValidation['is_valid']) {
            $errors['password'] = $passwordValidation['errors'][0] ?? 'Password is required.';
        }

        if ($errors === []) {
            $result = $this->auth->attemptLogin($identifierValidation['value'], $passwordRaw);
            if ($result['success']) {
                return [
                    'redirect' => $redirect !== '' ? $redirect : '/',
                ];
            }
            $errors = $result['errors'] ?? ['credentials' => 'Unable to sign in with those details.'];
        }

        return $this->buildView(
            loginInput: [
                'identifier' => $identifierValidation['value'] ?? $identifierRaw,
                'remember' => $remember,
                'redirect_to' => $redirect,
            ],
            loginErrors: $errors,
            registerInput: ['redirect_to' => $redirect],
            active: 'login'
        );
    }

    /**
     * @return array{redirect?: string, active?: string, login?: array<string,mixed>, register?: array<string,mixed>}
     */
    public function register(): array
    {
        $request = $this->request();
        $displayNameRaw = (string)$request->input('display_name', '');
        $usernameRaw = (string)$request->input('username', '');
        $emailRaw = (string)$request->input('email', '');
        $passwordRaw = (string)$request->input('password', '');
        $confirmRaw = (string)$request->input('confirm_password', '');
        $redirect = $this->sanitizeRedirect($request->input('redirect_to'));

        // Validate inputs
        $displayNameValidation = $this->validator->textField($displayNameRaw, 1, 100);
        $usernameValidation = $this->validator->username($usernameRaw);
        $emailValidation = $this->validator->email($emailRaw);
        $passwordValidation = $this->validator->password($passwordRaw);

        $errors = [];
        if (!$displayNameValidation['is_valid']) {
            $errors['display_name'] = $displayNameValidation['errors'][0] ?? 'Display name is required.';
        }
        if (!$usernameValidation['is_valid']) {
            $errors['username'] = $usernameValidation['errors'][0] ?? 'Username is invalid.';
        }
        if (!$emailValidation['is_valid']) {
            $errors['email'] = $emailValidation['errors'][0] ?? 'Email is invalid.';
        }
        if (!$passwordValidation['is_valid']) {
            $errors['password'] = $passwordValidation['errors'][0] ?? 'Password is required.';
        } elseif ($passwordRaw !== $confirmRaw) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if ($errors === []) {
            $result = $this->auth->register([
                'display_name' => $displayNameValidation['value'],
                'username' => $usernameValidation['value'],
                'email' => $emailValidation['value'],
                'password' => $passwordRaw,
            ]);

            if ($result['success']) {
                $this->auth->attemptLogin($emailValidation['value'], $passwordRaw);
                return [
                    'redirect' => $redirect !== '' ? $redirect : '/',
                ];
            }

            $errors = $result['errors'];
        }

        return $this->buildView(
            loginInput: ['redirect_to' => $redirect],
            registerInput: [
                'display_name' => $displayNameValidation['value'] ?? $displayNameRaw,
                'username' => $usernameValidation['value'] ?? $usernameRaw,
                'email' => $emailValidation['value'] ?? $emailRaw,
                'redirect_to' => $redirect,
            ],
            registerErrors: $errors,
            active: 'register'
        );
    }

    /**
     * @return array{redirect: string}
     */
    public function logout(): array
    {
        $this->auth->logout();
        return [
            'redirect' => '/auth',
        ];
    }

    /**
     * @return array{errors: array<string,string>, input: array<string,string>}
     */
    public function requestReset(): array
    {
        return [
            'errors' => [],
            'input' => ['email' => ''],
        ];
    }

    /**
     * @return array{errors?: array<string,string>, message?: string, input?: array<string,string>}
     */
    public function sendResetEmail(): array
    {
        $request = $this->request();
        $emailRaw = (string)$request->input('email', '');

        $emailValidation = $this->validator->email($emailRaw);

        if (!$emailValidation['is_valid']) {
            return [
                'errors' => ['email' => $emailValidation['errors'][0] ?? 'Invalid email format.'],
                'input' => ['email' => $emailRaw],
            ];
        }

        $result = $this->auth->requestPasswordReset($emailValidation['value']);

        if ($result['success']) {
            return [
                'message' => $result['message'] ?? 'If that email exists, a reset link has been sent.',
            ];
        }

        return [
            'errors' => $result['errors'] ?? ['email' => 'An error occurred.'],
            'input' => ['email' => $emailValidation['value']],
        ];
    }

    /**
     * @return array{valid: bool, token: string, error?: string}
     */
    public function showResetForm(string $token): array
    {
        $validation = $this->auth->validateResetToken($token);

        return [
            'valid' => $validation['valid'],
            'token' => $token,
            'error' => $validation['error'] ?? null,
        ];
    }

    /**
     * @return array{redirect?: string, errors?: array<string,string>, message?: string, token?: string}
     */
    public function processReset(string $token): array
    {
        $request = $this->request();
        $passwordRaw = (string)$request->input('password', '');
        $confirmRaw = (string)$request->input('confirm_password', '');

        $passwordValidation = $this->validator->password($passwordRaw);

        $errors = [];
        if (!$passwordValidation['is_valid']) {
            $errors['password'] = $passwordValidation['errors'][0] ?? 'Password is required.';
        } elseif ($passwordRaw !== $confirmRaw) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if ($errors !== []) {
            return [
                'errors' => $errors,
                'token' => $token,
            ];
        }

        $result = $this->auth->resetPasswordWithToken($token, $passwordRaw);

        if ($result['success']) {
            return [
                'redirect' => '/auth',
                'message' => $result['message'] ?? 'Password reset successfully.',
            ];
        }

        return [
            'errors' => $result['errors'] ?? ['token' => 'An error occurred.'],
            'token' => $token,
        ];
    }

    /**
     * @return array{success: bool, message?: string, errors?: array<string,string>, redirect?: string}
     */
    public function verifyEmail(string $token): array
    {
        $result = $this->auth->verifyEmail($token);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => $result['message'] ?? 'Email verified successfully.',
                'redirect' => '/',
            ];
        }

        return [
            'success' => false,
            'errors' => $result['errors'] ?? ['token' => 'Verification failed.'],
        ];
    }

    private function request(): Request
    {
        /** @var Request $request */
        $request = vt_service('http.request');
        return $request;
    }

    private function sanitizeRedirect($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $trimmed = trim($value);
        if ($trimmed === '' || str_starts_with($trimmed, '//')) {
            return '';
        }

        if (preg_match('#^https?://#i', $trimmed)) {
            return '';
        }

        return str_starts_with($trimmed, '/') ? $trimmed : '';
    }

    /**
     * @param array<string,mixed> $loginInput
     * @param array<string,string> $loginErrors
     * @param array<string,mixed> $registerInput
     * @param array<string,string> $registerErrors
     * @return array{
     *   active: string,
     *   login: array{errors: array<string,string>, input: array<string,mixed>},
     *   register: array{errors: array<string,string>, input: array<string,mixed>}
     * }
     */
    private function buildView(
        array $loginInput = [],
        array $loginErrors = [],
        array $registerInput = [],
        array $registerErrors = [],
        string $active = 'login'
    ): array {
        return [
            'active' => $active,
            'login' => [
                'errors' => $loginErrors,
                'input' => array_merge([
                    'identifier' => '',
                    'remember' => false,
                    'redirect_to' => '',
                ], $loginInput),
            ],
            'register' => [
                'errors' => $registerErrors,
                'input' => array_merge([
                    'display_name' => '',
                    'username' => '',
                    'email' => '',
                    'redirect_to' => '',
                ], $registerInput),
            ],
        ];
    }
}
