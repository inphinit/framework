<?php
namespace Inphinit\Routing;

use Inphinit\App;

abstract class Resource
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
        'edit'    => array('GET', '/<id>/edit', null),
        'show'    => array('GET', '/<id>', null),
        'update'  => array(array('PUT', 'PATCH'), '<id>', null),
        'destroy' => array('DELETE', '/<id>', null)
    );

    /**
     * Define routes based on class methods
     *
     * @param \Inphinit\App $context
     * @return mixed
     */
    public function route(App $context)
    {
        $allowed = array_keys(self::$valids);
        $methods = get_class_methods($this);

        foreach (array_intersect($methods, $allowed) as $method) {
            if (isset(self::$valids[$method])) {
                $route = self::$valids[$method];

                $type = isset($route[2]) ? $route[2] : $this->contentType;

                $context->action($route[0], $route[1], function ($params = null) use ($type, $method) {
                    header('Content-Type: ' . $type);

                    return call_user_func_array(
                        array($this, $method),
                        array($params)
                    );
                });
            }
        }
    }

    /**
     * Define routes based on class methods
     *
     * @param \Inphinit\App $context
     * @throws \Inphinit\Exception
     * @return mixed
     */
    public static function action(App $context)
    {
        $self = new static();
        $self->route($context);
        return $self;
    }
}
