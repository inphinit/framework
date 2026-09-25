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

class SQLServer
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
    private $fetchSelect;
    private $fetchStmt;

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
            $this->fetchMode = \SQLSRV_FETCH_ASSOC;
        }

        if ($this->handle === null) {
            $configs = new Config($this->configPath);

            $server = $configs->server;
            $info = $configs->info;

            if ($server === null || $info === null) {
                throw new Exception('Missing configuration', 0, 3);
            }

            $handle = \sqlsrv_connect($server, $info);

            if ($handle === false) {
                self::raiseLastError(3);
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
        self::resetExecution($this->fetchStmt);

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
            $this->fetchMode = \SQLSRV_FETCH_ASSOC;
        } elseif ($mode === self::NUM) {
            $this->fetchMode = \SQLSRV_FETCH_NUMERIC;
        } elseif ($mode === (self::ASSOC|self::NUM)) {
            $this->fetchMode = \SQLSRV_FETCH_BOTH;
        } else {
            throw new Exception('Invalid mode');
        }

        self::resetExecution($this->fetchStmt);
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

        self::resetExecution($this->fetchStmt);

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
        if ($this->fetchStmt === null) {
            $this->boot();

            $binds = $this->fetchBinds;

            $binds[] = $this->fetchOffset;
            $binds[] = $this->fetchLimit;

            $query = $this->fetchSelect . 'OFFSET ? ROWS FETCH NEXT ? ROWS ONLY';

            $this->execute($query, $binds, $stmt, $result);

            $this->fetchStmt = $stmt;
        }

        return \sqlsrv_fetch_array($this->fetchStmt, $this->fetchMode);
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

        $params = rtrim(str_repeat('?,', count($cols)), ',');

        $query = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . $params . ')';

        $changes = $this->execute($query, $entries, $stmt);

        self::resetExecution($stmt);

        return $changes;
    }

    /**
     * Shortcut to delete data from a table based on an SQL statement
     *
     * @param string $table
     * @param array<string,string> $contains
     * @throws \Inphinit\Exception
     * @return int
     */
    public function delete($table, array $contains)
    {
        self::isEmpty($contains, 'Conditions is empty');

        $where = array();

        foreach ($contains as $column => $value) {
            $where[] = $column . '=?';
        }

        $query = 'DELETE FROM ' . $table . ' WHERE ' . implode(' AND ', $where);

        $changes = $this->execute($query, $contains, $stmt, $result);

        self::resetExecution($stmt);

        return $changes;
    }

    /**
     * Shortcut to update data from a table based on an SQL statement
     *
     * @param string $table
     * @param array<string,string> $contains
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
            $sets[] = $column . '=?';
        }

        foreach ($conditions as $column => $value) {
            $where[] = $column . '=?';
        }

        $condition = implode(' AND ', $where);

        $query = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $condition;

        $changes = $this->execute($query, $binds, $stmt, $result);

        self::resetExecution($stmt);

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
        $changes = $this->execute($query, $values, $stmt, $result);

        self::resetExecution($stmt);

        return $changes;
    }

    /**
     * Obtains the handler used to manage the database
     *
     * @throws \Inphinit\Exception
     * @return resource
     */
    public function getHandler()
    {
        $this->boot();
        return $this->handle;
    }

    private function execute($query, array $binds, &$stmt)
    {
        $this->boot();

        $args = array($this->handle, $query);

        foreach ($binds as $bind => &$value) {
            if (is_int($value) === false && is_float($value) === false && is_string($value) === false) {
                $type = Inspector::type($value);
                throw new Exception('Unexpected ' . $type . ' value in a bind entry', 0, 3);
            }

            $args[] = &$value;
        }

        $stmt = call_user_func_array('sqlsrv_prepare', $args);

        if ($stmt === false) {
            $stmt = null;

            $this->raiseLastError(4);
        }

        if (\sqlsrv_execute($stmt) === false) {
            \sqlsrv_free_stmt($stmt);

            $stmt = null;

            $this->raiseLastError(4);
        }

        // Returns the number of rows deleted, inserted, or updated
        return \sqlsrv_rows_affected($stmt);
    }

    private function raiseLastError($level)
    {
        $errors = \sqlsrv_errors();

        if (isset($errors[0])) {
            $first_error = $errors[0];

            foreach( $errors as $error ) {
                $state = $error['SQLSTATE'];
                $code = $error['code'];
                $message = $error['message'];
            }
        } else {
            $code = 0;
            $message = 'Unknown error';
        }

        throw new Exception($message, $code, $level);
    }

    private function resetExecution(&$stmt)
    {
        if ($stmt !== null) {
            \sqlsrv_free_stmt($stmt);

            $stmt = null;
        }
    }

    public function __destruct()
    {
        self::resetExecution($this->fetchStmt);

        if ($this->handle !== null) {
            \sqlsrv_close($this->handle);
        }
    }

    private static function isEmpty(array $array, $message)
    {
        if (empty($array)) {
            throw new Exception($message, 0, 3);
        }
    }
}
