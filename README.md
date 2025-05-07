Inphinit is a PHP framework for use routes, controllers and views. To try it, go to:

- https://github.com/inphinit/inphinit/blob/master/README.md (English)
- https://github.com/inphinit/inphinit/blob/master/README-PT.md (Português)

## Requirements

1. PHP 8 (minimal PHP 5.4 -- consider updating to one of the latest PHP versions when possible: https://www.php.net/supported-versions.php)
1. Multibyte String (GD also) (optional, only used in `Inphinit\Utility\Strings` class)
1. libiconv (optional, only used in `Inphinit\Utility\Strings` class)
1. fileinfo (optional, only used in `Inphinit\Filesystem\File`)
1. COM or cURL (optional, only used in `Inphinit\Filesystem\Size`)

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
