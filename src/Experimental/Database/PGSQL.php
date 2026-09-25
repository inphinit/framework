<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Database;

use Inphinit\App;
use Inphinit\Config;
use Inphinit\Diagnostics\Inspector;
use Inphinit\Exception;

class PGSQL
{
    /* @var int returns an array indexed by column name as returned in the corresponding result set */
    const ASSOC = 1;

    /* @var int returns an array indexed by column number as returned in the corresponding result set, starting at column 0 */
    const NUM = 2;

    private $configPath;

    // Handle driver/api
    private $handle;

    // Fetch setup
    private $fetchBinds = array();
    private $fetchLimit = 100;
    private $fetchMode;
    private $fetchOffset = 0;
    private $fetchResult;
    private $fetchSelect;

    /**
     * Create SQLite instance
     *
     * @param string $configPath
     * @throws \Inphinit\Exception
     */
    public function __construct($configPath)
    {
        $this->configPath = $configPath;

        // Caution: In the development environment, the boot is forced to help anticipate errors
        if (App::config('environment') === 'development') {
            $this->boot();
        }
    }

    private function boot()
    {
        if ($this->fetchMode === null) {
            $this->fetchMode = \PGSQL_ASSOC;
        }

        if ($this->handle === null) {
            $configs = new Config($this->configPath);

            $str = array();

            if ($configs->host !== null) {
                $str[] = 'host=' . $configs->host;
            }

            if ($configs->port !== null) {
                $str[] = 'port=' . $configs->port;
            }

            if ($configs->user !== null) {
                $str[] = 'user=' . $configs->user;
            }

            if ($configs->pass !== null) {
                $str[] = 'password=' . $configs->pass;
            }

            if ($configs->database !== null) {
                $str[] = 'dbname=' . $configs->database;
            }

            if ($configs->options !== null) {
                $str[] = 'options=\'' . $configs->options . '\'';
            }

            // host=sheep port=5432 dbname=test user=lamb password=bar
            $handle = \pg_connect(implode(' ', $str));

            if ($handle === false) {
                throw new Exception('Unable connect');
            }

            $this->handle = $handle;
        }
    }

    /**
     * Set select and bind values of the `::fetch()` method
     *
     * @param string $select
     * @param array<int, string> $binds
     */
    public function setFetch($select, array $binds = array())
    {
        self::resetExecution($this->fetchResult);

        $this->fetchSelect = $select;
        $this->fetchBinds = $binds;
    }

    /**
     * Set result mode (associative, numeric, or both) of the `::fetch()` method
     *
     * @param int $mode
     * @throws \Inphinit\Exception
     */
    public function setFetchMode($mode)
    {
        if ($mode === self::ASSOC || $mode === null) {
            $this->fetchMode = \PGSQL_ASSOC;
        } elseif ($mode === self::NUM) {
            $this->fetchMode = \PGSQL_NUM;
        } elseif ($mode === (self::ASSOC|self::NUM)) {
            $this->fetchMode = \PGSQL_BOTH;
        } else {
            throw new Exception('Invalid mode');
        }

        self::resetExecution($this->fetchResult);
    }

    /**
     * Set offset and limit of the `::fetch()` method
     *
     * @param int $offset
     * @param int $limit
     * @throws \Inphinit\Exception
     */
    public function setFetchOffset($offset, $limit)
    {
        if (is_int($offset) === false || $offset < 0) {
            throw new Exception('Invalid offset');
        }

        if (is_int($limit) === false || $limit < 1) {
            throw new Exception('Invalid limit');
        }

        self::resetExecution($this->fetchResult);

        $this->fetchOffset = $offset;
        $this->fetchLimit = $limit;
    }

    /**
     * Set offset and limit, basead in pagination, of the `::fetch()` method
     *
     * @param int $page
     * @param int $limit
     * @throws \Inphinit\Exception
     */
    public function setFetchPagination($page, $limit)
    {
        if (is_int($page) === false || $page < 1) {
            throw new Exception('Invalid page');
        }

        --$page;

        $this->setFetchOffset(($page * $limit), $limit);
    }

    /**
     * Fetch data
     *
     * @throws \Inphinit\Exception
     * @return array|false
     */
    public function fetch()
    {
        if ($this->fetchResult === null) {
            $this->boot();

            $binds = $this->fetchBinds;

            $binds[] = $this->fetchLimit;
            $binds[] = $this->fetchOffset;

            $query = $this->fetchSelect . ' LIMIT ? OFFSET ?';

            $this->execute($query, $binds, $result);

            $this->fetchResult = $result;
        }

        return \pg_fetch_array($this->fetchResult, null, $this->fetchMode);
    }

