<?php
declare(strict_types=1);

// Force longer PHP session lifetime before any session is started
ini_set('session.gc_maxlifetime', 86400);   // 24 hours
ini_set('session.cookie_lifetime', 86400);  // 24 hours

// Extend session duration to 24 hours
ini_set('session.gc_maxlifetime', 86400);  // 24 hours in seconds
ini_set('session.cookie_lifetime', 86400);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Database\Database;
use Dotenv\Dotenv;
use App\Http\Controller\AuthController;
use App\Http\Controller\AdminController;
use App\Http\Controller\EventController;
use App\Http\Controller\HomeController;
use App\Http\Controller\CommunityController;
use App\Http\Controller\CommunityApiController;
use App\Http\Controller\ConversationController;
use App\Http\Controller\ConversationApiController;
use App\Http\Controller\InvitationApiController;
use App\Http\Controller\SearchController;
use App\Http\Controller\ProfileController;
use App\Http\Request;
use App\Services\EventService;
use App\Services\CommunityService;
use App\Services\CommunityMemberService;
use App\Services\ConversationService;
use App\Services\CircleService;
use App\Services\AuthService;
use App\Services\MailService;
use App\Services\SanitizerService;
use App\Services\ValidatorService;
use App\Services\InvitationService;
use App\Services\EventGuestService;
use App\Services\AuthorizationService;
use App\Services\NavigationService;
use App\Services\ImageService;
use App\Services\EmbedService;
use App\Services\SecurityService;
use App\Services\UserService;
use App\Services\SearchService;
use App\Services\BlueskyService;
use App\Services\DefaultCommunityService;
use PHPMailer\PHPMailer\PHPMailer;


require __DIR__ . '/../vendor/autoload.php';

// Load image helper functions
require __DIR__ . '/../includes/image-helpers.php';

// Load environment variables from .env if present (local development convenience).
// These can override config file values if needed.
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

if (!function_exists('app_config')) {
    /**
     * Retrieve application configuration values from the master config file.
     *
     * @param string|null $key Dot-notation key (e.g., 'app.name', 'database.host')
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    function app_config(?string $key = null, $default = null)
    {
        static $config = null;

        if ($config === null) {
            $path = __DIR__ . '/../config/config.php';
            if (!is_file($path)) {
                throw new \RuntimeException(
                    'Missing config/config.php. Copy config/config.sample.php to config/config.php and configure.'
                );
            }

            $loaded = require $path;
            if (!is_array($loaded)) {
                throw new \RuntimeException('config/config.php must return an array.');
            }

            // Auto-generate security salts if missing
            // Check if salts are in main config
            if (empty($loaded['security']['salts']['auth']) ||
                empty($loaded['security']['salts']['nonce']) ||
                empty($loaded['security']['salts']['session'])) {

                // Try loading from separate security_salts.php file (backwards compatibility)
                $saltsPath = __DIR__ . '/../config/security_salts.php';
                if (file_exists($saltsPath)) {
                    $loadedSalts = require $saltsPath;
                    if (is_array($loadedSalts)) {
                        $loaded['security']['salts'] = $loadedSalts;
                    }
                } else {
                    // Generate new salts
                    $newSalts = [
                        'auth' => bin2hex(random_bytes(32)),
                        'nonce' => bin2hex(random_bytes(32)),
                        'session' => bin2hex(random_bytes(32)),
                    ];

                    // Try to persist to separate file (easier permissions management)
                    $saltsContent = "<?php\n// Security salts - auto-generated\n// DO NOT commit to git\nreturn " . var_export($newSalts, true) . ";\n";
                    @file_put_contents($saltsPath, $saltsContent);

                    // Use the generated salts regardless of whether file write succeeded
                    $loaded['security']['salts'] = $newSalts;
                }
            }

            // Apply environment-specific debug settings
            if ($loaded['environment'] === 'local' && !isset($loaded['app']['debug'])) {
                $loaded['app']['debug'] = true;
            }

            $config = $loaded;
        }

        if ($key === null) {
            return $config;
        }

        // Support dot notation (e.g., 'app.name', 'database.host')
        if (str_contains($key, '.')) {
            $parts = explode('.', $key);
            $value = $config;
            foreach ($parts as $part) {
                if (!is_array($value) || !array_key_exists($part, $value)) {
                    return $default;
                }
                $value = $value[$part];
            }
            return $value;
        }

        return $config[$key] ?? $default;
    }
}

if (!function_exists('app_env')) {
    /**
     * Get the current application environment.
     *
     * @return string 'local', 'staging', or 'production'
     */
    function app_env(): string
    {
        return app_config('environment', 'production');
    }
}

