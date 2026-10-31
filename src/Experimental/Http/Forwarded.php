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

class Forwarded
{
    /** @var int Specifies that the IP should be obtained from the header */
    const IP = 1;

    /** @var int Specifies that the protocol should be obtained from the header */
    const PROTO = 2;

    /** @var int Specifies that the host should be obtained from the header */
    const HOST = 4;

    /** @var int Specifies that the path prefix should be obtained from the header */
    const PREFIX = 8;

    /** @var int Uses `Forwarded:` */
    const RFC7239 = 16;

    const DEFAULT_SOURCES = 7;
    const VALID_SOURCES = 15;

    // 12-byte prefix of IPv4-mapped IPv6 addresses (`::ffff:a.b.c.d`)
    const IPV4_MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    const REGEX_RFC7239 = '/(?:^|;)\s*(?<name>for|host|proto)\s*=\s*(?:"(?<quoted>[^"]*)"|(?<value>[^;]*))/i';

    private $allowedHosts = array('*');
    private $currentIp;
    private $flags = self::DEFAULT_SOURCES;
    private $headers = array(
        self::IP => 'HTTP_X_FORWARDED_FOR',
        self::PROTO => 'HTTP_X_FORWARDED_PROTO',
        self::HOST => 'HTTP_X_FORWARDED_HOST',
        self::PREFIX => 'HTTP_X_FORWARDED_PREFIX',
    );
    private $info = array();
    private $known;
    private $knownNetworks = array('::1/128', '127.0.0.0/8');
    private $knownProxies = array('127.0.0.1', '::1');
    private $limit = 1;
    private $rfc7239Info;
    private $server;

    /**
     * Creates a new instance
     *
     * @param string[] $server Server variables, usually `$_SERVER`
     */
    public function __construct(array $server)
    {
        $this->server = $server;
    }

    /**
     * Sets the address ranges (CIDR notation, IPv4 or IPv6) of proxies
     *
     * @param string[] $networks
     */
    public function setKnownNetworks(array $networks)
    {
        $this->knownNetworks = $networks;
        $this->info = array();
        $this->known = null;
    }

    /**
     * Sets the exact addresses of proxies from which forwarded headers are accepted
     *
     * @param string[] $proxies
     */
    public function setKnownProxies(array $proxies)
    {
        $this->knownProxies = $proxies;
        $this->info = array();
        $this->known = null;
    }

    /**
     * Sets the hosts accepted when the host comes from a forwarded header. Supported patterns:
     * - `example.com`
     * - `*.example.com`
     * - `*` (any host)
     *
     * Note: entries must be in non-transitional IDNA (UTS #46) ASCII format
     *
     * @param string[] $hosts
     */
    public function setAllowedHosts(array $hosts)
    {
        $this->allowedHosts = $hosts;
        $this->info = array();
    }

    /**
     * Set the headers that must/may provide values
     *
     * @param int $flags
     */
    public function setFlags($flags)
    {
        if (is_int($flags) === false || ($flags !== self::RFC7239 && ($flags & ~self::VALID_SOURCES) !== 0)) {
            throw new Exception('Invalid flag(s)');
        }

        $this->flags = $flags;
        $this->info = array();
    }

    /**
     * Set the headers names
     *
     * @param int $flag
     * @param string $name
     */
    public function setHeaderName($flag, $name)
    {
        if (is_int($flag) === false || isset($this->headers[$flag]) === false) {
            throw new Exception('Invalid flag');
        }

        $this->headers[$flag] = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $this->info = array();
    }

    /**
     * Sets how many trusted proxies are in front of the application.
     *
     * In headers with comma-separated values (e.g. `X-Forwarded-For`, or multiple
     * `Forwarded:` entries) the value is taken counting from the end of the list:
     * `1` (default) uses the last entry (appended by the closest proxy), `2` the
     * second to last, and so on. If the limit is greater than the number of entries,
     * the first one is used. This prevents clients from spoofing values at the
     * beginning of the list.
     *
     * @param int $limit
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
        $this->info = array();
        $this->rfc7239Info = null;
    }

    /**
     * Get IP
     *
     * @return string|null
     */
    public function getIp()
    {
        if ($this->cache('ip', $value)) {
            return $value;
        }

        $value = $this->getInfo(self::IP, 'for');

        if ($value !== null) {
            if (preg_match('/\[(.+?)\]/', $value, $matches) === 1) {
                $value = $matches[1];
            } elseif (preg_match('/^(.+?)\:\d+$/', $value, $matches) === 1) {
                $value = $matches[1];
            }

            if (filter_var($value, FILTER_VALIDATE_IP) === false) {
                $value = null;
            }
        }

        return $this->info['ip'] = $value === null ? $this->currentIp : $value;
    }

    /**
     * Get protocol/scheme (https or http)
     *
     * @return string
     */
    public function getProto()
    {
        if ($this->cache('proto', $value)) {
            return $value;
        }

        $value = $this->getInfo(self::PROTO, 'proto');

        if ($value === null && isset($this->server['HTTPS'][0])) {
            $value = $this->server['HTTPS'];
        }

        if ($value === '' || $value === null || in_array(strtolower($value), array('on', 'https'), true) === false) {
            $value = 'http';
        } else {
            $value = 'https';
        }

        return $this->info['proto'] = $value;
    }

