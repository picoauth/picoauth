PicoAuth
====
PicoAuth is a plugin for Pico CMS 2.0 providing various means of authentication and authorization to pages.

[![Build Status](https://travis-ci.org/picoauth/picoauth.svg?branch=master)](https://travis-ci.org/picoauth/picoauth)
[![Latest Stable Version](https://poser.pugx.org/picoauth/picoauth/v/stable)](https://packagist.org/packages/picoauth/picoauth)
[![License](https://poser.pugx.org/picoauth/picoauth/license)](https://github.com/picoauth/picoauth/blob/master/LICENSE)

The functionality of the plugin is contained in independent modules that can be individually enabled and configured to suit the needs of the website owner. Simple description of the modules that are included in the plugin:

* **Local user accounts**
  * Visitors can login to the site using accounts stored locally
  * User accounts defined in a configuration file
  * User registration *(optional)*
  * Password reset function *(optional)*
  * Users can change their password *(optional)*
* **Login using OAuth 2.0 services**
  * Visitors can login to the site using a 3rd party service that supports the OAuth 2.0 protocol
* **Access control**
  * Access control to Pico pages based on permissions of an authenticated user
* **Page locks**
  * Can be used to display selected pages only after the correct page key phrase is entered (no authentication required)

The plugin provides security features like CSRF tokens on all forms, IP-subnet rate limiting on sensitive actions (login attempts, registrations, etc.), option to enable logging, session security configuration, configurable password policy, selectable password hashing algorithms and other security options. There are many options for an advanced configuration like - caching configuration files, password reset options, dependency injection configuration. PicoAuth contains a default theme for its pages (login, registration form, etc.), but can be integrated into any existing Pico template with only few changes. The plugin also supports other alternative authentication/authorization methods that can be added via external plugins.

## Documentation
[GitHub wiki](https://github.com/picoauth/picoauth/wiki) or [picoauth.github.io](https://picoauth.github.io/).

Contains an Installation guide, Full Feature Reference with a description of all configuration options, Security considerations and options for advanced settings.

Screenshots
-----------
![PicoAuth](https://i.imgur.com/FMXWCZd.png)

Install
-------
PicoAuth requires **PHP 5.6.0+** The recommended way of installation is using composer (possible only if you installed Pico using [composer-installer](https://github.com/picocms/composer-installer)).
The plugin can be installed by issuing this command in the Pico root directory:

```
composer require picoauth/picoauth
```

Then visit `/PicoAuth` page (`?PicoAuth` if not using url-rewriting) in your Pico installation to view the installer, which will perform a basic security check of your Pico installation and will guide you through the rest of the installation (like selection of the modules you want to use).

*It is also possible to install the plugin without composer. This is not recommended due to complicated updating, so use it only if composer cannot be used. This method of installation is described in the [Documentation](#documentation).*

Configuration
-------------
The main plugin configuration is located in Pico's `config/config.yml`. Each enabled authentication/authorization module has its own configuration in the `config/PicoAuth` directory. Usually the file name corresponds to the module name (e.g. `LocalAuth.yml`, `OAuth.yml`, `PageACL.yml`, `PageLock.yml`). Refer to the **plugin [Documentation](#documentation) for the full configuration reference**. See project [picoauth/picoauth-examples](https://github.com/picoauth/picoauth-examples) for in-depth configuration examples.

### See also
* [picoauth/picoauth-theme](https://github.com/picoauth/picoauth-theme) - An example theme for Pico CMS using the PicoAuth plugin. Can serve as a template for customizing your own theme to work with PicoAuth. See the Demo: [https://picoauth.xyz](https://picoauth.xyz).
* [picoauth/picoauth-examples](https://github.com/picoauth/picoauth-examples) - in-depth configuration examples.

Getting help
------------
If you encounter a problem with the plugin that cannot be resolved by reading the corresponding section of the [Documentation](#documentation), you can create a new Issue. Please describe the problem as clear as possible.
