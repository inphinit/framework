Inphinit is a PHP framework for managing routes, controllers, and views. To learn more or try it out, visit:

- https://github.com/inphinit/inphinit/blob/master/README.md (English)
- https://github.com/inphinit/inphinit/blob/master/README-PT.md (Português)

## Requirements

1. See currently supported PHP versions: https://www.php.net/supported-versions.php.
    * Minimal _PHP 5.4_ (backward compatibility is maintained for users with upgrade limitations).
    * If you need a full-featured server for Windows or macOS, you can try WampServer, XAMPP, Laragon, EasyPHP, or AMPPS.
1. (Optional) Intl PHP extension to use `Inphinit\Utility\Strings` class.
1. (Optional) COM PHP extension or cURL PHP extension to use `Inphinit\Filesystem\Size` class.

## Getting started

This repository contains the core code of the Inphinit framework. To build an application, visit the main [repository](https://github.com/inphinit/inphinit).

Inphinit is a minimalist framework inspired by the syntax of other popular frameworks, designed to make learning easier. The core of the framework is divided into two parts: [`Inphinit`](https://github.com/inphinit/framework/tree/master/src/Inphinit) and [`Inphinit\Experimental`](https://github.com/inphinit/framework/tree/master/src/Experimental).

- `Inphinit` namespace contains all defined classes that will hardly change their behavior.
- `Inphinit\Experimental` namespace contains classes that are being designed and tested. Some of them already work very well, while others are still being defined. Once a class is stable and fully tested, it will be moved to the `Inphinit` namespace.

If you are a contributor, before sending a pull-request it is important to run LINT, run the following command to check for syntax errors:

```bash
find . -type f -name "*.php" -exec php -l {} \;
```

On Windows:

```batch
for /R %F in (*.php) do @php -l %F
```
