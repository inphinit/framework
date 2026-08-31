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

    const META_FILE = '%s/%s-%s.php';

    private static $cacheInfo = array();

    private $metadataDir;

    public function __construct()
    {
        $lock_path = INPHINIT_ROOT . '/composer.lock';

        $metadata_dir = INPHINIT_SYSTEM . '/boot/metadata';

        if (is_dir($metadata_dir) === false) {
            throw new Exception("{$metadata_dir} not exists");
        }

        if (is_writable($metadata_dir) === false) {
            throw new Exception("{$metadata_dir} is not writable");
        }

        $this->metadataDir = $metadata_dir;

        $this->readJson($lock_path);
    }

    /**
     * Get package info
     *
     * @param string $name Set <vendor>/<package>
     * @param int    $info Set info by constant:
     *                     - DESCRIPTION
     *                     - SOURCE
     *                     - TIME
     *                     - TYPE
     *                     - URL
     *                     - VERSION
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
     * Cache composer.lock data
     *
     * @throws \Inphinit\Exception
     */
    public function cache()
    {
        $this->createCache('packages');
        $this->createCache('packages-dev');
    }

    private function createCache($from)
    {
        $path = $this->metadataDir;
        $vendors = array();

        if (isset($this->packages->{$from})) {
            foreach ($this->packages->{$from} as $package) {
                if (strpos($package->name, '/') === false) {
                    continue;
                }

                list($vendor, $name) = explode('/', $package->name, 2);

                if (isset($vendors[$vendor]) === false) {
                    $vendors[$vendor] = array();
                }

                $vendors[$vendor][$name] = array(
                    self::DESCRIPTION => isset($package->description) ? $package->description : null,
                    self::SOURCE => isset($package->source->type) ? $package->source->type : null,
                    self::TIME => isset($package->time) ? $package->time : null,
                    self::TYPE => isset($package->type) ? $package->type : null,
                    self::URL => isset($package->source->url) ? $package->source->url : null,
                    self::VERSION => isset($package->version) ? $package->version : null,
                );
            }
        }

        foreach ($vendors as $vendor => $packages) {
            $path = sprintf(self::META_FILE, $path, $from, $vendor);

            $contents = "<?php\nreturn " . var_export($packages, true) . ";\n";

            if (file_put_contents($path, $contents, LOCK_EX) === false) {
                throw new Exception("Failed to write metadata file: {$path}", 0, 3);
            }
        }

        $vendors = null;
    }

    private function readJson($lockPath)
    {
        if (is_file($lockPath) === false) {
            throw new Exception('No such file: composer.lock', 0, 3);
        }

        $contents = file_get_contents($lockPath);

        if ($contents === false) {
            throw new Exception('composer.lock can\'t be read', 0, 3);
        }

        $data = json_decode($contents);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error parsing composer.lock', 0, 3);
        }

        if (isset($data->packages) === false) {
            throw new Exception('Missing packages key in composer.lock', 0, 3);
        }

        if (is_array($data->packages) === false || Arrays::indexed($data->packages) === false) {
            throw new Exception('Invalid packages key in composer.lock', 0, 3);
        }

        $this->packages = $data->packages;
    }
}
