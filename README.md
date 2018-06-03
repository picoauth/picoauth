PicoAuth
====
PicoAuth is a plugin for Pico CMS 2.0 providing various means of authentication and authorization to pages.

**Full Documentation & Feature Reference:** [GitHub wiki](https://github.com/picoauth/picoauth/wiki) or [picoauth.github.io](https://picoauth.github.io/).

The functionality of the plugin is contained in independent modules that can be individually enabled and configured to suit the needs of the website owner. Simple description  of the modules that are included in the plugin:

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

Then visit `/PicoAuth` page (`?PicoAuth` if not using url-rewriting) in your Pico installation to view the installer, which will perform a basic security check of your Pico installation and will guide you through the rest of the installation.

*It is also possible to install the plugin also without composer. This is not recommended due to complicated updating, so use it only if composer cannot be used. This method of installation is described in the Documentation.*
