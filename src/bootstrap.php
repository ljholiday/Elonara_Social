<?php
declare(strict_types=1);

// Force longer PHP session lifetime before any session is started
ini_set('session.gc_maxlifetime', 86400);   // 24 hours
ini_set('session.cookie_lifetime', 86400);  // 24 hours

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Database\Database;
use App\Http\Controller\AuthController;
use App\Http\Controller\EventController;
use App\Http\Controller\HomeController;
use App\Http\Controller\CommunityController;
use App\Http\Controller\CommunityApiController;
use App\Http\Controller\ConversationController;
use App\Http\Controller\ConversationApiController;
use App\Http\Controller\InvitationApiController;
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
use App\Services\BlueskyService;
use App\Services\DefaultCommunityService;
use PHPMailer\PHPMailer\PHPMailer;


require __DIR__ . '/../vendor/autoload.php';

if (!function_exists('app_config')) {
    /**
     * Retrieve application configuration values.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    function app_config(?string $key = null, $default = null)
    {
        static $config = null;

        if ($config === null) {
            $path = __DIR__ . '/../config/app.php';
            if (!is_file($path)) {
                throw new \RuntimeException('Missing config/app.php. Ensure configuration is published.');
            }

            $loaded = require $path;
            if (!is_array($loaded)) {
                throw new \RuntimeException('config/app.php must return an array.');
            }

            $config = $loaded;
        }

        if ($key === null) {
            return $config;
        }

        return $config[$key] ?? $default;
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


            $container->register('config.database', static function (): array {
                $path = __DIR__ . '/../config/database.php';
                if (!is_file($path)) {
                    throw new \RuntimeException('Missing config/database.php. Copy database.php.sample and update credentials.');
                }

                $config = require $path;
                if (!is_array($config)) {
                    throw new \RuntimeException('config/database.php must return an array.');
                }

                return $config;
            });

            $container->register('database.connection', static function (VTContainer $c): Database {
                return new Database($c->get('config.database'));
            });

            $container->register('event.service', static function (VTContainer $c): EventService {
                return new EventService($c->get('database.connection'));
            });

            $container->register('community.service', static function (VTContainer $c): CommunityService {
                return new CommunityService($c->get('database.connection'));
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
                    $c->get('embed.service')
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

            $container->register('mail.service', static function () use ($appConfig): MailService {

                $mailer = new PHPMailer(true);
                $mailer->isSMTP();
                $mailer->Host = $_ENV['SMTP_HOST'] ?? 'localhost';
                $mailer->Port = (int)($_ENV['SMTP_PORT'] ?? 587);
                $mailer->SMTPAuth = !empty($_ENV['SMTP_USERNAME']);
                if ($mailer->SMTPAuth) {
                    $mailer->Username = $_ENV['SMTP_USERNAME'];
                    $mailer->Password = $_ENV['SMTP_PASSWORD'];
                }
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

                $fromEmail = $appConfig['noreply_email'];
                $fromName  = $appConfig['app_name'];


                return new MailService($mailer, $fromEmail, $fromName);
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
                    $c->get('conversation.service')
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

            $container->register('controller.conversations', static function (VTContainer $c): ConversationController {
                return new ConversationController(
                    $c->get('conversation.service'),
                    $c->get('circle.service'),
                    $c->get('auth.service'),
                    $c->get('authorization.service'),
                    $c->get('validator.service'),
                    $c->get('security.service')
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
