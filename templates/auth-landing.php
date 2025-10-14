<?php
$login = $login ?? ($view['login'] ?? ['errors' => [], 'input' => []]);
$register = $register ?? ($view['register'] ?? ['errors' => [], 'input' => []]);
$active = $active ?? ($view['active'] ?? 'login');

$loginInput = array_merge(['identifier' => '', 'remember' => false, 'redirect_to' => ''], $login['input'] ?? []);
$registerInput = array_merge([
    'display_name' => '',
    'username' => '',
    'email' => '',
    'redirect_to' => '',
], $register['input'] ?? []);
$loginErrors = $login['errors'] ?? [];
$registerErrors = $register['errors'] ?? [];
?>
<section class="vt-section vt-auth">
  <div class="vt-container">
    <div class="vt-grid vt-gap-4 vt-grid-2">
      <div class="vt-card<?php echo $active === 'login' ? ' is-active' : ''; ?>">
        <div class="vt-card-header">
          <h1 class="vt-heading">Sign in to Elonara Social</h1>
          <p class="vt-text-muted">Access your dashboard, events, and conversations.</p>
        </div>
        <div class="vt-card-body">
          <?php if (isset($loginErrors['credentials'])): ?>
            <div class="vt-alert vt-alert-error vt-mb-4">
              <p><?php echo e($loginErrors['credentials']); ?></p>
            </div>
          <?php endif; ?>

          <form method="post" action="/auth/login" class="vt-form vt-stack">
            <div class="vt-field">
              <label class="vt-label" for="login-identifier">Email or Username</label>
              <input
                id="login-identifier"
                name="identifier"
                type="text"
                class="vt-input<?php echo isset($loginErrors['identifier']) ? ' is-invalid' : ''; ?>"
                value="<?php echo e($loginInput['identifier']); ?>"
                autocomplete="username"
                required
              >
              <?php if (isset($loginErrors['identifier'])): ?>
                <p class="vt-input-error"><?php echo e($loginErrors['identifier']); ?></p>
              <?php endif; ?>
            </div>

            <div class="vt-field">
              <label class="vt-label" for="login-password">Password</label>
              <input
                id="login-password"
                name="password"
                type="password"
                class="vt-input<?php echo isset($loginErrors['password']) ? ' is-invalid' : ''; ?>"
                autocomplete="current-password"
                required
              >
              <?php if (isset($loginErrors['password'])): ?>
                <p class="vt-input-error"><?php echo e($loginErrors['password']); ?></p>
              <?php endif; ?>
            </div>

            <div class="vt-flex vt-justify-between vt-items-center">
              <label class="vt-checkbox">
                <input type="checkbox" name="remember" value="1"<?php echo $loginInput['remember'] ? ' checked' : ''; ?>>
                <span>Remember me</span>
              </label>
              <a class="vt-text-muted" href="/reset-password">Forgot password?</a>
            </div>

            <?php if ($loginInput['redirect_to'] !== ''): ?>
              <input type="hidden" name="redirect_to" value="<?php echo e($loginInput['redirect_to']); ?>">
            <?php endif; ?>

            <button type="submit" class="vt-btn vt-btn-primary vt-btn-lg">Sign In</button>
          </form>
        </div>
      </div>

      <div class="vt-card<?php echo $active === 'register' ? ' is-active' : ''; ?>">
        <div class="vt-card-header">
          <h2 class="vt-heading">Create an account</h2>
          <p class="vt-text-muted">Plan events, RSVP, and stay connected with your communities.</p>
        </div>
        <div class="vt-card-body">
          <form method="post" action="/auth/register" class="vt-form vt-stack">
            <div class="vt-field">
              <label class="vt-label" for="register-display-name">Display Name</label>
              <input
                id="register-display-name"
                name="display_name"
                type="text"
                class="vt-input<?php echo isset($registerErrors['display_name']) ? ' is-invalid' : ''; ?>"
                value="<?php echo e($registerInput['display_name']); ?>"
                autocomplete="name"
                required
              >
              <?php if (isset($registerErrors['display_name'])): ?>
                <p class="vt-input-error"><?php echo e($registerErrors['display_name']); ?></p>
              <?php endif; ?>
            </div>

            <div class="vt-field">
              <label class="vt-label" for="register-username">Username</label>
              <input
                id="register-username"
                name="username"
                type="text"
                class="vt-input<?php echo isset($registerErrors['username']) ? ' is-invalid' : ''; ?>"
                value="<?php echo e($registerInput['username']); ?>"
                autocomplete="username"
                required
              >
              <p class="vt-text-muted vt-text-sm">At least 3 characters; letters, numbers, underscores, and dashes only.</p>
              <?php if (isset($registerErrors['username'])): ?>
                <p class="vt-input-error"><?php echo e($registerErrors['username']); ?></p>
              <?php endif; ?>
            </div>

            <div class="vt-field">
              <label class="vt-label" for="register-email">Email</label>
              <input
                id="register-email"
                name="email"
                type="email"
                class="vt-input<?php echo isset($registerErrors['email']) ? ' is-invalid' : ''; ?>"
                value="<?php echo e($registerInput['email']); ?>"
                autocomplete="email"
                required
              >
              <?php if (isset($registerErrors['email'])): ?>
                <p class="vt-input-error"><?php echo e($registerErrors['email']); ?></p>
              <?php endif; ?>
            </div>

            <div class="vt-field">
              <label class="vt-label" for="register-password">Password</label>
              <input
                id="register-password"
                name="password"
                type="password"
                class="vt-input<?php echo isset($registerErrors['password']) ? ' is-invalid' : ''; ?>"
                autocomplete="new-password"
                required
              >
              <p class="vt-text-muted vt-text-sm">Minimum 8 characters.</p>
              <?php if (isset($registerErrors['password'])): ?>
                <p class="vt-input-error"><?php echo e($registerErrors['password']); ?></p>
              <?php endif; ?>
            </div>

            <div class="vt-field">
              <label class="vt-label" for="register-confirm-password">Confirm Password</label>
              <input
                id="register-confirm-password"
                name="confirm_password"
                type="password"
                class="vt-input<?php echo isset($registerErrors['confirm_password']) ? ' is-invalid' : ''; ?>"
                autocomplete="new-password"
                required
              >
              <?php if (isset($registerErrors['confirm_password'])): ?>
                <p class="vt-input-error"><?php echo e($registerErrors['confirm_password']); ?></p>
              <?php endif; ?>
            </div>

            <?php if ($registerInput['redirect_to'] !== ''): ?>
              <input type="hidden" name="redirect_to" value="<?php echo e($registerInput['redirect_to']); ?>">
            <?php endif; ?>

            <button type="submit" class="vt-btn vt-btn-primary vt-btn-lg">Create Account</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
