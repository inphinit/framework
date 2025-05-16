<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

use Inphinit\Utility\Arrays;

class Packages
{
    private static $composerLock;
    private $composerPath;
    private $classmapName = 'autoload_classmap.php';
    private $psrZeroName = 'autoload_namespaces.php';
    private $psrFourName = 'autoload_psr4.php';
    private $libs = array();
    private $log = array();

    public function __construct()
    {
        $path = INPHINIT_SYSTEM . '/vendor/composer';

        $this->composerPath = str_replace('\\', '/', realpath($path)) . '/';
    }

    /**
     * Change composer path
     *
     * @param string $path Set composer path, like `vendor/composer`.
     * @throws \Inphinit\Exception
     * @return void
     */
    public function setComposer($path)
    {
        if (is_dir($path) === false) {
            throw new Exception('Composer path is not accessible: ' . $path);
        }

        $this->composerPath = str_replace('\\', '/', realpath($path)) . '/';
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
     * @return int|false Returns the total number of loaded packages, if `namespaces.php`
     *                   is not accessible returns `false`
     */
    public function inAutoload()
    {
        $path = INPHINIT_SYSTEM . '/boot/namespaces.php';

        if (is_file($path)) {
            $data = include $path;

            if (is_array($data)) {
                $this->libs = $data + $this->libs;
            }

            return count($this->libs);
        }

        return false;
    }

    /**
     * Load `autoload_classmap.php` classes
     *
     * @return int Return total packages loaded, if `autoload_classmap.php`
     *             is not accessible returns `false`
     */
    public function classmap()
    {
        $path = $this->composerPath . $this->classmapName;
        $i = 0;

        if (is_file($path)) {
            $data = include $path;

            foreach ($data as $key => $value) {
                if (empty($value) === false) {
                    $this->libs[$key] = $value;
                    ++$i;
                }
            }

            $this->log[] = 'Imported ' . $i . ' classes from classmap';
        } else {
            $this->log[] = 'Warn: classmap not found';
        }

        return $i;
    }

    /**
     * Load `autoload_namespaces.php` classes, used by PSR-0 packages
     *
     * @return int Return total packages loaded, if `autoload_namespaces.php`
     *             is not accessible returns `false`
     */
    public function psr0()
    {
        $i = $this->load($this->composerPath . $this->psrZeroName);

        if ($i !== false) {
            $this->log[] = 'Imported ' . $i . ' classes from psr0';
        } else {
            $this->log[] = 'Warn: psr0 not found';
        }

        return $i;
    }

    /**
     * Load `autoload_psr4.php` classes, used by PSR-4 packages
     *
     * @return int Return total packages loaded, if `autoload_psr4.php`
     *             is not accessible returns `false`
     */
    public function psr4()
    {
        $i = $this->load($this->composerPath . $this->psrFourName);

        if ($i !== false) {
            $this->log[] = 'Imported ' . $i . ' classes from psr4';
        } else {
            $this->log[] = 'Warn: psr4 not found';
        }

        return $i;
    }

    /**
     * Associate namespace prefix to folder
     *
     * @param string $prefix
     * @param string $path
     */
    public function setItem($prefix, $path)
    {
        $this->libs[$prefix] = $path;
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

        $libs = $this->libs;

        foreach ($libs as &$value) {
            $value = self::relativePath($value);
        }

        // Namespaces with more separators stay at the top.
        uksort($libs, function ($a, $b) {
            $x = substr_count($a, strpos($a, '\\') === false ? '\\' : '_');
            $y = substr_count($b, strpos($b, '\\') === false ? '\\' : '_');

            return $y - $x;
        });

        if (is_file($path)) {
            $original = include $path;

            if (Arrays::associative($original)) {
                $libs += $original;
            }
        }

        $contents = [
            '<?php',
            '// Namespaces with more separators stay at the top.',
            'return ' . var_export($libs, true) . ";\n"
        ];

        return file_put_contents($path, implode("\n", $contents), LOCK_EX) !== false;
    }

    /**
     * Return array of libs
     *
     * @return array
     */
    public function getLibs()
    {
        return $this->libs;
    }

    /**
     * Get package version from composer.lock file
     *
     * @param string $name Set package for detect version
     * @return string|null
     */
    public static function version($name)
    {
        if (self::$composerLock === null) {
            $file = INPHINIT_ROOT . '/composer.lock';

            if (is_file($file)) {
                self::$composerLock = json_decode(file_get_contents($file));
            }
        }

        $data = self::$composerLock;

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
        if (is_file($path) === false) {
            return false;
        }

        $data = include $path;
        $i = 0;

        foreach ($data as $key => $value) {
            if (isset($value[0]) && is_string($value[0])) {
                $this->libs[$key] = $value[0];
                ++$i;
            }
        }

        return $i;
    }

    private static function relativePath($path)
    {
        $path = str_replace('\\', '/', $path);
        $system = INPHINIT_SYSTEM . '/';

        if (strpos($path, $system) === 0) {
            $path = substr($path, strlen($system));
        }

        return $path;
    }

    public function __destruct()
    {
        $this->log = $this->libs = null;
    }
}
