Inphinit is a PHP framework for use routes, controllers and views. To try it, go to:

- https://github.com/inphinit/inphinit/blob/master/README.md (English)
- https://github.com/inphinit/inphinit/blob/master/README-PT.md (Português)

## Requirements

1. Currently supported PHP version: https://www.php.net/supported-versions.php.
    * Minimal _PHP 5.4_ (backward compatibility is maintained for users with upgrade limitations).
    * If you need a full-featured server for Windows or macOS, try: Wamp, Xampp, Laragon, EasyPHP, AMPPS, etc.
1. (Optional) Intl PHP extension to use `Inphinit\Utility\Strings` class.
1. (Optional) COM PHP extension or cURL PHP extension to use `Inphinit\Filesystem\Size` class.

## Getting start

This repository is core code of the Inphinit framework, to build an application visit the main [repository](https://github.com/inphinit/inphinit).

Inphinit is a minimalist framework based on the syntax of other popular frameworks, to make learning easier. The core of the framework is divided into two parts: [`Inphinit`](https://github.com/inphinit/framework/tree/master/src/Inphinit) and [`Inphinit\Experimental`](https://github.com/inphinit/framework/tree/master/src/Experimental).

- `Inphinit` namespace contains all defined classes that will hardly change their behavior.
- `Inphinit\Experimental` namespace contains classes that are being designed and tested, some of them already work very well, others are not yet fully defined, if the class has all its functionalities defined and tested in the future it will be moved to the `Inphinit` namespace.

If you are a contributor, before sending a pull-request it is important to run LINT, use the following command to make it easier:

```bash
find . -type f -name "*.php" -exec php -l {} \;
```

On Windows:

```batch
for /R %F in (*.php) do @php -l %F
```
