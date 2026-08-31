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
     * Fill libs from `./system/boot/namespaces.php` source.
     *
     * @return int|false Returns the total number of loaded packages,
     *                   if `namespaces.php` is not accessible returns `false`
     */
    public function boot()
    {
        if (is_file(INPHINIT_SYSTEM . '/boot/namespaces.php') === false) {
            return false;
        }

        $data = inphinit_sandbox('boot/namespaces.php');

        if (is_array($data) === false || Arrays::indexed($data)) {
            $this->log[] = 'Warning: Unexpected contents in `boot/namespaces.php`';
            return 0;
        }

        $this->sourceLibs = $data + $this->sourceLibs;

        return count($this->sourceLibs);
    }

    /**
     * Fill libs from `autoload_classmap.php` source.
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
     * Fill script files from `autoload_files.php` source.
     *
     * @return int Return total script files loaded
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
        return $this->loadPsr('psr4', $this->psrFourName, null);
    }

    /**
     * Load `autoload_namespaces.php` classes, used by PSR-0 packages
     *
     * @return int Return total packages loaded, if `autoload_namespaces.php`
     */
    public function psr0()
    {
        return $this->loadPsr('psr0', $this->psrZeroName, '_');
    }

    private function loadPsr($type, $file, $separator)
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
        $dA = strpos($a, '\\') !== false ? '\\' : '_';
        $dB = strpos($b, '\\') !== false ? '\\' : '_';

        $parts = explode($dA, $a, 2);
        $topA = $parts[0];

        $parts = explode($dB, $b, 2);
        $topB = $parts[0];

        // Group by top-level directory, alphabetically
        $topCmp = strnatcasecmp($topA, $topB);

        if ($topCmp !== 0) {
            return $topCmp;
        }

        // Within the same top-level dir, deepest paths first
        $depthA = substr_count($a, $dA);
        $depthB = substr_count($b, $dB);

        if ($depthA !== $depthB) {
            return $depthA < $depthB ? 1 : -1;
        }

        // Same depth -> alphabetical on the remaining path
        $restA = substr($a, strlen($topA) + 1);
        $restB = substr($b, strlen($topB) + 1);

        return strnatcasecmp($restA, $restB);
    }
}
