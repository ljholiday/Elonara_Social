<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\AuthService;
use App\Services\UserService;
use App\Services\ValidatorService;
use App\Services\SecurityService;

final class ProfileController
{
    public function __construct(
        private AuthService $auth,
        private UserService $users,
        private ValidatorService $validator,
        private SecurityService $security
    ) {
    }

    /**
     * Show current user's profile (redirects to their username)
     *
     * @return array{redirect?: string, error?: string}
     */
    public function showOwn(): array
    {
        $currentUser = $this->auth->getCurrentUser();

        if (!$currentUser || empty($currentUser->username)) {
            return ['error' => 'Please log in to view your profile.'];
        }

        return ['redirect' => '/profile/' . urlencode($currentUser->username)];
    }

    /**
     * Show user profile by username
     *
     * @return array{
     *   user: array<string, mixed>|null,
     *   is_own_profile: bool,
     *   stats: array<string, int>,
     *   recent_activity: array<int, array<string, mixed>>,
     *   error?: string
     * }
     */
    public function show(string $username): array
    {
        $user = $this->users->getByUsername($username);

        if ($user === null) {
            return [
                'user' => null,
                'is_own_profile' => false,
                'stats' => ['conversations' => 0, 'replies' => 0, 'communities' => 0],
                'recent_activity' => [],
                'error' => 'User not found.',
            ];
        }

        $currentUser = $this->auth->getCurrentUser();
        $isOwnProfile = $currentUser && (int)$currentUser->id === (int)$user['id'];

        $stats = $this->users->getStats((int)$user['id']);
        $recentActivity = $this->users->getRecentActivity((int)$user['id'], 10);

        return [
            'user' => $user,
            'is_own_profile' => $isOwnProfile,
            'stats' => $stats,
            'recent_activity' => $recentActivity,
        ];
    }

    /**
     * Show profile edit form
     *
     * @return array{
     *   user: array<string, mixed>|null,
     *   errors: array<string, string>,
     *   input: array<string, string>,
     *   error?: string
     * }
     */
    public function edit(): array
    {
        $currentUserId = $this->auth->currentUserId() ?? 0;

        if ($currentUserId <= 0) {
            return [
                'user' => null,
                'errors' => [],
                'input' => [],
                'error' => 'Please log in to edit your profile.',
            ];
        }

        $user = $this->users->getById($currentUserId);

        if ($user === null) {
            return [
                'user' => null,
                'errors' => [],
                'input' => [],
                'error' => 'User not found.',
            ];
        }

        return [
            'user' => $user,
            'errors' => [],
            'input' => [
                'display_name' => (string)($user['display_name'] ?? ''),
                'bio' => (string)($user['bio'] ?? ''),
            ],
        ];
    }