if (!function_exists('is_production')) {
    /**
     * Check if running in production environment.
     */
    function is_production(): bool
    {
        return app_env() === 'production';
    }
}

if (!function_exists('is_local')) {
    /**
     * Check if running in local development environment.
     */
    function is_local(): bool
    {
        return app_env() === 'local';
    }
}

if (!function_exists('is_staging')) {
    /**
     * Check if running in staging environment.
     */
    function is_staging(): bool
    {
        return app_env() === 'staging';
    }
}

if (!function_exists('user_config')) {
    /**
     * Retrieve user-related configuration values (e.g., username requirements).
     * Now pulls from the master config file.
     */
    function user_config(?string $key = null, $default = null)
    {
        $userConfig = app_config('users', []);

        if ($key === null) {
            return $userConfig;
        }

        return $userConfig[$key] ?? $default;
    }
}

/**
 * Very small service container for modern code paths.
 *
 * This intentionally mirrors the legacy helper functions (`app_service`,
 * `app_container`) so templates and controllers can stay agnostic while the
 * migration to namespaced classes progresses.
 */
final class VTContainer
{
    /** @var array<string, callable(self):mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * Register a service factory.
     *
     * @param string $id Identifier like "event.service".
     * @param callable(self):mixed $factory Factory that returns the service instance.
     * @param bool $shared Whether the instance should be cached (default true).
     */
    public function register(string $id, callable $factory, bool $shared = true): void
    {
        $this->factories[$id] = [$factory, $shared];
        if (!$shared) {
            unset($this->instances[$id]);
        }
    }

    /**
     * Resolve a service by id.
     *
     * @template T
     * @param string $id
     * @return mixed
     */
    public function get(string $id)
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new \RuntimeException(sprintf('Service "%s" not registered.', $id));
        }

        [$factory, $shared] = $this->factories[$id];
        $instance = $factory($this);

        if ($shared) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }
}

