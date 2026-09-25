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

class SQLite
{
    /* @var int returns an array indexed by column name as returned in the corresponding result set */
    const ASSOC = 1;

    /* @var int returns an array indexed by column number as returned in the corresponding result set, starting at column 0 */
    const NUM = 2;

    const BIND_PREFIX = 'bind';

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
            $this->fetchMode = \SQLITE3_ASSOC;
        }

        if ($this->handle === null) {
            $configs = new Config($this->configPath);

            $mode = $configs->mode !== null ? $configs->mode : \SQLITE3_OPEN_READWRITE;
            $key = $configs->encryption_key !== null ? $configs->encryption_key : '';

            try {
                $this->handle = new \SQLite3($configs->database, $mode, $key);
            } catch (\Exception $ex) {
                throw new Exception($ex->getMessage(), 0, 3, $ex);
            }
        }
    }

    /**
     * Set select and bind values of the `::fetch()` method
     *
     * @param string $select
     * @param array<string, string> $binds
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
        if ($mode === self::ASSOC || $mode === null) {
            $this->fetchMode = \SQLITE3_ASSOC;
        } elseif ($mode === self::NUM) {
            $this->fetchMode = \SQLITE3_NUM;
        } elseif ($mode === (self::ASSOC|self::NUM)) {
            $this->fetchMode = \SQLITE3_BOTH;
        } else {
            throw new Exception('Invalid mode');
        }

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

            $max = count($binds) + 2;

            $limit = self::createUniqueBind($this->fetchLimit, $max, $binds);
            $offset = self::createUniqueBind($this->fetchOffset, $max, $binds);

            $query = $this->fetchSelect . " LIMIT :{$limit} OFFSET :{$offset}";

            $this->execute($query, $binds, $stmt, $result);

            $this->fetchStmt = $stmt;
            $this->fetchResult = $result;
        }

        return $this->fetchResult->fetchArray($this->fetchMode);
    }

    /**
     * Shortcut to insert data from a table based on an SQL statement
     *
     * @param int $table
     * @param array $columns
     * @throws \Inphinit\Exception
     * @return int
     */
    public function insert($table, array $columns)
    {
        self::isEmpty($entries, 'Entries is empty');

        $cols = array_keys($columns);
        $binds = array();

        $max = count($columns);

        foreach ($columns as $value) {
            self::createUniqueBind($value, $max, $binds);
        }

        $values = ':' . implode(', :', array_keys($binds));

        $query = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . $values . ')';

        $changes = $this->execute($query, $binds, $stmt, $result);

        self::resetExecution($stmt, $result);

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

        $binds = array();
        $where = array();

        $max = count($conditions);

        foreach ($conditions as $column => $value) {
            $bind = self::createUniqueBind($value, $max, $binds);
            $where[] = $column . '=:' . $bind;
        }

        $query = 'DELETE FROM ' . $table . ' WHERE ' . implode(' AND ', $where);

        $changes = $this->execute($query, $binds, $stmt, $result);

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
     * @return int
     */
    public function update($table, array $conditions, array $updates)
    {
        self::isEmpty($conditions, 'Conditions is empty');
        self::isEmpty($updates, 'Updates is empty');

        $binds = array();
        $where = array();
        $sets = array();

        $max = count($conditions) + count($updates);

        foreach ($updates as $column => $value) {
            $bind = self::createUniqueBind($value, $max, $binds);
            $sets[] = $column . '=:' . $bind;
        }

        foreach ($conditions as $column => $value) {
            $bind = self::createUniqueBind($value, $max, $binds);
            $where[] = $column . '=:' . $bind;
        }

        $update = implode(', ', $sets);
        $condition = implode(' AND ', $where);

        $query = 'UPDATE ' . $table . ' SET ' . $update . ' WHERE ' . $condition;

        $changes = $this->execute($query, $binds, $stmt, $result);

        self::resetExecution($stmt, $result);

        return $changes;
    }

    /**
     * Shortcut to prepare an SQL statement, execute it, and return the total number of changes
     *
     * @param string $table
     * @param array<string,string> $values
     * @throws \Inphinit\Exception
     * @return int
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
     * @return \SQLite3
     */
    public function getHandler()
    {
        $this->boot();
        return $this->handle;
    }

    private function execute($query, array $binds, &$stmt, &$result)
    {
        $this->boot();

        $stmt = $this->handle->prepare($query);

        if ($stmt === false) {
            $this->raiseLastError(4);
        }

        foreach ($binds as $bind => $value) {
            if (is_string($bind) === false || strpos($bind, ':') !== false) {
                throw new Exception('Invalid key', 0, 3);
            }

            $bind = ':' . $bind;

            if ($value === null) {
                $stmt->bindValue($bind, $value, \SQLITE3_NULL);
            } elseif (is_int($value)) {
                $stmt->bindValue($bind, $value, \SQLITE3_INTEGER);
            } elseif (is_float($value)) {
                $stmt->bindValue($bind, $value, \SQLITE3_FLOAT);
            } elseif (is_string($value)) {
                $stmt->bindValue($bind, $value, \SQLITE3_TEXT);
            } else {
                $type = Inspector::type($value);
                throw new Exception('Unexpected ' . $type . ' value in a bind entry', 0, 3);
            }
        }

        $result = $stmt->execute();

        if ($result === false) {
            $stmt->clear();
            $stmt->reset();

            $stmt = null;

            $this->raiseLastError(4);
        }

        // Returns the number of rows deleted, inserted, or updated
        return $this->handle->changes();
    }

    private function raiseLastError($level)
    {
        $code = $this->handle->lastErrorCode();
        $message = $this->handle->lastErrorMsg();

        if ($message === '') {
            $message = 'Unknown error';
        }

        throw new Exception($message, $code, $level);
    }

    private function resetExecution(&$stmt, &$result)
    {
        if ($stmt !== null) {
            $stmt->clear();
            $stmt->reset();
            $stmt = null;
        }

        if ($result !== null) {
            $result->finalize();
            $result = null;
        }
    }

    private static function createUniqueBind($value, $max, array &$binds)
    {
        if (
            $value !== null &&
            is_int($value) === false &&
            is_float($value) === false &&
            is_string($value) === false
        ) {
            $type = Inspector::type($value);
            throw new Exception('Unexpected ' . $type . ' value in a bind entry', 0, 3);
        }

        $prefix = self::BIND_PREFIX;

        for ($i = 0; $i < $max; ++$i) {
            $bind = $prefix . $i;

            if (isset($binds[$bind]) === false) {
                $binds[$bind] = $value;
                return $bind;
            }
        }

        throw new Exception('Cannot create dynamic binds', 0, 3);
    }

    public function __destruct()
    {
        self::resetExecution($this->fetchStmt, $this->fetchResult);

        if ($this->handle !== null) {
            $this->handle->close();
        }
    }

    private static function isEmpty(array $array, $message)
    {
        if (empty($array)) {
            throw new Exception($message, 0, 3);
        }
    }
}