    /**
     * Shortcut to insert data from a table based on an SQL statement
     *
     * @param int $table
     * @param array<string,string> $entries
     * @throws \Inphinit\Exception
     * @return int
     */
    public function insert($table, array $entries)
    {
        self::isEmpty($entries, 'Entries is empty');

        $cols = array_keys($entries);
        $values = array_values($entries);

        foreach ($cols as $index => $value) {
            $substitute = $index + 1;
            $params[] = "\${$substitute}";
        }

        $query = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', $params) . ')';

        $changes = $this->execute($query, $values, $result);

        self::resetExecution($result);

        return $changes;
    }

    /**
     * Shortcut to delete data from a table based on an SQL statement
     *
     * @param string $table
     * @param array<string,string> $conditions
     * @throws \Inphinit\Exception
     * @return int
     */
    public function delete($table, array $conditions)
    {
        self::isEmpty($conditions, 'Conditions is empty');

        $substitute = 0;
        $where = array();

        foreach ($conditions as $column => $value) {
            ++$substitute;
            $where[] = $column . "=\${$substitute}";
        }

        $query = 'DELETE FROM ' . $table . ' WHERE ' . implode(' AND ', $where);

        $changes = $this->execute($query, $conditions, $result);

        self::resetExecution($result);

        return $changes;
    }

    /**
     * Shortcut to update data from a table based on an SQL statement
     *
     * @param string $table
     * @param array<string,string> $conditions
     * @param array<string,string> $updates
     * @throws \Inphinit\Exception
     * @return int
     */
    public function update($table, array $conditions, array $updates)
    {
        self::isEmpty($conditions, 'Conditions is empty');
        self::isEmpty($updates, 'Updates is empty');

        $binds = array();
        $where = array();
        $sets = array();

        $binds = array_merge(array_values($updates), array_values($conditions));

        foreach ($updates as $column => $value) {
            ++$substitute;
            $sets[] = $column . "=\${$substitute}";
        }

        foreach ($conditions as $column => $value) {
            ++$substitute;
            $where[] = $column . "=\${$substitute}";
        }

        $condition = implode(' AND ', $where);

        $query = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $condition;

        $changes = $this->execute($query, $binds, $result);

        self::resetExecution($result);

        return $changes;
    }

    /**
     * Shortcut to prepare an SQL statement, execute it, and return the total number of changes
     *
     * @param string $table
     * @param array<int,string> $values
     * @throws \Inphinit\Exception
     * @return int
     */
    public function exec($query, array $values = array())
    {
        $changes = $this->execute($query, $values, $result);

        self::resetExecution($result);

        return $changes;
    }

    /**
     * Obtains the handler used to manage the database
     *
     * @throws \Inphinit\Exception
     * @return \PgSql\Connection|resource
     */
    public function getHandler()
    {
        $this->boot();
        return $this->handle;
    }

    private function execute($query, array $binds, &$result)
    {
        $this->boot();

        $args = array();

        foreach ($binds as $bind => &$value) {
            if (is_int($value) === false && is_float($value) === false && is_string($value) === false) {
                $type = Inspector::type($value);
                throw new Exception('Unexpected ' . $type . ' value in a bind entry', 0, 3);
            }

            $args[] = $value;
        }

        // 'SELECT * FROM shops WHERE name = $1 or surname = $2'
        $result = \pg_query_params($this->handle, $query, $args);

        if ($result === false) {
            $result = null;
            $this->raiseLastError(4);
        }

        // Returns the number of rows deleted, inserted, or updated
        return \pg_affected_rows($result);
    }

    private function raiseLastError($level)
    {
        $message = \pg_result_error($this->handle);

        if ($message === '' || $message === false) {
            $message = 'Unknown error';
        }

        throw new Exception($message, 0, $level);
    }

    private function resetExecution(&$result)
    {
        if ($result !== null) {
            \pg_free_result($result);
            $result = null;
        }
    }

    public function __destruct()
    {
        self::resetExecution($this->fetchResult);

        if ($this->handle !== null) {
            \pg_close($this->handle);
        }
    }

    private static function isEmpty(array $array, $message)
    {
        if (empty($array)) {
            throw new Exception($message, 0, 3);
        }
    }
}
