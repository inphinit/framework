<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Packages;

use Inphinit\Exception;
use Inphinit\Utility\Arrays;

class Import
{
    private $composerPath;

    private $classmapName = 'autoload_classmap.php';
    private $psrFourName = 'autoload_psr4.php';
    private $psrZeroName = 'autoload_namespaces.php';
    private $sourceLibs = array();

    private $filesName = 'autoload_files.php';
    private $sourceFiles = array();

    private $log = array();

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

        if (empty($data->config->{'vendor-dir'})) {
            $vendor = INPHINIT_SYSTEM . '/vendor';
        } else {
            $vendor = self::resolveVendor($data->config->{'vendor-dir'});
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

        // An associative array is expected. If anything else is received, an exception will be thrown
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

    /**
     * Associate namespace prefix to folder class namespace to file
     *
     * @param string $prefix
     * @param string $path
     * @param string $delimiter
     * @throws \Inphinit\Exception
     */
    public function setItem($prefix, $path, $delimiter = '\\')
    {
        if (is_string($prefix) === false || is_string($path) === false) {
            throw new Exception('Namespace prefix and path must be strings');
        }

        $prefix = trim($prefix, $delimiter) . $delimiter;

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
     * Save imported packages path to file in PHP format ()
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
        uksort($libs, array('\\' . __CLASS__, 'sortLibs'));

        $contents = array(
            '<?php',
            '// Namespaces with more separators stay at the top.',
            'return ' . var_export($libs, true) . ";\n"
        );

        return file_put_contents($path, implode("\n", $contents), LOCK_EX) !== false;
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
            $this->log[] = 'Warning: "files" not found (' . $path . ')';
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
            $contents[] = 'inphinit_sandbox_file(' . var_export($file, true) . ');';
        }

        return file_put_contents($path, implode("\n", $contents), LOCK_EX) !== false;
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

        // An associative array is expected
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

        $this->log[] = "Imported {$results} from \"{$type}\"";

        return $results;
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

    private static function resolveVendor($vendor)
    {
        if (is_string($vendor) === false) {
            $type = Inspector::type($enable);
            throw new Exception("`vendor-dir:` expects a string, {$type} given", 0, 3);
        }

        // It currently does not support interpolation or resolution of $HOME and USERPROFILE
        if (preg_match('#(\$|\{|\})#', $vendor) === 1) {
            throw new Exception('"vendor-dir" contains invalid characters: ' . $vendor, 0, 3);
        }

        if (preg_match('#^(\/|[A-Za-z]\:|\\\\)#', $vendor) !== 1) {
            return INPHINIT_ROOT . '/' . $vendor;
        }

        return $vendor;
    }

    private static function sortLibs($entry1, $entry2)
    {
        $sep_entry1 = strpos($entry1, '\\') !== false ? '\\' : '_';
        $sep_entry2 = strpos($entry2, '\\') !== false ? '\\' : '_';

        $parts = explode($sep_entry1, $entry1, 2);
        $top_entry1 = $parts[0];

        $parts = explode($sep_entry2, $entry2, 2);
        $top_entry2 = $parts[0];

        // Group by top-level directory, alphabetically
        $top_cmp = strnatcasecmp($top_entry1, $top_entry2);

        if ($top_cmp !== 0) {
            return $top_cmp;
        }

        // Within the same top-level dir, deepest paths first
        $depth_entry1 = substr_count($entry1, $sep_entry1);
        $depth_entry2 = substr_count($entry2, $sep_entry2);

        if ($depth_entry1 !== $depth_entry2) {
            return $depth_entry1 < $depth_entry2 ? 1 : -1;
        }

        // Same depth -> alphabetical on the remaining path
        $restA = substr($entry1, strlen($top_entry1) + 1);
        $restB = substr($entry2, strlen($top_entry2) + 1);

        return strnatcasecmp($restA, $restB);
    }
}
