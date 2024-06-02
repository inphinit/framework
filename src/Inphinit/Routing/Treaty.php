<?php
namespace Inphinit\Routing;

use Inphinit\App;

abstract class Treaty
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
     * Define regex for match public methods from controller
     *
     * @var string
     */
    protected static $valids = '#^(delete|get|head|options|patch|post|put)([A-Z0-9]\w+)$#';

    private $context;

    /**
     * Define routes based on class methods
     *
     * @param \Inphinit\App $context
     * @throws \Inphinit\Exception
     * @return mixed
     */
    public function route(App $context)
    {
        $this->context = $context;

        $invalid = true;
        $analysis = new \ReflectionClass($this);

        foreach ($analysis->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $callback = $method->getName();

            if ($method->isStatic() === false && preg_match(self::$valids, $callback, $match)) {
                $this->putRoute(strtoupper($match[1]), '/' . $match[2], $callback);
                $invalid = false;
            }
        }

        if ($invalid) {
            throw new \Inphinit\Exception('Invalid controller', 0, 2);
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

    /**
     * Overwrite path parser
     *
     * @param string $path
     * @return string
     */
    protected static function parsePath($path)
    {
        return strtolower(preg_replace('#([a-z0-9])([A-Z])#', '$1-$2', $path));
    }

    private function putRoute($method, $path, $callback)
    {
        $callback = array($this, $callback);

        if ($path !== '/Index') {
            $path = self::parsePath($path);
        } else {
            $path = '/';
        }

        if ($this->format) {
            $format = $this->format;
        } else {
            $format = self::SLASH|self::NOSLASH;
        }

        if ($format & self::NOSLASH) {
            $this->context->action($method, $path, $callback);
        }

        if ($path !== '/' && $format & self::SLASH) {
            $this->context->action($method, $path . '/', $callback);
        }
    }
}
