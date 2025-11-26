<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Environment;

use Inphinit\Exception;

class Parser
{
    const REGEX_QUOTES = '/^([\'"])(.+)\\1(.*)/';
    const REGEX_VAR = '/\$\{(.+?)\}/';
    const REGEX_INTERPOLATE = '/^([A-Za-z_][A-Za-z0-9_]*)((:)?([\+\-\?])(.*))$/';

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
            $residual = $matches[3];

            if ($residual !== '') {
                throw new Exception('Invalid sintax');
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

        if (preg_match(self::REGEX_INTERPOLATE, $contents, $inMatches) !== 1) {
            throw new Exception("Invalid interpolation \$\{{$contents}\}", 0, 3);
        }

        $var = $inMatches[1];
        $nonEmpty = $inMatches[3] === ':';
        $interMode = $inMatches[4];
        $interParam = $inMatches[5];
        $value = null;

        if (isset($_ENV[$var])) {
            $value = $_ENV[$var];
        } elseif (isset($this->fallback[$var])) {
            $value = $this->fallback[$var];
        }

        switch ($interMode) {
            case '+':
                // ${VAR-alternative} Replace
                if ($nonEmpty) {
                    return $value === '' ? '' : $interParam;
                } else {
                    return $value === null ? '' : $interParam;
                }

                break;
            case '-':
                // ${VAR-default} Default
                if ($nonEmpty) {
                    return $value === '' ? $interParam : $value;
                } else {
                    return $value === null ? $interParam : $value;
                }

                break;
            case '?':
                // ${VAR?default} Required
                if ($nonEmpty) {
                    if ($value === '' || $value === null) {
                        throw new Exception($interParam, 0, 3);
                    }
                } elseif ($value === null) {
                    throw new Exception($interParam, 0, 3);
                }

                break;
        }

        return $value;
    }
}
