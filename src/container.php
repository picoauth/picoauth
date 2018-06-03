<?php
/**
 * Default container definition for PicoAuth plugin.
 *
 * Don't edit the plugin's file - but create a copy in
 * {PicoRoot}/config/PicoAuth/container.php
 *
 * If the container.php exists in the configuration directory it is used
 * instead of the default one.
 */

use League\Container\Argument\RawArgument;
use League\Container\Container;

$container = new Container;

// Version of this file, so PicoAuth can detect possibly outdated definition
$container->share('Version', new RawArgument(10000));


// ****************** Configuration cache setup ********************************

// Any implementation of Psr\SimpleCache\CacheInterface
// e.g. from package cache/cache: $pool = new ApcuCachePool();
$pool = 'PicoAuth\Cache\NullCache';
$container->share('cache', $pool);

// ****************** Logger setup *********************************************

// Any implementation of \Psr\Log\LoggerInterface
// e.g. $log = new \Monolog\Logger('name');
$log = 'Psr\Log\NullLogger';
$container->share('logger', $log);

// ****************** Mail setup ***********************************************

// Any implementation of PicoAuth\Mail\MailerInterface
//include 'Mailer.php';
//$container->share('mailer','PicoAuth\Mail\Mailer');

// ****************** Password policy setup ************************************

// Specify constraints of the default policy
// Or provide an alternative implementation of PicoAuth\Security\Password\Policy\PasswordPolicyInterface

$container->share('PasswordPolicy', 'PicoAuth\Security\Password\Policy\PasswordPolicy')
    ->withMethodCall('minLength', [new RawArgument(8)]);

// ****************** Session management configuration *************************

// PicoAuth default session driver
$container->share('session', 'PicoAuth\Session\SymfonySession')
    ->withArgument('session.storage');

$container->share('session.storage', 'Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage')
    ->withArgument(new RawArgument(array(
        //"cookie_secure" => "0",       // Set to "1" if using HTTPS
        "cookie_lifetime" => "0",       // Until user closes the browser
        "gc_maxlifetime" => "7200",     // Activity timeout 2hrs
        "name" => "Pico",               // Session cookie name
        "cookie_httponly" => "1",
    )));

// *****************************************************************************

// PicoAuth modules
$container->share('LocalAuth', 'PicoAuth\Module\Authentication\LocalAuth\LocalAuth')
    ->withArgument('PicoAuth')
    ->withArgument('session')
    ->withArgument('LocalAuth.storage')
    ->withArgument('RateLimit');

$container->share('OAuth', 'PicoAuth\Module\Authentication\OAuth')
    ->withArgument('PicoAuth')
    ->withArgument('session')
    ->withArgument('OAuth.storage')
    ->withMethodCall('setLogger', ['logger']);

$container->share('PageACL', 'PicoAuth\Module\Authorization\PageACL')
    ->withArgument('PicoAuth')
    ->withArgument('session')
    ->withArgument('PageACL.storage');

$container->share('PageLock', 'PicoAuth\Module\Authorization\PageLock')
    ->withArgument('PicoAuth')
    ->withArgument('session')
    ->withArgument('PageLock.storage')
    ->withArgument('RateLimit');

$container->share('Installer', 'PicoAuth\Module\Generic\Installer')
    ->withArgument('PicoAuth');

// Storage
$container->share('LocalAuth.storage', 'PicoAuth\Storage\LocalAuthFileStorage')
    ->withArgument('configDir')
    ->withArgument('cache');
$container->share('OAuth.storage', 'PicoAuth\Storage\OAuthFileStorage')
    ->withArgument('configDir')
    ->withArgument('cache');
$container->share('PageACL.storage', 'PicoAuth\Storage\PageACLFileStorage')
    ->withArgument('configDir')
    ->withArgument('cache');
$container->share('PageLock.storage', 'PicoAuth\Storage\PageLockFileStorage')
    ->withArgument('configDir')
    ->withArgument('cache');
$container->share('RateLimit.storage', 'PicoAuth\Storage\RateLimitFileStorage')
    ->withArgument('configDir')
    ->withArgument('cache');

// Password hashing options
$container->add('bcrypt', 'PicoAuth\Security\Password\Encoder\BCrypt');
$container->add('argon2i', 'PicoAuth\Security\Password\Encoder\Argon2i');
$container->add('plain', 'PicoAuth\Security\Password\Encoder\Plaintext');

// Rate limiting
$container->share('RateLimit', 'PicoAuth\Security\RateLimiting\RateLimit')
    ->withArgument('RateLimit.storage')
    ->withMethodCall('setLogger', ['logger']);

// LocalAuth extensions
$container->share('PasswordReset', 'PicoAuth\Module\Authentication\LocalAuth\PasswordReset')
    ->withArgument('PicoAuth')
    ->withArgument('session')
    ->withArgument('LocalAuth.storage')
    ->withArgument('RateLimit')
//  ->withMethodCall('setMailer',['mailer'])
    ->withMethodCall('setLogger', ['logger']);

$container->share('Registration', 'PicoAuth\Module\Authentication\LocalAuth\Registration')
    ->withArgument('PicoAuth')
    ->withArgument('session')
    ->withArgument('LocalAuth.storage')
    ->withArgument('RateLimit')
    ->withMethodCall('setLogger', ['logger']);

$container->share('EditAccount', 'PicoAuth\Module\Authentication\LocalAuth\EditAccount')
    ->withArgument('PicoAuth')
    ->withArgument('session')
    ->withArgument('LocalAuth.storage');

return $container;
