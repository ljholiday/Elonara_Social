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
     * Show user profile by username or numeric ID
     *
     * Design decision: System-generated URLs use numeric IDs (stable, never break on username changes).
     * Username support exists as a courtesy for human-typed URLs. Canonical format is /profile/{id}.
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
        // Support both numeric IDs (canonical) and usernames (convenience for manual URLs)
        if (ctype_digit($username)) {
            $user = $this->users->getById((int)$username);
        } else {
            $user = $this->users->getByUsername($username);
        }

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
        $currentUserId = (int)($this->auth->currentUserId() ?? 0);

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
     *   error?: string,
     *   success?: bool,
     *   json?: bool
     * }
     */
    public function update(Request $request): array
    {
        try {
            $currentUserId = (int)($this->auth->currentUserId() ?? 0);
            $isAjax = $request->expectsJson();

            if ($currentUserId <= 0) {
                $error = ['error' => 'Please log in to update your profile.'];
                if ($isAjax) {
                    $error['json'] = true;
                }
                return $error;
            }

            $user = $this->users->getById($currentUserId);
            if ($user === null) {
                $error = ['error' => 'User not found.'];
                if ($isAjax) {
                    $error['json'] = true;
                }
                return $error;
            }

            // Verify CSRF token
            $nonce = (string)$request->input('profile_nonce', '');

            if (!$this->security->verifyNonce($nonce, 'app_profile_update', $currentUserId)) {
                $response = [
                    'user' => $user,
                    'errors' => ['nonce' => 'Security verification failed. Please refresh and try again.'],
                    'input' => [
                        'display_name' => (string)$request->input('display_name', ''),
                        'bio' => (string)$request->input('bio', ''),
                    ],
                ];
                if ($isAjax) {
                    $response['json'] = true;
                }
                return $response;
            }

            // Validate inputs
            $displayNameValidation = $this->validator->textField($request->input('display_name', ''), 2, 100);
            $bioValidation = $this->validator->textField($request->input('bio', ''), 0, 500);

            $errors = [];
            $input = [
                'display_name' => $displayNameValidation['value'],
                'bio' => $bioValidation['value'],
                'avatar_alt' => (string)$request->input('avatar_alt', ''),
                'cover_alt' => (string)$request->input('cover_alt', ''),
                'avatar_preference' => (string)$request->input('avatar_preference', 'auto'),
            ];

            if (!$displayNameValidation['is_valid']) {
                $errors['display_name'] = $displayNameValidation['errors'][0] ?? 'Display name must be between 2 and 100 characters.';
            }

            if (!$bioValidation['is_valid']) {
                $errors['bio'] = $bioValidation['errors'][0] ?? 'Bio must be 500 characters or less.';
            }

            // Validate avatar preference
            $validPreferences = ['auto', 'custom', 'gravatar'];
            if (!in_array($input['avatar_preference'], $validPreferences, true)) {
                $input['avatar_preference'] = 'auto';
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
                $response = [
                    'user' => $user,
                    'errors' => $errors,
                    'input' => $input,
                ];
                if ($isAjax) {
                    $response['json'] = true;
                }
                return $response;
            }

            // Update profile
            $updateData = [
                'display_name' => $input['display_name'],
                'bio' => $input['bio'],
                'avatar_preference' => $input['avatar_preference'],
            ];

            // Check for uploaded avatar URL from modal
            $avatarUrlUploaded = (string)$request->input('avatar_url_uploaded', '');
            if ($avatarUrlUploaded && $input['avatar_alt']) {
                // Use the already-uploaded image
                $updateData['avatar_url'] = $avatarUrlUploaded;
            } elseif ($hasAvatar) {
                // Traditional file upload
                $updateData['avatar'] = $_FILES['avatar'];
                $updateData['avatar_alt'] = $input['avatar_alt'];
            }

            // Check for uploaded cover URL from modal
            $coverUrlUploaded = (string)$request->input('cover_url_uploaded', '');
            if ($coverUrlUploaded && $input['cover_alt']) {
                // Use the already-uploaded image
                $updateData['cover_url'] = $coverUrlUploaded;
            } elseif ($hasCover) {
                // Traditional file upload
                $updateData['cover'] = $_FILES['cover'];
                $updateData['cover_alt'] = $input['cover_alt'];
            }

            $this->users->updateProfile($currentUserId, $updateData);

            // Get updated user data
            $updatedUser = $this->users->getById($currentUserId);
            $username = $updatedUser['username'] ?? 'user';

            // Return JSON response for AJAX or redirect for traditional form
            if ($isAjax) {
                return [
                    'success' => true,
                    'json' => true,
                    'user' => $updatedUser,
                    'message' => 'Profile updated successfully.',
                ];
            }

            return ['redirect' => '/profile/' . urlencode($username) . '?updated=1'];
        } catch (\Throwable $e) {
            // Log error for debugging but don't expose details to user
            error_log("ProfileController::update exception: " . $e->getMessage());
            $response = [
                'user' => $user ?? null,
                'errors' => ['general' => 'An error occurred while updating your profile. Please try again.'],
                'input' => $input ?? ['display_name' => '', 'bio' => '', 'avatar_alt' => ''],
            ];
            if ($isAjax) {
                $response['json'] = true;
            }
            return $response;
        }
    }
}
