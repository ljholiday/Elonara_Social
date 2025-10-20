<?php
$appName = (string)app_config('app.name', 'Elonara Social');
$login = $login ?? ($view['login'] ?? ['errors' => [], 'input' => []]);
$register = $register ?? ($view['register'] ?? ['errors' => [], 'input' => []]);
$active = $active ?? ($view['active'] ?? 'login');
$flash = $flash ?? ($view['flash'] ?? []);

$loginInput = array_merge(['identifier' => '', 'remember' => false, 'redirect_to' => ''], $login['input'] ?? []);
$registerInput = array_merge([
    'display_name' => '',
    'username' => '',
    'email' => '',
    'redirect_to' => '',
], $register['input'] ?? []);
$usernameMinLength = (int)user_config('username_min_length', 2);
$usernameMaxLength = (int)user_config('username_max_length', 30);
$loginErrors = $login['errors'] ?? [];
$registerErrors = $register['errors'] ?? [];
?>
<section class="app-section app-auth">
  <div class="app-container">
    <?php if (!empty($flash['message'])): ?>
      <!-- Friendly success copy so brand-new members know what to do next -->
      <div class="app-alert app-alert-success app-mb-4">
        <p><?php echo e($flash['message']); ?></p>
      </div>
    <?php endif; ?>
    <div class="app-grid app-gap-4 app-grid-2">
      <div class="app-card<?php echo $active === 'login' ? ' is-active' : ''; ?>">
        <div class="app-card-header">
          <h1 class="app-heading">Sign in to <?php echo e($appName); ?></h1>
          <p class="app-text-muted">Access your dashboard, events, and conversations.</p>
        </div>
        <div class="app-card-body">
          <?php if (isset($loginErrors['credentials'])): ?>
            <div class="app-alert app-alert-error app-mb-4">
              <p><?php echo e($loginErrors['credentials']); ?></p>
            </div>
          <?php endif; ?>

          <form method="post" action="/auth/login" class="app-form app-stack">
            <div class="app-field">
              <label class="app-label" for="login-identifier">Email or Username</label>
              <input
                id="login-identifier"
                name="identifier"
                type="text"
                class="app-input<?php echo isset($loginErrors['identifier']) ? ' is-invalid' : ''; ?>"
                value="<?php echo e($loginInput['identifier']); ?>"
                autocomplete="username"
                required
              >
              <?php if (isset($loginErrors['identifier'])): ?>
                <p class="app-input-error"><?php echo e($loginErrors['identifier']); ?></p>
              <?php endif; ?>
            </div>

            <div class="app-field">
              <label class="app-label" for="login-password">Password</label>
              <input
                id="login-password"
                name="password"
                type="password"
                class="app-input<?php echo isset($loginErrors['password']) ? ' is-invalid' : ''; ?>"
                autocomplete="current-password"
                required
              >
              <?php if (isset($loginErrors['password'])): ?>
                <p class="app-input-error"><?php echo e($loginErrors['password']); ?></p>
              <?php endif; ?>
            </div>

            <div class="app-flex app-justify-between app-items-center">
              <label class="app-checkbox">
                <input type="checkbox" name="remember" value="1"<?php echo $loginInput['remember'] ? ' checked' : ''; ?>>
                <span>Remember me</span>
              </label>
              <a class="app-text-muted" href="/reset-password">Forgot password?</a>
            </div>

            <?php if ($loginInput['redirect_to'] !== ''): ?>
              <input type="hidden" name="redirect_to" value="<?php echo e($loginInput['redirect_to']); ?>">
            <?php endif; ?>

            <button type="submit" class="app-btn app-btn-primary app-btn-lg">Sign In</button>
          </form>
        </div>
      </div>

      <div class="app-card<?php echo $active === 'register' ? ' is-active' : ''; ?>">
        <div class="app-card-header">
          <h2 class="app-heading">Create an account</h2>
          <p class="app-text-muted">Plan events, RSVP, and stay connected with your communities.</p>
        </div>
        <div class="app-card-body">
          <form method="post" action="/auth/register" class="app-form app-stack">
            <div class="app-field">
              <label class="app-label" for="register-display-name">Display Name</label>
              <input
                id="register-display-name"
                name="display_name"
                type="text"
                class="app-input<?php echo isset($registerErrors['display_name']) ? ' is-invalid' : ''; ?>"
                value="<?php echo e($registerInput['display_name']); ?>"
                autocomplete="name"
                required
              >
              <?php if (isset($registerErrors['display_name'])): ?>
                <p class="app-input-error"><?php echo e($registerErrors['display_name']); ?></p>
              <?php endif; ?>
            </div>

            <div class="app-field">
              <label class="app-label" for="register-username">Username</label>
              <input
                id="register-username"
                name="username"
                type="text"
                class="app-input<?php echo isset($registerErrors['username']) ? ' is-invalid' : ''; ?>"
                value="<?php echo e($registerInput['username']); ?>"
                autocomplete="username"
                required
              >
              <p class="app-text-muted app-text-sm">
                Usernames must be <?= $usernameMinLength; ?>–<?= $usernameMaxLength; ?> characters and may include letters, numbers, underscores, and dashes.
              </p>
              <?php if (isset($registerErrors['username'])): ?>
                <p class="app-input-error"><?php echo e($registerErrors['username']); ?></p>
              <?php endif; ?>
            </div>

            <div class="app-field">
              <label class="app-label" for="register-email">Email</label>
              <input
                id="register-email"
                name="email"
                type="email"
                class="app-input<?php echo isset($registerErrors['email']) ? ' is-invalid' : ''; ?>"
                value="<?php echo e($registerInput['email']); ?>"
                autocomplete="email"
                required
              >
              <?php if (isset($registerErrors['email'])): ?>
                <p class="app-input-error"><?php echo e($registerErrors['email']); ?></p>
              <?php endif; ?>
            </div>

            <div class="app-field">
              <label class="app-label" for="register-password">Password</label>
              <input
                id="register-password"
                name="password"
                type="password"
                class="app-input<?php echo isset($registerErrors['password']) ? ' is-invalid' : ''; ?>"
                autocomplete="new-password"
                required
              >
              <p class="app-text-muted app-text-sm">Minimum 8 characters.</p>
              <?php if (isset($registerErrors['password'])): ?>
                <p class="app-input-error"><?php echo e($registerErrors['password']); ?></p>
              <?php endif; ?>
            </div>

            <div class="app-field">
              <label class="app-label" for="register-confirm-password">Confirm Password</label>
              <input
                id="register-confirm-password"
                name="confirm_password"
                type="password"
                class="app-input<?php echo isset($registerErrors['confirm_password']) ? ' is-invalid' : ''; ?>"
                autocomplete="new-password"
                required
              >
              <?php if (isset($registerErrors['confirm_password'])): ?>
                <p class="app-input-error"><?php echo e($registerErrors['confirm_password']); ?></p>
              <?php endif; ?>
            </div>

            <?php if ($registerInput['redirect_to'] !== ''): ?>
              <input type="hidden" name="redirect_to" value="<?php echo e($registerInput['redirect_to']); ?>">
            <?php endif; ?>

            <button type="submit" class="app-btn app-btn-primary app-btn-lg">Create Account</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
