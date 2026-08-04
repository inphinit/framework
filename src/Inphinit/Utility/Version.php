<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Utility;

use Inphinit\Exception;

/**
 * @property string     $major
 * @property string     $minor
 * @property string     $patch
 * @property array|null $prerelease
 * @property array|null $build
 */
class Version
{
    /** @var string Define version pattern */
    protected static $pattern = '#^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][\da-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][\da-zA-Z-]*))*))?(?:\+([\da-zA-Z-]+(?:\.[\da-zA-Z-]+)*))?$#';

    private $data = array(
        'major' => '0',
        'minor' => '0',
        'patch' => '0',
        'prerelease' => null,
        'build' => null
    );

    private $cache;

    /**
     * Parse version format
     *
     * @param string $version
     */
    public function __construct($version)
    {
        if (preg_match(self::$pattern, $version, $matches)) {
            $this->data['major'] = $matches[1];
            $this->data['minor'] = $matches[2];
            $this->data['patch'] = $matches[3];

            if (empty($matches[4]) === false) {
                $this->data['prerelease'] = explode('.', $matches[4]);
            }

            if (empty($matches[5]) === false) {
                $this->data['build'] = explode('.', $matches[5]);
            }
        } else {
            throw new Exception('Invalid version format: ' . $version . ' does not match SemVer');
        }
    }

    /**
     * Compare current version with another version
     *
     * @param \Inphinit\Utility\Version $version
     * @return int returns -1 if the current version is lower than the second,
     *                     0 if they are equal, and 1 if the second is lower.
     */
    public function compare(Version $version)
    {
        return version_compare((string) $this, (string) $version);
    }

    /**
     * Get value for a version component
     *
     * @param string $name
     * @return array|int|string|null
     */
    public function __get($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    /**
     * Set value for a version component
     *
     * @param string                $name
     * @param array|int|string|null $value
     * @throws \Inphinit\Exception
     */
    public function __set($name, $value)
    {
        if (array_key_exists($name, $this->data) === false) {
            throw new Exception('Invalid version component: ' . $name);
        }

        if ($value !== null) {
            if ($name === 'build' || $name === 'prerelease') {
                if (is_array($value) === false) {
                    throw new Exception($name . ' expects an array');
                }

                if ($name === 'prerelease') {
                    $id_regex = '#^(?:0|[1-9]\d*|[a-zA-Z-][\da-zA-Z-]*)$#';
                } else {
                    $id_regex = '#^[\da-zA-Z-]+$#';
                }

                foreach ($value as $id) {
                    if (is_string($id) === false || preg_match($id_regex, $id) !== 1) {
                        throw new Exception("Invalid identifier '{$id}' for {$name} component");
                    }
                }
            } elseif (is_numeric($value) === false || preg_match('#^(0|[1-9]\d*)$#', $value) === false) {
                throw new Exception($name . ' expects a numeric value');
            }
        }

        $this->data[$name] = $value;
        $this->cache = null;
    }

    /**
     * Compose string
     *
     * @return string
     */
    public function __toString()
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $output = $this->data['major'] . '.' . $this->data['minor'] . '.' . $this->data['patch'];

        if ($this->data['prerelease']) {
            $output .= '-' . implode('.', $this->data['prerelease']);
        }

        if ($this->data['build']) {
            $output .= '+' . implode('.', $this->data['build']);
        }

        return $this->cache = $output;
    }

    /**
     * Validate string version
     *
     * @param string $version
     * @return bool
     */
    public static function valid($version)
    {
        return preg_match(self::$pattern, $version) === 1;
    }
}
