<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Environment;

use Inphinit\Exception;

class Parser
{
    const REGEX_QUOTES = '/^([\'"])(.*)\\1(.*)/';
    const REGEX_VAR = '/\$\{(.*?)\}/';
    const REGEX_INTERPOLATE = '/^([A-Za-z_][A-Za-z0-9_]*)((:)?([\+\-\?])(.*))?$/';

    private $data;
    private $fallback = array();
    private static $escapes = array(
        '\\n' => "\n",
        '\\t' => "\t",
        '\\r' => "\r"
    );

    /**
     * Define the value that will be processed
     *
     * @param string $value
     */
    public function setValue($value)
    {
        $value = trim($value);
        $quote = null;

        if (preg_match(self::REGEX_QUOTES, $value, $matches) === 1) {
            // If there is anything other than whitespace after the closing quotation mark,
            // it will be considered an error.
            $unexpected = $matches[3];

            if ($unexpected !== '') {
                throw new Exception("Unexpected '{$unexpected}' in {$value}");
            }

            $quote = $matches[1];
            $value = preg_replace('/\\\\(["\'\\\])/', '$1', $matches[2]);

            if ($quote === '"') {
                $value = strtr($value, self::$escapes);
            }
        } else {
            $value = preg_replace('/\s+#.*$/', '', $value);
        }

        if ($quote !== '\'' && strpos($value, '$') !== false) {
            $value = preg_replace_callback(self::REGEX_VAR, array($this, 'interpolate'), $value);
        }

        $this->data = $value;
    }

    /**
     * Adds alternative values if they don't exist in `$_ENV[...]`
     *
     * @param string $name
     * @param string $value
     */
    public function putFallback($name, $value)
    {
        $this->fallback[$name] = $value;
    }

    /**
     * Get the processed value
     *
     * @return string
     */
    public function output()
    {
        return $this->data;
    }

    private function interpolate($matches)
    {
        $contents = $matches[1];

        if ($contents === '' || preg_match(self::REGEX_INTERPOLATE, $contents, $inter_matches) !== 1) {
            throw new Exception('Invalid interpolation ${' . $contents . '}', 0, 3);
        }

        $var = $inter_matches[1];
        $non_empty = isset($inter_matches[3]) && $inter_matches[3] === ':';
        $inter_mode = isset($inter_matches[4]) ? $inter_matches[4] : '';
        $inter_param = isset($inter_matches[5]) ? $inter_matches[5] : '';

        $value = null;

        if (isset($_ENV[$var])) {
            $value = $_ENV[$var];
        } elseif (isset($this->fallback[$var])) {
            $value = $this->fallback[$var];
        }

        $unset_value = ($value === null);
        $empty_value = ($value === '' || $unset_value);

        switch ($inter_mode) {
            case '+':
                if ($non_empty) {
                    // ${VAR:+replacement} Replace
                    return $empty_value ? '' : $inter_param;
                }

                // ${VAR+replacement} Replace
                return $unset_value ? '' : $inter_param;

            case '-':
                if ($non_empty) {
                    // ${VAR:-default} Default
                    return $empty_value ? $inter_param : $value;
                }

                // ${VAR-default} Default
                return $unset_value ? $inter_param : $value;

            case '?':
                if ($non_empty) {
                    // ${VAR:?error} Required
                    if ($empty_value) {
                        throw new Exception($inter_param, 0, 3);
                    }
                } elseif ($unset_value) {
                    // ${VAR?error} Required
                    throw new Exception($inter_param, 0, 3);
                }
        }

        return $value !== null ? $value : '';
    }
}