if (!function_exists('app_container')) {
    /**
     * Retrieve the global container instance, creating it on first use.
     */
    function app_container(): VTContainer
    {
        static $container = null;

        if ($container === null) {
            $container = new VTContainer();
            // Load application configuration so it's available inside service closures
            $appConfig = app_config();
            $mailConfig = app_config('mail', []);
            $dbConfig = app_config('database', []);

            $container->register('config.database', static function () use ($dbConfig): array {
                return $dbConfig;
            });

            $container->register('database.connection', static function (VTContainer $c): Database {
                return new Database($c->get('config.database'));
            });

            $container->register('event.service', static function (VTContainer $c): EventService {
                return new EventService(
                    $c->get('database.connection'),
                    $c->get('search.service')
                );
            });

            $container->register('community.service', static function (VTContainer $c): CommunityService {
                return new CommunityService(
                    $c->get('database.connection'),
                    $c->get('search.service')
                );
            });

            $container->register('community.member.service', static function (VTContainer $c): CommunityMemberService {
                return new CommunityMemberService($c->get('database.connection'));
            });

            $container->register('event.guest.service', static function (VTContainer $c): EventGuestService {
                return new EventGuestService($c->get('database.connection'));
            });

            $container->register('default.community.service', static function (VTContainer $c): DefaultCommunityService {
                return new DefaultCommunityService($c->get('community.service'));
            });

            $container->register('conversation.service', static function (VTContainer $c): ConversationService {
                return new ConversationService(
                    $c->get('database.connection'),
                    $c->get('image.service'),
                    $c->get('embed.service'),
                    $c->get('search.service')
                );
            });

            $container->register('circle.service', static function (VTContainer $c): CircleService {
                return new CircleService($c->get('database.connection'));
            });

            $container->register('sanitizer.service', static function (): SanitizerService {
                return new SanitizerService();
            });

            $container->register('validator.service', static function (VTContainer $c): ValidatorService {
                return new ValidatorService($c->get('sanitizer.service'));
            });

            $container->register('authorization.service', static function (VTContainer $c): AuthorizationService {
                return new AuthorizationService($c->get('database.connection'));
            });

            $container->register('navigation.service', static function (VTContainer $c): NavigationService {
                return new NavigationService($c->get('authorization.service'));
            });

            $container->register('auth.service', static function (VTContainer $c): AuthService {
                return new AuthService($c->get('database.connection'), $c->get('mail.service'));
            });

            $container->register('mail.service', static function () use ($appConfig, $mailConfig): MailService {
                $mailer = new PHPMailer(true);

                $transport = strtolower((string)($mailConfig['transport'] ?? 'smtp'));
                switch ($transport) {
                    case 'sendmail':
                        $mailer->isSendmail();
                        if (!empty($mailConfig['sendmail_path'])) {
                            $mailer->Sendmail = (string)$mailConfig['sendmail_path'];
                        }
                        break;
                    case 'mail':
                        $mailer->isMail();
                        break;
                    case 'smtp':
                    default:
                        $mailer->isSMTP();
                        $mailer->Host = (string)($mailConfig['host'] ?? '127.0.0.1');
                        $mailer->Port = (int)($mailConfig['port'] ?? 1025);
                        $mailer->SMTPAuth = (bool)($mailConfig['auth'] ?? false);
                        if ($mailer->SMTPAuth) {
                            if (!empty($mailConfig['username'])) {
                                $mailer->Username = (string)$mailConfig['username'];
                            }
                            if (!empty($mailConfig['password'])) {
                                $mailer->Password = (string)$mailConfig['password'];
                            }
                        }
                        $encryption = $mailConfig['encryption'] ?? '';
                        if (is_string($encryption) && $encryption !== '' && strtolower($encryption) !== 'none') {
                            $mailer->SMTPSecure = $encryption;
                        }
                        break;
                }

                if (!empty($mailConfig['timeout'])) {
                    $mailer->Timeout = (int)$mailConfig['timeout'];
                }

                if (!empty($mailConfig['debug'])) {
                    $mailer->SMTPDebug = (int)$mailConfig['debug'];
                }

                // Use mail config or fall back to app config
                $fromEmail = !empty($mailConfig['from_address'])
                    ? (string)$mailConfig['from_address']
                    : (string)($appConfig['app']['noreply_email'] ?? 'noreply@example.com');
                $fromName = !empty($mailConfig['from_name'])
                    ? (string)$mailConfig['from_name']
                    : (string)($appConfig['app']['name'] ?? 'Application');

                $replyTo = [
                    'address' => !empty($mailConfig['reply_to_address'])
                        ? (string)$mailConfig['reply_to_address']
                        : (string)($appConfig['app']['support_email'] ?? ''),
                    'name' => !empty($mailConfig['reply_to_name'])
                        ? (string)$mailConfig['reply_to_name']
                        : (string)($appConfig['app']['name'] ?? 'Application') . ' Support',
                ];

                return new MailService($mailer, $fromEmail, $fromName, $replyTo);
            });

            $container->register('image.service', static function (): ImageService {
                $uploadBasePath = dirname(__DIR__) . '/public/uploads';
                $uploadBaseUrl = '/uploads';
                return new ImageService($uploadBasePath, $uploadBaseUrl);
            });

            $container->register('embed.service', static function (): EmbedService {
                return new EmbedService();
            });

            $container->register('security.service', static function (): SecurityService {
                return new SecurityService();
            });

            $container->register('user.service', static function (VTContainer $c): UserService {
                return new UserService(
                    $c->get('database.connection'),
                    $c->get('image.service')
                );
            });

            $container->register('search.service', static function (VTContainer $c): SearchService {
                return new SearchService($c->get('database.connection'));
            });

            $container->register('bluesky.service', static function (VTContainer $c): BlueskyService {
                return new BlueskyService($c->get('database.connection'));
            });

            $container->register('invitation.manager', static function (VTContainer $c): InvitationService {
                return new InvitationService(
                    $c->get('database.connection'),
                    $c->get('auth.service'),
                    $c->get('mail.service'),
                    $c->get('sanitizer.service'),
                    $c->get('event.guest.service'),
                    $c->get('community.member.service'),
                    $c->get('bluesky.service')
                );
            });

            $container->register('controller.auth', static function (VTContainer $c): AuthController {
                return new AuthController($c->get('auth.service'), $c->get('validator.service'));
            }, false);

            $container->register('controller.events', static function (VTContainer $c): EventController {
                return new EventController(
                    $c->get('event.service'),
                    $c->get('auth.service'),
                    $c->get('validator.service'),
                    $c->get('invitation.manager'),
                    $c->get('conversation.service'),
                    $c->get('authorization.service'),
                    $c->get('community.service')
                );
            }, false);

            $container->register('controller.home', static function (VTContainer $c): HomeController {
                return new HomeController(
                    $c->get('auth.service'),
                    $c->get('event.service'),
                    $c->get('community.service'),
                    $c->get('conversation.service'),
                    $c->get('circle.service')
                );
            }, false);

            $container->register('controller.admin', static function (VTContainer $c): AdminController {
                return new AdminController(
                    $c->get('auth.service'),
                    $c->get('event.service'),
                    $c->get('community.service'),
                    $c->get('mail.service'),
                    $c->get('user.service'),
                    $c->get('search.service')
                );
            }, false);

            $container->register('controller.communities', static function (VTContainer $c): CommunityController {
                return new CommunityController(
                    $c->get('community.service'),
                    $c->get('circle.service'),
                    $c->get('auth.service'),
                    $c->get('authorization.service'),
                    $c->get('validator.service'),
                    $c->get('community.member.service'),
                    $c->get('event.service'),
                    $c->get('conversation.service')
                );
            }, false);

            $container->register('controller.communities.api', static function (VTContainer $c): CommunityApiController {
                return new CommunityApiController(
                    $c->get('community.service'),
                    $c->get('auth.service'),
                    $c->get('authorization.service'),
                    $c->get('security.service')
                );
            }, false);

            $container->register('controller.search', static function (VTContainer $c): SearchController {
                return new SearchController(
                    $c->get('search.service'),
                    $c->get('auth.service')
                );
            }, false);

            $container->register('controller.conversations', static function (VTContainer $c): ConversationController {
                return new ConversationController(
                    $c->get('conversation.service'),
                    $c->get('circle.service'),
                    $c->get('auth.service'),
                    $c->get('authorization.service'),
                    $c->get('validator.service'),
                    $c->get('security.service'),
                    $c->get('community.service'),
                    $c->get('event.service')
                );
            }, false);

            $container->register('controller.conversations.api', static function (VTContainer $c): ConversationApiController {
                return new ConversationApiController(
                    $c->get('conversation.service'),
                    $c->get('circle.service'),
                    $c->get('auth.service'),
                    $c->get('security.service')
                );
            }, false);

            $container->register('controller.invitations', static function (VTContainer $c): InvitationApiController {
                return new InvitationApiController(
                    $c->get('database.connection'),
                    $c->get('auth.service'),
                    $c->get('invitation.manager'),
                    $c->get('security.service'),
                    $c->get('community.member.service')
                );
            }, false);

            $container->register('controller.profile', static function (VTContainer $c): ProfileController {
                return new ProfileController(
                    $c->get('auth.service'),
                    $c->get('user.service'),
                    $c->get('validator.service'),
                    $c->get('security.service')
                );
            }, false);

            $container->register('http.request', static function (): Request {
                return Request::fromGlobals();
            }, false);
        }

        return $container;
    }
}

