<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

use Inphinit\File;

class Dir implements \IteratorAggregate
{
    private $iterator;

    /**
     * Return items from a folder
     *
     * @param string $path
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function __construct($path)
    {
        $data = array();

        if (false === is_dir($path)) {
            throw new Exception('Folder not found', 2);
        }

        $path = strtr(realpath($path), '\\', '/');
        $path = rtrim($path, '/') . '/';

        $handle = opendir($path);

        if ($handle) {
            while (($name = readdir($handle)) !== false) {
                if ($name !== '.' && $name !== '..') {
                    $current = $path . $name;

                    $data[] = (object) array(
                        'type' => filetype($current),
                        'path' => $current,
                        'name' => $name
                    );
                }
            }

            closedir($handle);

            $this->iterator = new \ArrayIterator($data);
        }
    }

    /**
     * Allow iteration with `for`, `foreach` and `while`
     *
     * Example:
     * <pre>
     * <code>
     * $foo = new Dir('/home/foo/bar/baz/');
     *
     * foreach ($foo as $value) {
     *      var_dump($value);
     *      echo EOL;
     * }
     * </code>
     * </pre>
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return $this->iterator;
    }

    /**
     * Return items from root project folder (probably, will depend on the setting
     * of the "INPHINIT_ROOT" constant)
     *
     * @return \Inphinit\Experimental\Dir
     */
    public static function root()
    {
        return new self(INPHINIT_ROOT);
    }

    /**
     * Return items from storage folder
     *
     * @return \Inphinit\Experimental\Dir
     */
    public static function storage()
    {
        return new self(INPHINIT_PATH . 'storage/');
    }

    /**
     * Return items from application folder
     *
     * @return \Inphinit\Experimental\Dir
     */
    public static function application()
    {
        return new self(INPHINIT_PATH . 'application/');
    }

    public function __destruct()
    {
        $this->iterator = null;
    }
}
