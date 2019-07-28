<?php
/*
 * Inphinit
 *
 * Copyright (c) 2019 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Packages implements \IteratorAggregate
{
    private $composerPath;
    private $classmapName = 'autoload_classmap.php';
    private $psrZeroName = 'autoload_namespaces.php';
    private $psrFourName = 'autoload_psr4.php';
    private $logs = array();
    private $libs;

    /**
     * Create a `Inphinit\Packages` instance.
     *
     * @param string $path Setup composer path, like `./vendor/composer`
     * @return void
     */
    public function __construct($path)
    {
        if (empty($path) || is_dir($path) === false) {
            throw new \InvalidArgumentException(empty($path) ? 'Path is empty' : 'Composer path is not accessible: ' . $path);
        }

        $this->composerPath = str_replace('\\', '/', realpath($path)) . '/';
        $this->libs = new \ArrayIterator(array());
    }

    /**
     * Get log
     *
     * @return array
     */
    public function logs()
    {
        return $this->log;
    }

    /**
     * Auto import composer packages
     *
     * @return int
     */
    public function auto()
    {
        return 0 + $this->classmap() + $this->psr0() + $this->psr4();
    }

    /**
     * Load `./system/boot/namespaces.php` classes
     *
     * @return int|bool Return total packages loaded, if `namespaces.php`
     *                  is not accessible return `false`
     */
    public function inAutoload()
    {
        $path = INPHINIT_PATH . 'boot/namespaces.php';
        $i = 0;

        if (is_file($path)) {
            $data = include $path;

            if (is_array($data)) {
                $this->libs = new \ArrayIterator($data + $this->libs->getArrayCopy());
            }

            return count($this->libs);
        }

        return false;
    }

    /**
     * Load `autoload_classmap.php` classes
     *
     * @return int|bool Return total packages loaded, if `autoload_classmap.php`
     *                  is not accessible return `false`
     */
    public function classmap()
    {
        $path = $this->composerPath . $this->classmapName;
        $i = 0;

        if (is_file($path)) {
            $data = include $path;

            foreach ($data as $key => $value) {
                if (false === empty($value)) {
                    $this->libs[addcslashes($key, '\\')] = $value;
                    $i++;
                }
            }

            $this->log[] = 'Imported ' . $i . ' classes from classmap';
            return $i;
        }

        $this->log[] = 'Warn: classmap not found';
        return false;
    }

    /**
     * Load `autoload_namespaces.php` classes, used by PSR-0 packages
     *
     * @return int|bool Return total packages loaded, if `autoload_namespaces.php`
     *                  is not accessible return `false`
     */
    public function psr0()
    {
        $i = $this->load($this->composerPath . $this->psrZeroName);

        if ($i !== false) {
            $this->log[] = 'Imported ' . $i . ' classes from psr0';
            return $i;
        }

        $this->log[] = 'Warn: psr0 not found';
        return false;
    }

    /**
     * Load `autoload_psr4.php` classes, used by PSR-4 packages
     *
     * @return int|bool Return total packages loaded, if `autoload_psr4.php`
     *                  is not accessible return `false`
     */
    public function psr4()
    {
        $i = $this->load($this->composerPath . $this->psrFourName);

        if ($i !== false) {
            $this->log[] = 'Imported ' . $i . ' classes from psr4';
            return $i;
        }

        $this->log[] = 'Warn: psr4 not found';
        return false;
    }

    /**
     * Save imported packages path to file in PHP format
     *
     * @param string $path File to save packages paths, eg. `/foo/namespaces.php`
     * @return bool
     */
    public function save($path)
    {
        if (count($this->libs) === 0) {
            return false;
        }

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \InvalidArgumentException('This path is not writabled: ' . $path);
        }

        $first = true;
        $eol = chr(10);

        fwrite($handle, '<?php' . $eol . 'return array(');

        foreach ($this->libs as $key => $value) {
            $value = self::relativePath($value);

            fwrite($handle, ($first ? '' : ',') . $eol . "    '" . $key . "' => '" . $value . "'");

            $first = false;
        }

        fwrite($handle, $eol . ');' . $eol);
        fclose($handle);

        return true;
    }

    /**
     * Allow iteration with `for`, `foreach` and `while`
     *
     * Example:
     * <pre>
     * <code>
     * $foo = new Packages;
     * $foo->inAutoload(); //Get imported classes
     *
     * foreach ($foo as $namespace => $path) {
     *     var_dump($namespace, $path);
     *     echo EOL;
     * }
     * </code>
     * </pre>
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return $this->libs;
    }

    private static function relativePath($path)
    {
        $path = strtr($path, '\\', '/');

        if (defined('INPHINIT_PATH') && strpos($path, INPHINIT_PATH) === 0) {
            $path = substr($path, strlen(INPHINIT_PATH));
        }

        return $path;
    }

    /**
     * Get package version from composer.lock file
     *
     * @param string $name set package for detect version
     * @return string|null
     */
    public static function version($name)
    {
        $file = INPHINIT_ROOT . 'composer.lock';
        $data = is_file($file) ? json_decode(file_get_contents($file)) : false;

        if (empty($data->packages)) {
            return null;
        }

        $version = null;

        foreach ($data->packages as $package) {
            if ($package->name === $name) {
                $version = $package->version;
                break;
            }
        }

        $data = null;

        return $version;
    }

    private function load($path)
    {
        if (false === is_file($path)) {
            return false;
        }

        $data = include $path;
        $i = 0;

        foreach ($data as $key => $value) {
            if (isset($value[0]) && is_string($value[0])) {
                $this->libs[addcslashes($key, '\\')] = $value[0];
                $i++;
            }
        }

        return $i;
    }

    public function __destruct()
    {
        $this->logs = $this->libs = null;
    }
}
