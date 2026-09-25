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

class DB2
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
            $this->fetchMode = self::ASSOC;
        }

        if ($this->handle === null) {
            $configs = new Config($this->configPath);

            $host = $configs->host;
            $port = $configs->port;
            $user = $configs->user;
            $pass = $configs->pass;
            $database = $configs->database;

            if ($host === null || $user === null || $pass === null || $database === null) {
                throw new Exception('Missing configuration', 0, 3);
            }

            $options = $configs->options;

            if ($options !== null && is_array($options) === false) {
                throw new Exception('Invalid options configuration', 0, 3);
            }

            $handle = \db2_connect($database, $user, $pass, $options);

            if ($handle === false) {
                throw new Exception('Unable connect', 0, 3);
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
        self::resetExecution($this->fetchStmt, $this->fetchResult);

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
        $valid_modes = self::ASSOC | self::NUM;

        if (is_int($mode) === false || ($mode & ~$valid_modes) !== 0) {
            throw new Exception('Invalid mode');
        }

        $this->fetchMode = $mode;

        self::resetExecution($this->fetchStmt, $this->fetchResult);
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

        self::resetExecution($this->fetchStmt, $this->fetchResult);

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

            $this->execute($query, $binds, $stmt, $result);

            $this->fetchStmt = $stmt;
            $this->fetchResult = $result;
        }

        switch ($this->fetchMode) {
            case self::ASSOC:
                return \db2_fetch_assoc($this->fetchResult);

            case self::NUM:
                return \db2_fetch_array($this->fetchResult);

            default:
                return \db2_fetch_both($this->fetchResult);
        }
    }

    /**
     * Shortcut to insert data from a table based on an SQL statement
     *
     * @param int $table
     * @param array<string,string> $entries
     * @throws \Inphinit\Exception
     * @return int Returns total affected rows
     */
    public function insert($table, array $entries)
    {
        self::isEmpty($entries, 'Entries is empty');

        $cols = array_keys($entries);

        $params = rtrim(str_repeat('?,', count($cols)), ',');

        $query = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . $params . ')';

        $changes = $this->execute($query, $entries, $stmt, $result);

        self::resetExecution($stmt, $result);

        return $changes;
    }

    /**
     * Shortcut to delete data from a table based on an SQL statement
     *
     * @param string $table
     * @param array<string,string> $conditions
     * @throws \Inphinit\Exception
     * @return int Returns total affected rows
     */
    public function delete($table, array $conditions)
    {
        self::isEmpty($contains, 'Conditions is empty');

        $where = array();

        foreach ($conditions as $column => $value) {
            $where[] = $column . '=?';
        }

        $query = 'DELETE FROM ' . $table . ' WHERE ' . implode(' AND ', $where);

        $changes = $this->execute($query, $conditions, $stmt, $result);

        self::resetExecution($stmt, $result);

        return $changes;
    }

    /**
     * Shortcut to update data from a table based on an SQL statement
     *
     * @param string $table
     * @param array<string,string> $conditions
     * @param array<string,string> $updates
     * @throws \Inphinit\Exception
     * @return int Returns total affected rows
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

        self::resetExecution($stmt, $result);

        return $changes;
    }

    /**
     * Shortcut to prepare an SQL statement, execute it, and return the total number of changes
     *
     * @param string $table
     * @param array<int,string> $values
     * @throws \Inphinit\Exception
     * @return int Returns total affected rows
     */
    public function exec($query, array $values = array())
    {
        $changes = $this->execute($query, $values, $stmt, $result);

        self::resetExecution($stmt, $result);

        return $changes;
    }

    /**
     * Obtains the handler used to manage the database
     *
     * @throws \Inphinit\Exception
     * @return \resource
     */
    public function getHandler()
    {
        $this->boot();
        return $this->handle;
    }

    private function execute($query, array $binds, &$stmt, &$result)
    {
        $this->boot();

        $stmt = \db2_prepare($conn, $query);

        if ($stmt === false) {
            $stmt = null;
            $this->raiseLastError(4);
        }

        $result = \db2_execute($stmt, $binds);

        if ($result === false) {
            $result = null;

            $code = \db2_stmt_error($this->handle);
            $message = \db2_stmt_errormsg($this->handle);

            if ($message === '') {
                $message = 'Unknown error';
            }

            throw new Exception($message, $code, 3);
        }

        // Returns the number of rows deleted, inserted, or updated
        return \db2_num_rows($stmt);
    }

    private function raiseLastError($level)
    {
        $code = \db2_conn_error($this->handle);
        $message = \db2_conn_errormsg($this->handle);

        if ($message === '') {
            $message = 'Unknown error';
        }

        throw new Exception($message, $code, $level);
    }

    private function resetExecution(&$stmt, &$result)
    {
        if ($stmt !== null) {
            \db2_free_stmt($stmt);
            $stmt = null;
        }

        if ($result !== null) {
            \db2_next_result($result);
            $result = null;
        }
    }

    public function __destruct()
    {
        self::resetExecution($this->fetchStmt, $this->fetchResult);

        if ($this->handle !== null) {
            \db2_close($this->handle);
        }
    }

    private static function isEmpty(array $array, $message)
    {
        if (empty($array)) {
            throw new Exception($message, 0, 3);
        }
    }
}
