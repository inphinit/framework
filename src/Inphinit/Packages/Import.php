<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Packages;

use Inphinit\Utility\Arrays;

class Import
{
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
        $json_path = INPHINIT_ROOT . '/composer.json';

        $contents = file_get_contents($json_path);

        if ($contents === false) {
            throw new Exception('composer.json can\'t be read.');
        }

        $data = json_decode($contents);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error parsing composer.json');
        }

        if (empty($data->config->{'vendor-dir'}) === false) {
            $vendor = $data->config->{'vendor-dir'};
        } else {
            $vendor = INPHINIT_ROOT . '/vendor';
        }

        $composer_path = realpath($vendor . '/composer');

        if ($composer_path) {
            $this->composerPath = $composer_path . DIRECTORY_SEPARATOR;
        }
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
            $this->log[] = "Warning: \"classmap\" not found ({$path})";
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
            $this->log[] = "Warning: \"files\" not found ({$path})";
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
        return $this->load('psr4', $this->psrFourName, null);
    }

    /**
     * Load `autoload_namespaces.php` classes, used by PSR-0 packages
     *
     * @return int Return total packages loaded, if `autoload_namespaces.php`
     */
    public function psr0()
    {
        return $this->load('psr0', $this->psrZeroName, '_');
    }

    private function load($type, $file, $separator)
    {
        $results = 0;

        if ($this->composerPath === null) {
            $this->log[] = 'Warning: Unable to load "' . $type . '", maybe your project is not using composer';
            return $results;
        }

        $path = $this->composerPath . $file;

        if (is_file($path) === false) {
            $this->log[] = "Warning: \"{$type}\" not found ({$path})";
            return $results;
        }

        $data = include $path;

        if (is_array($data) === false || Arrays::indexed($data)) {
            $this->log[] = 'Warning: "' . $type . '" is invalid';
            return $results;
        }

        foreach ($data as $key => $value) {
            if (isset($value[0]) && is_string($value[0])) {
                if ($separator) {
                    $key = str_replace(array('_', '\\'), $separator, $key);
                    $key = rtrim($key, $separator) . $separator;
                }

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

        // Namespaces with more separators stay at the top
        uksort($libs, array($this, 'sortLibs'));

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

    private function sortLibs($a, $b)
    {
        $x = substr_count($a, strpos($a, '\\') !== false ? '\\' : '_');
        $y = substr_count($b, strpos($b, '\\') !== false ? '\\' : '_');

        if ($x === $y) {
            return 0;
        }

        return $x < $y ? 1 : -1;
    }
}
