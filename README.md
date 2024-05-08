## About

PHP framework, routes, controllers and views

## Requirements

1. PHP 5.3.0+
1. Multibyte String (GD also) (optional, only used in `Inphinit\Helper::toAscii`)
1. libiconv (optional, only used in `Inphinit\Helper::toAscii`)
1. fileinfo (optional, only used in `Inphinit\File::mime`)

## Getting start

This repository is core code of the Inphinit framework, to build an application visit the main [repository](https://github.com/inphinit/inphinit).

Inphinit is a minimalist framework based on the syntax of other popular frameworks, to make learning easier. The core of the framework is divided into two parts: [Inphinit](https://github.com/inphinit/framework/tree/master/src/Inphinit) and [Inphinit\Experimental](https://github.com/inphinit/framework/tree/master/src/Experimental).

- `Inphinit` namespace contains all defined classes that will hardly change their behavior.
- `Inphinit\Experimental` namespace contains classes that are being designed and tested, some of them already work very well, others are not yet fully defined, if the class has all its functionalities defined and tested in the future it will be moved to the `Inphinit` namespace.

To start the framework see the wiki:

- [Directory structure](https://github.com/inphinit/inphinit/wiki/Directory-Structure)
- [Routing](https://github.com/inphinit/inphinit/wiki/Routing)
- [Controllers](https://github.com/inphinit/inphinit/wiki/Controllers)
- [API doc](http://inphinit.github.io/api/)

## TODO

- [ ] Move `system/boot` folder to `inphinit/framework` package
- [ ] Move `system/View/debug/*` (views) to `inphinit/framework` package
- [ ] Move from `Inphinit\Experimental\` to `Inphinit\` namespace:
    - `Inphinit\Experimental\Config`
    - `Inphinit\Experimental\Debug`
    - `Inphinit\Experimental\Dom\*`
    - `Inphinit\Experimental\Exception`
    - `Inphinit\Experimental\Http\Negotiation`
    - `Inphinit\Experimental\Http\Status`
    - `Inphinit\Experimental\Routing\Group`
    - `Inphinit\Experimental\Routing\Quick`
    - `Inphinit\Experimental\Routing\Rest`
- [ ] Create CLI with features:
    - Maintenance mode (change up or down server)
    - Generate `.htaccess`
    - Builtin webserver (start, stop and restart, like httpd2 commands)
- [ ] Auth
- [ ] ORM
- [ ] Unit testing
