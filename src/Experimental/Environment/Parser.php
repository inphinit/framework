<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Environment;

class Parser
{
    const REGEX_QUOTES = '/^([\'"])(.+)\\1/';
    const REGEX_VAR = '/\$\{(.+?)\}/';

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
            $quote = $matches[1];

            $value = substr($value, 1, -1);

            $value = strtr($value, array(
                '\\\\' => '\\',
                '\\' . $quote => $quote,
            ));

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
     * Obtêm o valor processado
     *
     * @return string
     */
    public function output()
    {
        return $this->data;
    }

    private function interpolate($matches)
    {
        $name = $matches[1];

        if (isset($_ENV[$name])) {
            return $_ENV[$name];
        }

        if (isset($this->fallback[$name])) {
            return $this->fallback[$name];
        }

        return '';
    }
}
