<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Http;

use Inphinit\Exception;

class CookieJar
{
    const SAME_LAX = 1;
    const SAME_NONE = 2;
    const SAME_STRICT = 3;

    const DISALLOW_NAME_CHARS = "=,; \t\r\n\013\014";
    const DISALLOW_VALUE_CHARS = ",; \t\r\n\013\014";
    const DELETE = '; Expires=Thu, 01 Jan 1970 00:00:01 GMT; Max-Age=0';
    const DELIMITER = ':';

    private $secure = false;
    private $domain;
    private $path = '/';
    private $expires;
    private $httpOnly = false;
    private $partitioned = false;
    private $sameSite;
    private $cookies = array();
    private $jar;
    private static $timeZone;

    /**
     * @param string $jar Define jar name (cookie name prefix)
     */
    public function __construct($jar)
    {
        if (ctype_alnum($jar) === false) {
            throw new Exception('Invalid jar name');
        }

        $this->jar = $jar;

        $jar .= self::DELIMITER;

        $offset = strlen($jar);

        foreach ($_COOKIE as $key => $value) {
            if (
                strpos($key, $jar) === 0 &&
                is_string($value) &&
                self::validChars($value, self::DISALLOW_VALUE_CHARS)
            ) {
                $key = substr($key, $offset);

                if ($key !== '' && self::validChars($key, self::DISALLOW_NAME_CHARS)) {
                    $this->cookies[$key] = $value;
                }
            }
        }

        if (self::$timeZone === null) {
            self::$timeZone = new \DateTimeZone('UTC');
        }
    }

    /**
     * Defines the host to which the cookie will be sent.
     * Note: Contrary to earlier specifications, leading
     * dots in domain names (`.example.com`) are ignored.
     *
     * @param string $domain
     */
    public function setDomain($domain)
    {
        if (
            is_string($domain) === false ||
            $domain === '' ||
            self::validChars($domain, self::DISALLOW_VALUE_CHARS) === false
        ) {
            throw new Exception('Invalid domain');
        }

        $this->domain = $domain;
    }

    /**
     * Indicates the path that must exist in the requested URL for the browser
     * to send the Cookie header.
     *
     * @param string $path
     */
    public function setPath($path)
    {
        if (
            is_string($path) === false ||
            $path === '' ||
            $path[0] !== '/' ||
            preg_match('/[\x00-\x1F\x7F]/', $path) ||
            strpos($path, ';') !== false
        ) {
            throw new Exception('Invalid path');
        }

        $this->path = $path;
    }

    /**
     * Indicates the maximum lifetime of the cookies.
     * Note: Accept English textual datetime descriptions (e.g., '+1 day', 'last Monday').
     *
     * @param string $datetime
     */
    public function setExpires($datetime)
    {
        try {
            $dt = new \DateTime($datetime, self::$timeZone);
            $this->expires = $dt->format('D, d M Y H:i:s \G\M\T');
            $dt = null;
        } catch (\Exception $ee) {
            throw new Exception($ee->getMessage(), 0, 2, $ee);
        }
    }

    /**
     * Forbids JavaScript from accessing the cookie, for example,
     * through the `Document.cookie` property.
     *
     * @param bool $enable
     */
    public function setHttpOnly($enable)
    {
        self::checkBool($enable);
        $this->httpOnly = $enable;
    }

    /**
     * Indicates that the cookie should be stored using partitioned storage.
     * Note that if this is set, the Secure directive must also be set.
     *
     * @param bool $enable
     */
    public function setPartitioned($enable)
    {
        self::checkBool($enable);
        $this->partitioned = $enable;
    }

    /**
     * Controls whether or not a cookie is sent with cross-site requests.
     *
     * @param int $mode
     */
    public function setSameSite($mode)
    {
        switch ($mode) {
            case self::SAME_LAX:
                $this->sameSite = 'Lax';
                break;
            case self::SAME_NONE:
                $this->sameSite = 'None';
                break;
            case self::SAME_STRICT:
                $this->sameSite = 'Strict';
                break;
            default:
                throw new Exception('Invalid mode');
        }
    }

    /**
     * Indicates that the cookie is sent to the server only when a request
     * is made with the https scheme (except on localhost), and therefore,
     * is more resistant to man-in-the-middle attacks.
     *
     * @param bool $enable
     */
    public function setSecure($enable)
    {
        self::checkBool($enable);
        $this->secure = $enable;
    }

    /**
     * Magic method for get property value from jar
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return isset($this->cookies[$name]) ? $this->cookies[$name] : null;
    }

    /**
     * Magic method for set or remove cookie jar properties
     *
     * @param string      $name
     * @param string|null $value
     * @throws \Inphinit\Exception
     */
    public function __set($name, $value)
    {
        // Checks cookie name
        if (
            is_string($name) === false ||
            $name === '' ||
            self::validChars($name, self::DISALLOW_NAME_CHARS) === false
        ) {
            throw new Exception('The name is invalid or contains invalid characters');
        }

        // Checks cookie value
        if ($value === null || is_string($value)) {
            $value = $value;
        } elseif (is_numeric($value) || (is_object($value) && method_exists($value, '__toString'))) {
            $value = (string) $value;
        } else {
            $type = function_exists('get_debug_type') ? get_debug_type($value) : gettype($value);
            throw new Exception("Expected value to be null, string, number, or Stringable object; {$type} given");
        }

        if ($value !== null && self::validChars($value, self::DISALLOW_VALUE_CHARS) === false) {
            throw new Exception("Value contains invalid characters: {$value}");
        }

        $this->cookies[$name] = $value;
    }

    /**
     * Magic method for check if variable is setted
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->cookies[$name]);
    }

    /**
     * Send cookies from jar to headers
     *
     * @throws \Inphinit\Exception
     */
    public function send()
    {
        if (headers_sent($file, $line)) {
            throw new \ErrorException('Cannot set cookies, headers already sent', 0, E_ERROR, $file, $line);
        }

        $secure = $this->secure;
        $params = '';
        $expires = '';
        $expires_delete = self::DELETE;

        if ($this->domain) {
            $params .= '; Domain=' . $this->domain;
        }

        $params .= '; Path=' . $this->path;

        if ($this->expires) {
            $expires = '; Expires=' . $this->expires;
        }

        if ($this->httpOnly) {
            $params .= '; HttpOnly';
        }

        if ($this->partitioned) {
            $params .= '; Partitioned';
            $secure = true;
        }

        if ($this->sameSite) {
            $params .= '; SameSite=' . $this->sameSite;

            if ($this->sameSite === 'None') {
                $secure = true;
            }
        }

        if ($secure) {
            $params .= '; Secure';
        }

        $prefix = $this->jar . self::DELIMITER;

        foreach ($this->cookies as $name => $value) {
            if ($value === null) {
                $entry = '_' . $params . $expires_delete;
            } else {
                $entry = rawurlencode($value) . $params . $expires;
            }

            header('Set-Cookie: ' . $prefix . $name . '=' . $entry, false);
        }
    }

    private static function validChars($value, $chars)
    {
        return strpbrk($value, $chars) === false;
    }

    private static function checkBool($enable)
    {
        if (is_bool($enable) === false) {
            $type = function_exists('get_debug_type') ? get_debug_type($enable) : gettype($enable);
            throw new Exception("Expects to be bool, {$type} given", 0, 3);
        }
    }
}
