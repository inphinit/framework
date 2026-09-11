<p align="center">
<a href="https://packagist.org/packages/inphinit/framework"><img src="https://img.shields.io/packagist/dt/inphinit/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/inphinit/framework"><img src="https://img.shields.io/packagist/v/inphinit/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/inphinit/framework"><img src="https://img.shields.io/packagist/l/inphinit/framework" alt="License"></a>
</p>

## Getting started

This repository contains the core code of the Inphinit framework. To build an application, visit the main [repository](https://github.com/inphinit/inphinit).

The core of the framework is divided into two parts: [`Inphinit`](https://github.com/inphinit/framework/tree/master/src/Inphinit) and [`Inphinit\Experimental`](https://github.com/inphinit/framework/tree/master/src/Experimental).

- `Inphinit` namespace contains all defined classes that will hardly change their behavior.
- `Inphinit\Experimental` namespace contains classes that are being designed and tested. Some of them already work very well, while others are still being defined. Once a class is stable and fully tested, it will be moved to the `Inphinit` namespace.

## Contributing

Requirements:

1. Recommended: *PHP 8* (see the currently supported versions at https://www.php.net/supported-versions.php)
   - Minimum: *PHP 5.4* (backward compatibility is preserved for environments with upgrade limitations)
1. Intl PHP extension, required for the `Inphinit\Utility\Strings` class.
1. COM or cURL PHP extension, required for the `Inphinit\Filesystem\Size` class.

Before submitting a pull-request, it's important to run *LINT* with the following command to check for potential errors:

```bash
find . -type f -name "*.php" -exec php -l {} \;
```

In Windows environments (cmd) you should run the command:

```batch
for /R %F in (*.php) do @php -l %F
```

## Documentation

The documentation contains instructions for using the framework, as well as the basic application that uses the framework.

- English: https://inphinit.github.io/en/docs/
- Portuguese: https://inphinit.github.io/pt/docs/
- API Reference: https://inphinit.github.io/api/