    /**
     * Get host
     *
     * @return string|null
     */
    public function getHost()
    {
        if ($this->cache('host', $value)) {
            return $value;
        }

        $source = $this->getInfo(self::HOST, 'host');
        $value = null;

        if ($source === null && isset($this->server['HTTP_HOST'][0]) && $this->fromKnown()) {
            $source = $this->server['HTTP_HOST'];
        }

        if ($source !== null && preg_match('#^(.+?)(\:\d+)?$#', $source, $matches)) {
            $host = $matches[1];
            $ascii = \idn_to_ascii($host, \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46);

            if ($ascii !== false && $this->isAllowedHost($ascii)) {
                $value = $source;
            }
        }

        if ($value === null && isset($this->server['SERVER_NAME'][0])) {
            $value = $this->server['SERVER_NAME'];
        }

        return $this->info['host'] = $value;
    }

    /**
     * Get prefix
     *
     * @return string
     */
    public function getPrefix()
    {
        if ($this->cache('prefix', $value)) {
            return $value;
        }

        $source = $this->getInfo(self::PREFIX, 'prefix');
        $value = '/';

        if ($source !== null) {
            $source = trim($source, '/');
            $value = $source === '' ? '' : ('/' . $source);
        }

        return $this->info['prefix'] = $value;
    }

    private function fromKnown()
    {
        if ($this->known !== null) {
            return $this->known;
        }

        if (isset($this->server['REMOTE_ADDR'][0])) {
            $this->currentIp = $this->server['REMOTE_ADDR'];
        }

        if ($this->currentIp === null) {
            return $this->known = false;
        }

        if (in_array($this->currentIp, $this->knownProxies, true)) {
            return $this->known = true;
        }

        $addr = @inet_pton($this->currentIp);

        if ($addr !== false) {
            $addr = self::toIPv6($addr);

            foreach ($this->knownNetworks as $network) {
                if (self::inNetwork($addr, $network)) {
                    return $this->known = true;
                }
            }
        }

        return $this->known = false;
    }

    private function isAllowedHost($host)
    {
        foreach ($this->allowedHosts as $allowed) {
            if ($allowed === '*' || $allowed === $host) {
                return true;
            }

            // *.example.com matches subdomains, but not example.com itself
            if (strpos($allowed, '*.') === 0) {
                $suffix = substr($allowed, 1);

                if (strlen($host) > strlen($suffix) && substr($host, -strlen($suffix)) === $suffix) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Converts a binary address (inet_pton) into 16 bytes.
     * IPv4 is converted to an IPv4-mapped address (::ffff:a.b.c.d), so `10.0.0.1` and `::ffff:10.0.0.1`
     * become identical, and an IPv4 rule will also match dual-stack clients.
     */
    private static function toIPv6($bin)
    {
        return strlen($bin) === 4 ? (self::IPV4_MAPPED_PREFIX . $bin) : $bin;
    }

    private static function inNetwork($addr, $network)
    {
        $parts = explode('/', $network, 2);
        $base = @inet_pton($parts[0]);

        if ($base === false) {
            return false;
        }

        $bits = strlen($base) * 8;

        if (isset($parts[1])) {
            if (ctype_digit($parts[1]) === false) {
                return false;
            }

            $prefix = (int) $parts[1];

            if ($prefix > $bits) {
                return false;
            }
        } else {
            $prefix = $bits;
        }

        if ($bits === 32) {
            // Caution: Position within the IPv4-mapped
            $prefix += 96;
        }

        $base = self::toIPv6($base);
        $bytes = $prefix >> 3;
        $rest = $prefix & 7;

        if (strncmp($addr, $base, $bytes) !== 0) {
            return false;
        }

        if ($rest === 0) {
            return true;
        }

        $mask = (0xff << (8 - $rest)) & 0xff;

        return (ord($addr[$bytes]) & $mask) === (ord($base[$bytes]) & $mask);
    }

    private function cache($key, &$value)
    {
        if (array_key_exists($key, $this->info)) {
            $value = $this->info[$key];
            return true;
        }

        return false;
    }

    private function getInfo($flag, $name)
    {
        if ($this->fromKnown()) {
            if ($this->flags === self::RFC7239) {
                return $this->getRfc7239($name);
            }

            if ($this->flags & $flag) {
                return $this->pick($this->headers[$flag], $this->limit);
            }
        }

        return null;
    }

    private function pick($key, $limit)
    {
        if (isset($this->server[$key][0]) === false) {
            return null;
        }

        $items = array_map('trim', explode(',', $this->server[$key]));

        $index = max(0, count($items) - $limit);
        $value = $items[$index];

        return $value !== '' ? $value : null;
    }

    private function getRfc7239($name)
    {
        if ($this->rfc7239Info !== null) {
            $info = $this->rfc7239Info;
        } else {
            $info = array('for' => null, 'proto' => null, 'host' => null, 'prefix' => null);

            if (isset($this->server['HTTP_FORWARDED'][0])) {
                $input = $this->server['HTTP_FORWARDED'];
                $items = preg_split('/\s*,\s*/', trim($input));
                $index = max(0, count($items) - $this->limit);

                if (isset($items[$index][0])) {
                    $entry = $items[$index];

                    preg_match_all(self::REGEX_RFC7239, $entry, $matches, PREG_SET_ORDER);

                    foreach ($matches as $match) {
                        if (isset($match['value'])) {
                            $value = trim($match['value']);
                        } else {
                            $value = trim($match['quoted'], '"');
                        }

                        $info[strtolower($match['name'])] = $value === '' ? null : $value;
                    }
                }
            }

            $this->rfc7239Info = $info;
        }

        return $info[$name];
    }
}