    /**
     * Update profile
     *
     * @return array{
     *   redirect?: string,
     *   user?: array<string, mixed>,
     *   errors?: array<string, string>,
     *   input?: array<string, string>,
     *   error?: string
     * }
     */
    public function update(Request $request): array
    {
        try {
            $currentUserId = $this->auth->currentUserId() ?? 0;

            if ($currentUserId <= 0) {
                return ['error' => 'Please log in to update your profile.'];
            }

            $user = $this->users->getById($currentUserId);
            if ($user === null) {
                return ['error' => 'User not found.'];
            }

            // Verify CSRF token
            $nonce = (string)$request->input('profile_nonce', '');
            $logFile = dirname(__DIR__, 3) . '/debug.log';
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "ProfileController nonce verification: nonce={$nonce}, action=vt_profile_update, userId={$currentUserId}\n", FILE_APPEND);

            if (!$this->security->verifyNonce($nonce, 'vt_profile_update', $currentUserId)) {
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Nonce verification FAILED\n", FILE_APPEND);
                return [
                    'user' => $user,
                    'errors' => ['nonce' => 'Security verification failed. Please refresh and try again.'],
                    'input' => [
                        'display_name' => (string)$request->input('display_name', ''),
                        'bio' => (string)$request->input('bio', ''),
                    ],
                ];
            }
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Nonce verification PASSED\n", FILE_APPEND);

            // Validate inputs
            $displayNameValidation = $this->validator->textField($request->input('display_name', ''), 2, 100);
            $bioValidation = $this->validator->textField($request->input('bio', ''), 0, 500);

            $errors = [];
            $input = [
                'display_name' => $displayNameValidation['value'],
                'bio' => $bioValidation['value'],
                'avatar_alt' => (string)$request->input('avatar_alt', ''),
                'cover_alt' => (string)$request->input('cover_alt', ''),
            ];

            if (!$displayNameValidation['is_valid']) {
                $errors['display_name'] = $displayNameValidation['errors'][0] ?? 'Display name must be between 2 and 100 characters.';
            }

            if (!$bioValidation['is_valid']) {
                $errors['bio'] = $bioValidation['errors'][0] ?? 'Bio must be 500 characters or less.';
            }

            // Check for avatar upload
            $hasAvatar = !empty($_FILES['avatar']) && !empty($_FILES['avatar']['tmp_name']);

            // Check for upload errors
            if (!empty($_FILES['avatar']['error'])) {
                $uploadError = $_FILES['avatar']['error'];
                if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                    $errors['avatar'] = 'File is too large. Maximum size is 10MB.';
                } elseif ($uploadError === UPLOAD_ERR_NO_FILE) {
                    // No file uploaded, which is fine
                } else {
                    $errors['avatar'] = 'File upload failed. Please try again.';
                }
            }

            if ($hasAvatar) {
                $avatarAlt = trim($input['avatar_alt']);
                if ($avatarAlt === '') {
                    $errors['avatar_alt'] = 'Avatar description is required for accessibility.';
                }
            }

            // Check for cover image upload
            $hasCover = !empty($_FILES['cover']) && !empty($_FILES['cover']['tmp_name']);

            // Check for cover upload errors
            if (!empty($_FILES['cover']['error'])) {
                $uploadError = $_FILES['cover']['error'];
                if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                    $errors['cover'] = 'File is too large. Maximum size is 10MB.';
                } elseif ($uploadError === UPLOAD_ERR_NO_FILE) {
                    // No file uploaded, which is fine
                } else {
                    $errors['cover'] = 'File upload failed. Please try again.';
                }
            }

            if ($hasCover) {
                $coverAlt = trim($input['cover_alt']);
                if ($coverAlt === '') {
                    $errors['cover_alt'] = 'Cover image description is required for accessibility.';
                }
            }

            if ($errors) {
                return [
                    'user' => $user,
                    'errors' => $errors,
                    'input' => $input,
                ];
            }

            // Update profile
            $updateData = [
                'display_name' => $input['display_name'],
                'bio' => $input['bio'],
            ];

            if ($hasAvatar) {
                $updateData['avatar'] = $_FILES['avatar'];
                $updateData['avatar_alt'] = $input['avatar_alt'];
            }

            if ($hasCover) {
                $updateData['cover'] = $_FILES['cover'];
                $updateData['cover_alt'] = $input['cover_alt'];
            }

            $this->users->updateProfile($currentUserId, $updateData);

            // Redirect to profile page
            $updatedUser = $this->users->getById($currentUserId);
            $username = $updatedUser['username'] ?? 'user';

            return ['redirect' => '/profile/' . urlencode($username) . '?updated=1'];
        } catch (\Throwable $e) {
            file_put_contents(dirname(__DIR__, 3) . '/debug.log', date('[Y-m-d H:i:s] ') . "ProfileController::update exception: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            return [
                'user' => $user ?? null,
                'errors' => ['general' => $e->getMessage()],
                'input' => $input ?? ['display_name' => '', 'bio' => '', 'avatar_alt' => ''],
            ];
        }
    }
}
