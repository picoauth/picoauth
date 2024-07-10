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

use League\Container\Argument\Literal\StringArgument;
use League\Container\Argument\Literal\ArrayArgument;
use League\Container\Container;

$container = new League\Container\Container();

// Version of this file, so PicoAuth can detect possibly outdated definition
$container->addShared('Version', new StringArgument(10000));


// ****************** Configuration cache setup ********************************

// Any implementation of Psr\SimpleCache\CacheInterface
// e.g. from package cache/cache: $pool = new ApcuCachePool();
$pool = 'PicoAuth\Cache\NullCache';
$container->addShared('cache', $pool);

// ****************** Logger setup *********************************************

// Any implementation of \Psr\Log\LoggerInterface
// e.g. $log = new \Monolog\Logger('name');
$log = 'Psr\Log\NullLogger';
$container->addShared('logger', $log);

// ****************** Mail setup ***********************************************

// Any implementation of PicoAuth\Mail\MailerInterface
//include 'Mailer.php';
//$container->addShared('mailer','PicoAuth\Mail\Mailer');

// ****************** Password policy setup ************************************

// Specify constraints of the default policy
// Or provide an alternative implementation of PicoAuth\Security\Password\Policy\PasswordPolicyInterface

$container->addShared('PasswordPolicy', 'PicoAuth\Security\Password\Policy\PasswordPolicy')
    ->addMethodCall('minLength', [new StringArgument(8)]);

// ****************** Session management configuration *************************

// PicoAuth default session driver
$container->addShared('session', 'PicoAuth\Session\SymfonySession')
    ->addArgument('session.storage');

$container->addShared('session.storage', 'Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage')
    ->addArgument(new ArrayArgument(array(
        //"cookie_secure" => "0",       // Set to "1" if using HTTPS
        "cookie_lifetime" => "0",       // Until user closes the browser
        "gc_maxlifetime" => "7200",     // Activity timeout 2hrs
        "name" => "Pico",               // Session cookie name
        "cookie_httponly" => "1",
    )));

// *****************************************************************************

// PicoAuth modules
$container->addShared('LocalAuth', 'PicoAuth\Module\Authentication\LocalAuth\LocalAuth')
    ->addArgument('PicoAuth')
    ->addArgument('session')
    ->addArgument('LocalAuth.storage')
    ->addArgument('RateLimit');

$container->addShared('OAuth', 'PicoAuth\Module\Authentication\OAuth')
    ->addArgument('PicoAuth')
    ->addArgument('session')
    ->addArgument('OAuth.storage')
    ->addMethodCall('setLogger', ['logger']);

$container->addShared('PageACL', 'PicoAuth\Module\Authorization\PageACL')
    ->addArgument('PicoAuth')
    ->addArgument('session')
    ->addArgument('PageACL.storage');

$container->addShared('PageLock', 'PicoAuth\Module\Authorization\PageLock')
    ->addArgument('PicoAuth')
    ->addArgument('session')
    ->addArgument('PageLock.storage')
    ->addArgument('RateLimit');

$container->addShared('Installer', 'PicoAuth\Module\Generic\Installer')
    ->addArgument('PicoAuth');

// Storage
$container->addShared('LocalAuth.storage', 'PicoAuth\Storage\LocalAuthFileStorage')
    ->addArgument('configDir')
    ->addArgument('cache');
$container->addShared('OAuth.storage', 'PicoAuth\Storage\OAuthFileStorage')
    ->addArgument('configDir')
    ->addArgument('cache');
$container->addShared('PageACL.storage', 'PicoAuth\Storage\PageACLFileStorage')
    ->addArgument('configDir')
    ->addArgument('cache');
$container->addShared('PageLock.storage', 'PicoAuth\Storage\PageLockFileStorage')
    ->addArgument('configDir')
    ->addArgument('cache');
$container->addShared('RateLimit.storage', 'PicoAuth\Storage\RateLimitFileStorage')
    ->addArgument('configDir')
    ->addArgument('cache');

// Password hashing options
$container->add('bcrypt', 'PicoAuth\Security\Password\Encoder\BCrypt');
$container->add('argon2i', 'PicoAuth\Security\Password\Encoder\Argon2i');
$container->add('plain', 'PicoAuth\Security\Password\Encoder\Plaintext');

// Rate limiting
$container->addShared('RateLimit', 'PicoAuth\Security\RateLimiting\RateLimit')
    ->addArgument('RateLimit.storage')
    ->addMethodCall('setLogger', ['logger']);

// LocalAuth extensions
$container->addShared('PasswordReset', 'PicoAuth\Module\Authentication\LocalAuth\PasswordReset')
    ->addArgument('PicoAuth')
    ->addArgument('session')
    ->addArgument('LocalAuth.storage')
    ->addArgument('RateLimit')
//  ->addMethodCall('setMailer',['mailer'])
    ->addMethodCall('setLogger', ['logger']);

$container->addShared('Registration', 'PicoAuth\Module\Authentication\LocalAuth\Registration')
    ->addArgument('PicoAuth')
    ->addArgument('session')
    ->addArgument('LocalAuth.storage')
    ->addArgument('RateLimit')
    ->addMethodCall('setLogger', ['logger']);

$container->addShared('EditAccount', 'PicoAuth\Module\Authentication\LocalAuth\EditAccount')
    ->addArgument('PicoAuth')
    ->addArgument('session')
    ->addArgument('LocalAuth.storage');

return $container;
