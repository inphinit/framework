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
    /** @var int Package description */
    const INFO_DESCRIPTION = 1;

    /** @var int Source type of the package (e.g., git, dist) */
    const INFO_SOURCE = 2;

    /** @var int Package release time */
    const INFO_TIME = 3;

    /** @var int Package type (e.g., library, project, metapackage) */
    const INFO_TYPE = 4;

    /** @var int Source URL or repository path of the package */
    const INFO_URL = 5;

    /** @var int Package version string */
    const INFO_VERSION = 6;

    const META_FILE = "%s/%s-%s.php";

    private $composerPath;
    private $classmapName = 'autoload_classmap.php';
    private $filesName = 'autoload_files.php';
    private $psrFourName = 'autoload_psr4.php';
    private $psrZeroName = 'autoload_namespaces.php';
    private $sourceFiles = array();
    private $sourceLibs = array();
    private $log = array();
    private static $cacheInfo = array();

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
        return $this->classmap() + $this->files() + $this->psr4() + $this->psr0();
    }

    /**
     * Load `./system/boot/namespaces.php` classes
     *
     * @return int|false Returns the total number of loaded packages,
     *                   if `namespaces.php` is not accessible returns `false`
     */
    public function inAutoload()
    {
        if (is_file(INPHINIT_SYSTEM . '/boot/namespaces.php') === false) {
            return false;
        }

        $data = inphinit_sandbox('boot/namespaces.php');

        if (is_array($data)) {
            $this->sourceLibs = $data + $this->sourceLibs;
        }

        return count($this->sourceLibs);
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
            $this->log[] = 'Warning: Unable to load "classmap", maybe your project is not using composer';
            return $results;
        }

        $path = $this->composerPath . $this->classmapName;

        if (is_file($path) === false) {
            $this->log[] = 'Warning: "classmap" not found';
            return $results;
        }

        $data = include $path;

        if (is_array($data) === false) {
            $this->log[] = 'Warning: "classmap" is invalid';
            return $results;
        }

        foreach ($data as $key => $value) {
            if (empty($value) === false) {
                $this->sourceLibs[$key] = $value;
                ++$results;
            }
        }

        $this->log[] = 'Imported ' . $results . ' classes from "classmap"';

        return $results;
    }

    /**
     * Load `autoload_files.php` classes
     *
     * @return int Return total packages loaded
     */
    public function files()
    {
        $results = 0;

        if ($this->composerPath === null) {
            $this->log[] = 'Warning: Unable to load "files", maybe your project is not using composer';
            return $results;
        }

        $path = $this->composerPath . $this->filesName;

        if (is_file($path) === false) {
            $this->log[] = 'Warning: "files" not found';
            return $results;
        }

        $data = include $path;

        if (is_array($data) === false) {
            $this->log[] = 'Warning: "files" is invalid';
            return $results;
        }

        foreach ($data as $id => $value) {
            if (empty($value) === false && isset($this->sourceFiles[$id]) === false) {
                $this->sourceFiles[$id] = $value;
                ++$results;
            }
        }

        $this->log[] = 'Imported ' . $results . ' from "files"';

        return $results;
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

    /**
     * Load `autoload_namespaces.php` classes, used by PSR-0 packages
     *
     * @return int Return total packages loaded, if `autoload_namespaces.php`
     */
    public function psr0()
    {
        return $this->load('psr0', $this->psrZeroName);
    }

    private function load($type, $file)
    {
        $results = 0;

        if ($this->composerPath === null) {
            $this->log[] = 'Warning: Unable to load "' . $type . '", maybe your project is not using composer';
            return $results;
        }

        $path = $this->composerPath . $file;

        if (is_file($path) === false) {
            $this->log[] = 'Warning: "' . $type . '" not found';
            return $results;
        }

        $data = include $path;

        if (is_array($data) === false || Arrays::indexed($data) === false) {
            $this->log[] = 'Warning: "' . $type . '" is invalid';
            return $results;
        }

        foreach ($data as $key => $value) {
            if (isset($value[0]) && is_string($value[0])) {
                $this->sourceLibs[$key] = $value[0];
                ++$results;
            }
        }

        $this->log[] = 'Imported ' . $results . ' classes from "' . $type . '"';

        return $results;
    }

    /**
     * Associate namespace prefix to folder
     *
     * @param string $prefix
     * @param string $path
     * @throws \Inphinit\Exception
     */
    public function setItem($prefix, $path)
    {
        if (!is_string($prefix) || !is_string($path)) {
            throw new Exception('Namespace prefix and path must be strings');
        }

        $this->sourceLibs[$prefix] = $path;
    }

    /**
     * Return array of libs
     *
     * @return array
     */
    public function getLibs()
    {
        return $this->sourceLibs;
    }

    /**
     * Save imported packages path to file in PHP format
     *
     * @param string $path File to save packages paths (e.g., `/foo/namespaces.php`)
     * @return bool
     */
    public function save($path)
    {
        if (count($this->sourceLibs) === 0) {
            return false;
        }

        $libs = $this->sourceLibs;

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

            return $x < $y ? 1 : -1;
        });

        if (is_file($path)) {
            $original = include $path;

            if (is_array($original) && Arrays::indexed($original) === false) {
                $libs += $original;
            }
        }

        $contents = array(
            '<?php',
            '// Namespaces with more separators stay at the top.',
            'return ' . var_export($libs, true) . ";\n"
        );

        return file_put_contents($path, implode("\n", $contents), LOCK_EX) !== false;
    }

    /**
     * Save imported autoload files path to file in PHP format
     *
     * @param string $path      File to save packages paths (e.g., `/foo/files.php`)
     * @param bool $removeEmpty Remove the destination file if there are no files to be included
     * @return bool
     */
    public function saveFiles($path, $removeEmpty = false)
    {
        if (empty($this->sourceFiles)) {
            if ($removeEmpty && is_file($path)) {
                unlink($path);
            }

            return false;
        }

        $contents = array(
            '<?php',
            '// Require autoload files',
        );

        foreach ($this->sourceFiles as $file) {
            $contents[] = "inphinit_sandbox_file('{$file}');";
        }

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
     * Get package info
     *
     * @param string $name Set <vendor>/<package>
     * @param int    $info Set info by constant:
     *                     - INFO_DESCRIPTION
     *                     - INFO_SOURCE
     *                     - INFO_TIME
     *                     - INFO_TYPE
     *                     - INFO_URL
     *                     - INFO_VERSION
     * @param bool   $dev  Set true for get from packages-dev
     * @return string|null
     */
    public static function info($name, $info, $dev = false)
    {
        if (!preg_match('#^([^/]+)/(.*?)$#', $name, $match)) {
            throw new Exception("Invalid package name: {$name}");
        }

        $group = $dev ? 'packages-dev' : 'packages';
        $name = $group . ':' . $name;

        if (isset(self::$cacheInfo[$name]) === false) {
            $folder = 'boot/metadata';
            $vendor = $match[1];
            $package = $match[2];

            $path = sprintf(self::META_FILE, $folder, $group, $vendor);

            $data = inphinit_sandbox($path);

            self::$cacheInfo[$name] = isset($data[$package]) ? $data[$package] : false;
        }

        if (isset(self::$cacheInfo[$name][$info])) {
            return self::$cacheInfo[$name][$info];
        }

        return null;
    }

    /**
     * Update version cache using composer.lock
     *
     * @param string $folder
     */
    public function refreshMetadata($folder = null)
    {
        if ($folder === null) {
            $folder = INPHINIT_SYSTEM . '/boot/metadata';
        }

        if (is_dir($folder) === false) {
            throw new Exception("{$folder} not exists");
        }

        if (is_writable($folder) === false) {
            throw new Exception("{$folder} is not writable");
        }

        $file = INPHINIT_ROOT . '/composer.lock';

        if (is_file($file) === false) {
            throw new Exception('composer.lock not found');
        }

        $contents = file_get_contents($file);

        if ($contents === false) {
            throw new Exception('composer.lock can\'t be read.');
        }

        $lock = json_decode($contents);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error parsing composer.lock');
        }

        self::createMetadata($lock, $folder, 'packages');
        self::createMetadata($lock, $folder, 'packages-dev');
    }

    private static function createMetadata($lock, $folder, $from)
    {
        $vendors = array();

        if (isset($lock->{$from})) {
            foreach ($lock->{$from} as $package) {
                if (strpos($package->name, '/') === false) {
                    continue;
                }

                list($vendor, $name) = explode('/', $package->name, 2);

                if (isset($vendors[$vendor]) === false) {
                    $vendors[$vendor] = array();
                }

                $vendors[$vendor][$name] = array(
                    self::INFO_DESCRIPTION => isset($package->description) ? $package->description : null,
                    self::INFO_SOURCE => isset($package->source->type) ? $package->source->type : null,
                    self::INFO_TIME => isset($package->time) ? $package->time : null,
                    self::INFO_TYPE => isset($package->type) ? $package->type : null,
                    self::INFO_URL => isset($package->source->url) ? $package->source->url : null,
                    self::INFO_VERSION => isset($package->version) ? $package->version : null,
                );
            }
        }

        foreach ($vendors as $vendor => $packages) {
            $path = sprintf(self::META_FILE, $folder, $from, $vendor);

            $contents = "<?php\nreturn " . var_export($packages, true) . ";\n";

            if (file_put_contents($path, $contents, LOCK_EX) === false) {
                throw new Exception("Failed to write metadata file: {$path}", 0, 3);
            }
        }

        $vendors = null;
    }
}
