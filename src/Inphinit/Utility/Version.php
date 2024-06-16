<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Utility;

use Inphinit\Exception;

class Version
{
    private $data = array(
        'major' => '0',
        'minor' => '0',
        'patch' => '0',
        'prerelease' => null,
        'build' => null
    );

    /**
     * Parse version format
     *
     * @param string $version
     */
    public function __construct($version)
    {
        if (preg_match('#^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][\da-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][\da-zA-Z-]*))*))?(?:\+([\da-zA-Z-]+(?:\.[\da-zA-Z-]+)*))?$#', $version, $matches)) {
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
            throw new Exception($version . ' not matches with semversion');
        }
    }

    /**
     * Get value for a version component
     *
     * @param string $name
     * @return array|string|null
     */
    public function __get($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    /**
     * Set value for a version component
     *
     * @param string $name
     * @param string|array $value
     */
    public function __set($name, $value)
    {
        if (array_key_exists($name, $this->data)) {
            if (($name === 'build' || $name === 'prerelease') && is_array($value) === false) {
                throw new Exception(get_class($this) . '::$' . $name . ' except an array');
            }

            $this->data[$name] = $value;
        }
    }

    /**
     * Compose string
     *
     * @return string
     */
    public function __toString()
    {
        $output = $this->data['major'] . '.' . $this->data['minor'] . '.' . $this->data['patch'];

        if ($this->data['prerelease']) {
            $output .= '-' . implode('.', $this->data['prerelease']);
        }

        if ($this->data['build']) {
            $output .= '+' . implode('.', $this->data['build']);
        }

        return $output;
    }

    public function __destruct()
    {
        $this->data = null;
    }
}
