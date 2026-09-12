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
use Inphinit\Utility\PropertyAccessor;

class Package
{
    /** @var int Package description */
    const DESCRIPTION = 1;

    /** @var int Source type of the package (e.g., git, dist) */
    const SOURCE = 2;

    /** @var int Package release time */
    const TIME = 3;

    /** @var int Package type (e.g., library, project, metapackage) */
    const TYPE = 4;

    /** @var int Source URL or repository path of the package */
    const URL = 5;

    /** @var int Package version string */
    const VERSION = 6;

    const META_FILE = '%s/(%s)-%s.php';

    private $metadataDir;
    private $packages;
    private $packagesDev;

    private static $cacheInfo = array();

    public function __construct()
    {
        $metadata_dir = INPHINIT_SYSTEM . '/boot/metadata';

        if (is_dir($metadata_dir) === false) {
            throw new Exception($metadata_dir . ' not exists');
        }

        if (is_writable($metadata_dir) === false) {
            throw new Exception($metadata_dir . ' is not writable');
        }

        $this->metadataDir = $metadata_dir;

        $this->readLock();
    }

    /**
     * Get package info
     *
     * @param string $name Set <vendor>/<package>
     * @param int    $type Set info by constant:
     *                     - DESCRIPTION
     *                     - SOURCE
     *                     - TIME
     *                     - TYPE
     *                     - URL
     *                     - VERSION
     * @param bool   $dev  Set true for get from packages-dev
     * @return string|null
     */
    public static function info($name, $type, $dev = false)
    {
        if (strpos($name, '/') === false) {
            throw new Exception('Invalid package name: ' . $name);
        }

        $group = $dev ? 'packages-dev' : 'packages';
        $gname = $group . ':' . $name;

        if (isset(self::$cacheInfo[$gname]) === false) {
            list($vendor, $package) = explode('/', $name, 2);

            $path = sprintf(self::META_FILE, 'boot/metadata', $group, $vendor);

            $data = inphinit_sandbox($path);

            self::$cacheInfo[$gname] = isset($data[$package]) ? $data[$package] : false;
        }

        if (isset(self::$cacheInfo[$gname][$type])) {
            return self::$cacheInfo[$gname][$type];
        }

        return null;
    }

    /**
     * Caches metadata from composer.lock
     *
     * @throws \Inphinit\Exception
     */
    public function cache()
    {
        // Loads composer.lock -> `"packages": [...]`
        $this->createCache('packages', $this->packages);

        // Loads composer.lock -> `"packages-dev": [...]`
        $this->createCache('packages-dev', $this->packagesDev);
    }

    /**
     * Clear metadata cache
     *
     * @return bool
     */
    public function clear()
    {
        $search = sprintf(self::META_FILE, $this->metadataDir, '(packages*)', '*');

        $files = glob($search, GLOB_ERR|GLOB_NOSORT);

        if ($files === false) {
            return false;
        }

        $total = 0;

        foreach ($files as $file) {
            if (is_file($file) && unlink($file)) {
                ++$total;
            }
        }

        if (count($files) !== $total) {
            return false;
        }

        self::$cacheInfo = array();

        return true;
    }

    private function createCache($from, $data)
    {
        if ($data ===  null) {
            return null;
        }

        $vendors = array();
        $meta_dir = $this->metadataDir;

        foreach ($data as $package) {
            if (strpos($package->name, '/') === false) {
                continue;
            }

            list($vendor, $name) = explode('/', $package->name, 2);

            if (isset($vendors[$vendor]) === false) {
                $vendors[$vendor] = array();
            }

            $vendors[$vendor][$name] = array(
                self::DESCRIPTION => self::getNonEmptyString('description', $package),
                self::SOURCE => self::getNonEmptyString('source.type', $package),
                self::TIME => self::getNonEmptyString('time', $package),
                self::TYPE => self::getNonEmptyString('type', $package),
                self::URL => self::getNonEmptyString('source.url', $package),
                self::VERSION => self::getNonEmptyString('version', $package)
            );
        }

        foreach ($vendors as $vendor => $packages) {
            $path = sprintf(self::META_FILE, $meta_dir, $from, $vendor);

            $contents = "<?php\nreturn " . var_export($packages, true) . ";\n";

            if (file_put_contents($path, $contents, LOCK_EX) === false) {
                throw new Exception('Failed to write metadata file: ' . $path, 0, 3);
            }
        }
    }

    private static function getNonEmptyString($path, $package)
    {
        $value = PropertyAccessor::getValue($path, $package);

        if (is_string($value) === false || trim($value) === '') {
            return null;
        }

        return $value;
    }

    private function readLock()
    {
        $lock_path = INPHINIT_ROOT . '/composer.lock';

        if (is_file($lock_path) === false) {
            throw new Exception('No such file: composer.lock', 0, 3);
        }

        $contents = file_get_contents($lock_path);

        if ($contents === false) {
            throw new Exception('composer.lock can\'t be read', 0, 3);
        }

        $data = json_decode($contents);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error parsing composer.lock', 0, 3);
        }

        $this->packages = self::readFrom('packages', true, $data);
        $this->packagesDev = self::readFrom('packages-dev', false, $data);
    }

    private static function readFrom($from, $required, $data)
    {
        if (isset($data->{$from})) {
            // An index array is expected
            if (is_array($data->{$from}) === false || Arrays::indexed($data->{$from}) === false) {
                throw new Exception('Invalid ' . $from . ' key in composer.lock', 0, 4);
            }

            return $data->{$from};
        } elseif ($required) {
            throw new Exception('Missing ' . $from . ' key in composer.lock', 0, 4);
        }

        return null;
    }
}
