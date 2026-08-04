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

class EnvFile
{
    /** @var int Define `$_ENV` as source */
    const SOURCE_VAR = 1;

    /** @var int Define `getenv()` and `putenv()` as source */
    const SOURCE_ENV = 2;

    /** @var int Define `apache_getenv()` and `apache_setenv()` as source */
    const SOURCE_APACHE = 4;

    /** @var int Define `apache_getenv()` and `apache_setenv()` as source for all layers of Apache */
    const SOURCE_APACHE_ALL = 8;

    const REGEX_ENTRY = '/^\s*([A-Za-z_][A-Za-z0-9_]*?)\s*=\s*(.*)$/';
    const REGEX_KEY = '/^([A-Za-z_][A-Za-z0-9_]*)$/';

    private $path;
    private $entries = array();
    private $override = false;

    /**
     * Load variable from .env or from cache (if avaliable)
     *
     * @param string $path  Env file path
     * @throws \Inphinit\Exception
     */
    public function __construct($path)
    {
        $this->path = $path;
        $this->load();
    }

    /**
     * This method affect fill() and storeAsVars() methods
     *
     * @throws \Inphinit\Exception
     */
    public function setOverride($enable)
    {
        if (is_bool($enable) === false) {
            throw new Exception('Excepted an bool value');
        }

        $this->override = $enable;
    }

    /**
     * Refresh entries from the env file values.
     * Ideal for use before fill() or cache(),
     * especially if there are any changes to the file.
     *
     * @throws \Inphinit\Exception
     */
    public function refresh()
    {
        $this->entries = array();
        $this->load();
    }

    /**
     * Fill the values of environment variables
     *
     * @param int $sources Defines the filling modes
     * @throws \Inphinit\Exception
     */
    public function fill($sources = self::SOURCE_VAR)
    {
        $valid_sources = self::SOURCE_VAR | self::SOURCE_ENV | self::SOURCE_APACHE | self::SOURCE_APACHE_ALL;

        if (is_int($sources) === false || ($sources & ~$valid_sources) !== 0) {
            throw new Exception('Invalid sources');
        }

        if ($sources & self::SOURCE_ENV) {
            self::isAvaliable('getenv', 'putenv');
        }

        if ($sources & (self::SOURCE_APACHE | self::SOURCE_APACHE_ALL)) {
            self::isAvaliable('apache_getenv', 'apache_setenv');
        }

        $override = $this->override;

        foreach ($this->entries as $name => $entry) {
            if (($sources & self::SOURCE_VAR) && ($override || array_key_exists($name, $_ENV) === false)) {
                $_ENV[$name] = $entry;
            }

            if (($sources & self::SOURCE_ENV) && ($override || getenv($name) === false)) {
                putenv("{$name}={$entry}");
            }

            if (($sources & self::SOURCE_APACHE) && ($override || apache_getenv($name) === false)) {
                apache_setenv($name, $entry);
            }

            if (($sources & self::SOURCE_APACHE_ALL) && ($override || apache_getenv($name, true) === false)) {
                apache_setenv($name, $entry, true);
            }
        }
    }

    /**
     * Store entries as PHP array
     *
     * @param string $path
     * @return bool
     */
    public function storeAsArray($path)
    {
        $contents = "<?php\nreturn " . var_export($this->entries, true) . ";\n";
        return file_put_contents($path, $contents, LOCK_EX) !== false;
    }

    /**
     * Store entries as $_ENV[<entry key>] = <entry value>; in a PHP script
     *
     * @param string $path
     * @return bool
     */
    public function storeAsVars($path)
    {
        $contents = "<?php\n";

        if ($this->override) {
            foreach ($this->entries as $name => $entry) {
                $name = var_export($name, true);
                $contents .= "\$_ENV[{$name}] = " . var_export($entry, true) . ";\n";
            }
        } else {
            $contents .= '$_ENV += ' . var_export($this->entries, true) . ";\n";
        }

        return file_put_contents($path, $contents, LOCK_EX) !== false;
    }

    /**
     * Magic method for set a manual entry
     *
     * @param string      $name
     * @param string|null $value
     * @throws \Inphinit\Exception
     */
    public function __set($name, $value)
    {
        if (preg_match(self::REGEX_KEY, $name) !== 1) {
            throw new Exception('Invalid name entry');
        }

        if ($value === null) {
            unset($this->entries[$name]);
        } elseif (is_string($value) === false) {
            throw new Exception('A string value is expected');
        } else {
            $this->entries[$name] = $value;
        }
    }

    /**
     * Magic method for get specific entry
     *
     * @param string $name
     * @return string
     */
    public function __get($name)
    {
        return isset($this->entries[$name]) ? $this->entries[$name] : null;
    }

    private function load()
    {
        $path = $this->path;

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            $err = error_get_last();
            throw new Exception($err ? $err['message'] : 'Unknown error', $err ? $err['type'] : 0, 3);
        }

        $parser = new Parser();

        $line = 0;

        while (($data = fgets($handle)) !== false) {
            ++$line;

            $data = rtrim($data, "\r\n");

            if (empty($data)) {
                continue;
            }

            if (preg_match(self::REGEX_ENTRY, $data, $matches) === 1) {
                $this->addressIssues($parser, $matches[2], $line);

                $name = $matches[1];
                $value = $parser->output();

                $this->entries[$name] = $value;

                $parser->putFallback($name, $value);
            } elseif (strpos($data, '#') !== 0) {
                throw new EnvException("Invalid '{$data}' entry", 0, $this->path, $line);
            }
        }

        fclose($handle);
    }

    private static function isAvaliable($getter, $setter)
    {
        if (function_exists($getter) === false || function_exists($setter) === false) {
            throw new Exception("{$getter} or {$setter} is not avaliable", 0, 3);
        }
    }

    private function addressIssues($parser, $value, $line)
    {
        try {
            $parser->setValue($value);
        } catch (\Exception $ee) {
            throw new EnvException(
                $ee->getMessage(),
                $ee->getCode(),
                $this->path,
                $line
            );
        }
    }
}
