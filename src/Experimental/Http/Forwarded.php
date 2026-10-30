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
    /** @var int Uses `X-Forwarded-For: <client>, <proxy>, ..., <proxyN>` */
    const SOURCE_FORWARDED_FOR = 1;

    /** @var int Uses `CF-Connecting-IP:` (Cloudflare) */
    const SOURCE_CF_CONNECTING_IP = 2;

    /** @var int Uses `True-Client-IP:` (Cloudflare Enterprise, Akamai) */
    const SOURCE_TRUE_CLIENT_IP = 4;

    /** @var int Uses `X-Real-IP:` (commonly set by nginx) */
    const SOURCE_REAL_IP = 8;

    /** @var int Uses `X-Forwarded-Proto: <http|https>` */
    const SOURCE_FORWARDED_PROTO = 16;

    /** @var int Uses `X-Forwarded-Ssl: <on|off>` */
    const SOURCE_FORWARDED_SSL = 32;

    /** @var int Uses `X-Url-Scheme: <http|https>` */
    const SOURCE_URL_SCHEME = 64;

    /** @var int Uses `X-Forwarded-Host:` */
    const SOURCE_FORWARDED_HOST = 128;

    /** @var int Uses `X-Forwarded-Prefix:` */
    const SOURCE_FORWARDED_PREFIX = 256;

    /** @var int Uses `Forwarded:` */
    const SOURCE_RFC7239 = 512;

    // default: x-forwarded-for, x-forwarded-proto, x-forwarded-host
    const DEFAULT_SOURCES = 145;
    const VALID_SOURCES = 1023;

    // 12-byte prefix of IPv4-mapped IPv6 addresses (`::ffff:a.b.c.d`).
    const IPV4_MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    const REGEX_RFC7239 = '/(?:^|;)\s*(?<name>for|host|proto)\s*=\s*(?:"(?<quoted>[^"]*)"|(?<value>[^;]*))/i';

    private $allowedHosts = array('*');
    private $currentIp;
    private $flags = self::DEFAULT_SOURCES;
    private $info = array();
    private $known;
    private $knownNetworks = array('::1/128', '127.0.0.0/8');
    private $knownProxies = array();
    private $limit = 1;
    private $server;

    /**
     * Creates a new instance.
     *
     * @param string[] $server Server variables, usually `$_SERVER`.
     */
    public function __construct(array $server)
    {
        $this->server = $server;
    }

    /**
     * Set address ranges of known proxies to accept forwarded headers from.
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
     * Set addresses of known proxies to accept forwarded headers from.
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
     * Set allowed hosts via x-forwarded-host. Supported patterns:
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
     * Set the headers that must/may provide values.
     *
     * @param int $flags
     */
    public function setFlags($flags)
    {
        if (is_int($flags) === false || ($flags & ~self::VALID_SOURCES) !== 0) {
            throw new Exception('Invalid flag(s)');
        }

        $this->flags = $flags;
        $this->info = array();
    }

    /**
     * Set forwarded limit
     *
     * @param int $limit
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
        $this->info = array();
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

        $value = null;

        if ($this->fromKnown()) {
            $flags = $this->flags;

            if ($flags & self::SOURCE_RFC7239) {
                $value = $this->rfc7239('for');
            } elseif ($flags & self::SOURCE_FORWARDED_FOR) {
                $value = $this->pick('HTTP_X_FORWARDED_FOR', null, $this->limit);
            } elseif ($flags & self::SOURCE_CF_CONNECTING_IP) {
                $value = $this->pick('HTTP_CF_CONNECTING_IP', null, 1);
            } elseif ($flags & self::SOURCE_TRUE_CLIENT_IP) {
                $value = $this->pick('HTTP_TRUE_CLIENT_IP', null, 1);
            } elseif ($flags & self::SOURCE_REAL_IP) {
                $value = $this->pick('HTTP_X_REAL_IP', null, 1);
            }
        }

        if ($value === null) {
            $value = $this->currentIp;
        }

        return $this->info['ip'] = $value;
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

        $value = null;

        if ($this->fromKnown()) {
            $flags = $this->flags;

            if ($flags & self::SOURCE_RFC7239) {
                $value = $this->rfc7239('proto');
            } elseif ($flags & self::SOURCE_FORWARDED_PROTO) {
                // X-Forwarded-Proto: https
                $value = $this->pick('HTTP_X_FORWARDED_PROTO', null, $this->limit);
            } elseif ($flags & self::SOURCE_FORWARDED_SSL) {
                // X-Forwarded-Ssl: on
                $value = $this->pick('HTTP_X_FORWARDED_SSL', 'on', $this->limit);
            } elseif ($flags & self::SOURCE_URL_SCHEME) {
                // X-Url-Scheme: https
                $value = $this->pick('HTTP_X_URL_SCHEME', null, 1);
            }
        }

        if ($value === null && isset($this->server['HTTPS'][0])) {
            $https = $this->server['HTTPS'];
            $value = $https !== '' && strcasecmp($https, 'off') !== 0;
        }

        if ($value === null || is_bool($value)) {
            $value = $value ? 'https' : 'http';
        } elseif (strcasecmp($value, 'https') === 0) {
            $value = 'https';
        } else {
            $value = 'http';
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

        $flags = $this->flags;
        $value = null;

        if ($this->fromKnown()) {
            $source = null;

            if ($flags & self::SOURCE_RFC7239) {
                $source = $this->rfc7239('host');
            } elseif ($flags & self::SOURCE_FORWARDED_HOST) {
                $source = $this->pick('HTTP_X_FORWARDED_HOST', null, $this->limit);
            }

            if ($source === null && isset($this->server['HTTP_HOST'][0])) {
                $source = $this->server['HTTP_HOST'];
            }

            if ($source !== null && preg_match('#^(.*?)(\:\d+)?$#', $source, $matches)) {
                // Extract host from <host>:<port>
                $host = $matches[1];
                $ascii = \idn_to_ascii($host, \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46);

                if ($ascii !== false && $this->isAllowedHost($ascii)) {
                    $value = $source;
                }
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
        $flags = $this->flags;

        if ($flags & self::SOURCE_RFC7239) {
            throw new Exception('Not supported by RFC7239');
        }

        if ($this->cache('prefix', $value)) {
            return $value;
        }

        $value = '/';

        if ($this->fromKnown() && ($flags & self::SOURCE_FORWARDED_PREFIX)) {
            $prefix = $this->pick('HTTP_X_FORWARDED_PREFIX', null, $this->limit);

            if ($prefix !== null) {
                $prefix = trim($prefix, '/');
                $value = $prefix === '' ? '/' : ('/' . $prefix . '/');
            }
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

        if ($prefix > $bits) {
            return false;
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

    private function pick($key, $matches, $limit)
    {
        if (isset($this->server[$key][0]) === false) {
            return null;
        }

        $items = array_map('trim', explode(',', $this->server[$key]));

        $index = max(0, count($items) - $limit);
        $value = $items[$index];

        if ($matches !== null) {
            return strcasecmp($items[$index], $matches) === 0;
        }

        return $value !== '' ? $value : null;
    }

    private function rfc7239($name)
    {
        $info = array('for' => null, 'host' => null, 'proto' => null);

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

                    $info[strtolower($match['name'])] = $value;
                }
            }
        }

        $this->info = $info;

        return $info[$name];
    }
}
