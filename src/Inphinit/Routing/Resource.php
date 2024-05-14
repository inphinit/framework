<?php
namespace Inphinit\Routing;

use Inphinit\App;

abstract class Resource extends Router
{
    /** Create a route with slash at the end, like: `/foo/` */
    const SLASH = 1;

    /** Create a route without slash at the end, like: `/foo` */
    const NOSLASH = 2;

    /**
     * Define path format, possible values: self::SLASH, self::NOSLASH, self::SLASH|self::NOSLASH
     *
     * @var int
     */
    protected $format;

    /**
     * Define content-type header
     *
     * @var string
     */
    protected $contentType = 'text/html; charset=UTF-8';

    /**
     * Define methods and routes
     *
     * @var array
     */
    protected static $valids = array(
        'index'   => array('GET', '/', null),
        'create'  => array('GET', '/create', null),
        'store'   => array('POST', '/', null),
        'edit'    => array('GET', '/{:[^/]+:}/edit', null),
        'show'    => array('GET', '/{:[^/]+:}', null),
        'update'  => array(array('PUT', 'PATCH'), '{:[^/]+:}', null),
        'destroy' => array('DELETE', '/{:[^/]+:}', null)
    );

    /**
     * Define routes based on class methods
     *
     * @param \Inphinit\App $context
     * @throws \Inphinit\Exception
     * @return mixed
     */
    public function route()
    {
        $allowed = array_keys(static::$valids);
        $methods = get_class_methods($this);

        foreach (array_intersect($methods, $allowed) as $method) {
            if (isset(static::$valids[$method])) {
                $route = static::$valids[$method];

                $type = isset($route[2]) ? $route[2] : $this->contentType;

                Route::set($route[0], $route[1], function ($params = null) use ($type, $method) {
                    header('Content-Type: ' . $type);

                    return static::output(
                        call_user_func_array(array($this, $method), func_get_args())
                    );
                });
            }
        }
    }

    /**
     * Define routes based on class methods
     *
     * @throws \Inphinit\Exception
     * @return mixed
     */
    public static function action()
    {
        $self = new static();
        $self->route();
        return $self;
    }

    /**
     * Overwrite output
     *
     * @throws \Inphinit\Exception
     * @return mixed
     */
    protected static function output($output)
    {
        return $output;
    }
}
