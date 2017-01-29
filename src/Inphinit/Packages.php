<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Packages implements \IteratorAggregate
{
    private $composerPath;
    private $classmapName = 'autoload_classmap.php';
    private $psrZeroName  = 'autoload_namespaces.php';
    private $psrFourName  = 'autoload_psr4.php';
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
        if (is_dir($path) === false) {
            throw new \InvalidArgumentException('Composer path is not accessible: ' . $path);
        }

        $this->composerPath = $path;
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
                    $this->libs[self::addSlashPackage($key)] = $value;
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
        if (is_writeable($path) === false) {
            throw new \InvalidArgumentException('This path is not writabled: ' . $path);
        }

        if (count($this->libs) === 0) {
            return false;
        }

        $handle = fopen($path, 'w');
        $eol = chr(10);

        fwrite($handle, '<?php' . $eol . 'return array(');

        $first = true;

        foreach ($this->libs as $key => $value)
        {
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

    private static function addSlashPackage($prefix)
    {
        return str_replace('\\', '\\\\', $prefix);
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
                $this->libs[self::addSlashPackage($key)] = $value[0];
                $i++;
            }
        }

        return $i;
    }

    public function __destruct()
    {
        $this->libs = null;
    }
}