if (!function_exists('app_service')) {
    /**
     * Convenience accessor for services during the migration.
     *
     * @param string $id
     * @return mixed
     */
    function app_service(string $id)
    {
        return app_container()->get($id);
    }
}

if (!function_exists('vt_container')) {
    function vt_container(): VTContainer
    {
        return app_container();
    }
}

if (!function_exists('vt_service')) {
    function vt_service(string $id)
    {
        return app_service($id);
    }
}

/**
 * Legacy compatibility shim: App_Mail
 * Routes legacy mail helpers through the modern MailService.
 */
if (!class_exists('App_Mail')) {
    final class App_Mail {
        /**
         * @param string|array<string> $to
         */
        public static function send($to, string $subject, string $htmlBody, string $textBody = ''): bool
        {
            /** @var \App\Services\MailService $mail */
            $mail = app_service('mail.service');
            return $mail->send($to, $subject, $htmlBody, $textBody);
        }

        /**
         * @param array<string,mixed> $variables
         */
        public static function sendTemplate(string $to, string $template, array $variables = []): bool
        {
            /** @var \App\Services\MailService $mail */
            $mail = app_service('mail.service');
            return $mail->sendTemplate($to, $template, $variables);
        }
    }
}

if (!class_exists('VT_Mail')) {
    class_alias(App_Mail::class, 'VT_Mail');
}
