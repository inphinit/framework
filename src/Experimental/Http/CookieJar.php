<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
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

    const DELETE = '; Expires=Thu, 01 Jan 1970 00:00:01 GMT; Max-Age=0';

    private $secure = false;
    private $domain;
    private $path = '/';
    private $expires;
    private $httpOnly = false;
    private $partitioned = false;
    private $sameSite;
    private $cookies = array();
    private $jar;
    private $timezone;

    /**
     * @param string $jar Define jar name (cookie name prefix)
     */
    public function __construct($jar)
    {
        if (ctype_alnum($jar) === false) {
            throw new Exception('Invalid jar name');
        }

        $this->jar = $jar;

        $jar .= ':';

        $offset = strlen($jar);

        foreach ($_COOKIE as $key => $value) {
            if (
                strpos($key, $jar) === 0 &&
                is_string($value) &&
                self::validChars($value)
            ) {
                $key = substr($key, $offset);

                if ($key !== '' && self::validChars($key)) {
                    $this->cookies[$key] = $value;
                }
            }
        }

        $this->timezone = new \DateTimeZone('UTC');
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
        if (empty($domain) || is_string($domain) === false || self::validChars($domain) === false) {
            throw new Exception('Invalid domain');
        }

        $this->domain = $domain;
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
            $dt = new \DateTime($datetime, $this->timezone);
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
     * Indicates the path that must exist in the requested URL for the browser
     * to send the Cookie header.
     *
     * @param string $path
     */
    public function setPath($path)
    {
        if (
            is_string($path) === false ||
            $path[0] !== '/' ||
            preg_match('/[\x00-\x1F\x7F]/', $path) ||
            strpos($path, ';') !== false
        ) {
            throw new Exception('Invalid path');
        }

        $this->path = $path;
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
        if (empty($name) || is_string($name) === false || self::validChars($name) === false) {
            throw new Exception('Invalid name');
        }

        if ($value === null) {
            if (isset($this->cookies[$name])) {
                $this->cookies[$name] = null;
            }
        } elseif (is_numeric($value) || (is_string($value) && self::validChars($value))) {
            $this->cookies[$name] = (string) $value;
        } else {
            $type = gettype($value);
            throw new Exception("Expects to be string, number or null, {$type} given");
        }
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
        $expiresDelete = self::DELETE;

        if ($this->domain) {
            $params .= '; Domain=' . $this->domain;
        }

        if ($this->path) {
            $params .= '; Path=' . $this->path;
        }

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

        $prefix = $this->jar . ':';

        foreach ($this->cookies as $name => $value) {
            if ($value === null) {
                header('Set-Cookie: ' . $prefix . $name . '=_' . $params . $expiresDelete, false);
            } else {
                header('Set-Cookie: ' . $prefix . $name . '=' . $value . $params . $expires, false);
            }
        }
    }

    private static function validChars($value)
    {
        return strpbrk($value, "=,; \t\r\n\013\014") === false;
    }

    private static function checkBool($enable)
    {
        if (is_bool($enable) === false) {
            $type = gettype($enable);
            throw new Exception("Expects to be bool, {$type} given", 0, 3);
        }
    }
}
