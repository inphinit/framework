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
        $path = realpath(INPHINIT_SYSTEM . '/vendor/composer');

        if ($path !== false) {
            $this->composerPath = str_replace('\\', '/', $path) . '/';
        }
    }

    /**
     * Change composer path
     *
     * @param string $path Set composer path, like `vendor/composer`
     * @throws \Inphinit\Exception
     * @return void
     */
    public function setComposer($path)
    {
        $path = realpath($path);

        if ($path === false || is_dir($path) === false) {
            throw new Exception('Composer path is not accessible: ' . $path);
        }

        $this->composerPath = str_replace('\\', '/', $path) . '/';
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
        return $this->classmap() + $this->psr0() + $this->psr4();
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
     * @return int Return total packages loaded
     */
    public function classmap()
    {
        $results = 0;

        if ($this->composerPath === null) {
            $this->log[] = 'Warn: Unable to load classmap, maybe your project is not using composer';
            return $results;
        }

        $path = $this->composerPath . $this->classmapName;

        if (is_file($path) === false) {
            $this->log[] = 'Warn: classmap not found';
            return $results;
        }

        $data = include $path;

        if (is_array($data) === false) {
            $this->log[] = 'Warn: classmap is invalid';
            return $results;
        }

        foreach ($data as $key => $value) {
            if (empty($value) === false) {
                $this->libs[$key] = $value;
                ++$results;
            }
        }

        $this->log[] = 'Imported ' . $results . ' classes from classmap';

        return $results;
    }

    /**
     * Load `autoload_namespaces.php` classes, used by PSR-0 packages
     *
     * @return int Return total packages loaded, if `autoload_namespaces.php`
     */
    public function psr0()
    {
        return $this->load('psr0', $this->psrZeroName);
    }

    /**
     * Load `autoload_psr4.php` classes, used by PSR-4 packages
     *
     * @return int Return total packages loaded
     */
    public function psr4()
    {
        return $this->load('psr4', $this->psrFourName);
    }

    private function load($type, $file)
    {
        $results = 0;

        if ($this->composerPath === null) {
            $this->log[] = 'Warn: Unable to load ' . $type . ', maybe your project is not using composer';
            return $results;
        }

        $path = $this->composerPath . $file;

        if (is_file($path) === false) {
            $this->log[] = 'Warn: ' . $type . ' not found';
            return $results;
        }

        $data = include $path;

        if (is_array($data) === false || Arrays::indexed($data) === false) {
            $this->log[] = 'Warn: ' . $type . ' is invalid';
            return $results;
        }

        foreach ($data as $key => $value) {
            if (isset($value[0]) && is_string($value[0])) {
                $this->libs[$key] = $value[0];
                ++$results;
            }
        }

        $this->log[] = 'Imported ' . $results . ' classes from ' . $type;

        return $results;
    }

    /**
     * Associate namespace prefix to folder
     *
     * @param string $prefix
     * @param string $path
     * @throws \Inphinit\Exception
     * @return void
     */
    public function setItem($prefix, $path)
    {
        if (!is_string($prefix) || !is_string($path)) {
            throw new Exception('Namespace prefix and path must be strings');
        }

        $this->libs[$prefix] = $path;
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
            $x = substr_count($a, strpos($a, '\\') !== false ? '\\' : '_');
            $y = substr_count($b, strpos($b, '\\') !== false ? '\\' : '_');

            if ($x === $y) {
                return 0;
            }

            return $x < $y ? -1 : 1;
        });

        if (is_file($path)) {
            $original = include $path;

            if (is_array($original) && Arrays::indexed($original) === false) {
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

    private static function relativePath($path)
    {
        $path = str_replace('\\', '/', $path);
        $system = INPHINIT_SYSTEM . '/';

        if (strpos($path, $system) === 0) {
            $path = substr($path, strlen($system));
        }

        return $path;
    }

    /**
     * Get package version from composer.lock file
     *
     * @param string $name Set package for detect version
     * @return string|false
     */
    public static function version($name)
    {
        if (self::$composerLock === null) {
            $file = INPHINIT_ROOT . '/composer.lock';

            if (is_file($file) === false) {
                return false;
            }

            $contents = file_get_contents($file);

            if ($contents === false) {
                return false;
            }

            $contents = json_decode($contents);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error parsing composer.lock');
            }

            self::$composerLock = $contents;
        }

        $version = self::findVersion('packages', $name);

        if ($version === false) {
            $version = self::findVersion('packages-dev', $name);
        }

        return $version;
    }

    private static function findVersion($from, $name)
    {
        if (isset(self::$composerLock->{$from})) {
            foreach (self::$composerLock->{$from} as $package) {
                if ($package->name === $name) {
                    return $package->version;
                }
            }
        }

        return false;
    }
}
