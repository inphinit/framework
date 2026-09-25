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

class MySQL
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
            $this->fetchMode = \MYSQLI_ASSOC;
        }

        if ($this->handle === null) {
            $configs = new Config($this->configPath);

            $host = $configs->host;
            $port = $configs->port;
            $user = $configs->user;
            $pass = $configs->pass;
            $database = $configs->database;

            if ($host === null || $port === null || $user === null || $pass === null || $database === null) {
                throw new Exception('Missing configuration', 0, 3);
            }

            try {
                $handle = \mysqli_connect($host, $user, $pass, $database, $port);

                if ($handle === false) {
                    throw new \RuntimeException(\mysqli_connect_error(), \mysqli_connect_errno());
                }
            } catch (\Exception $ex) {
                throw new Exception($ex->getMessage(), 0, 3, $ex);
            }

            $charset = $configs->charset !== null ? $configs->charset : 'utf8mb4';
            $change_charset = false;

            try {
                $change_charset = \mysqli_set_charset($handle, $charset);

                if ($change_charset === false) {
                    throw new \RuntimeException(\mysqli_error($handle), \mysqli_errno($handle));
                }
            } catch (\Exception $ex) {
                \mysqli_close($handle);
                throw new Exception($ex->getMessage(), 0, 3, $ex);
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
        if ($mode === self::ASSOC || $mode === null) {
            $this->fetchMode = \MYSQLI_ASSOC;
        } elseif ($mode === self::NUM) {
            $this->fetchMode = \MYSQLI_NUM;
        } elseif ($mode === (self::ASSOC|self::NUM)) {
            $this->fetchMode = \MYSQLI_BOTH;
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

            $binds[] = $this->fetchLimit;
            $binds[] = $this->fetchOffset;

            $query = $this->fetchSelect . ' LIMIT ? OFFSET ?';

            $this->execute($query, $binds, $stmt, $result);

            $result = \mysqli_stmt_get_result($stmt);

            if ($result === false) {
                self::resetExecution($stmt, $result);
                $this->raiseLastError(4);
            }

            $this->fetchStmt = $stmt;
            $this->fetchResult = $result;
        }

        return $this->fetchResult->fetch_array($this->fetchMode);
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
     * @return int
     */
    public function delete($table, array $conditions)
    {
        self::isEmpty($conditions, 'Conditions is empty');

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

        self::resetExecution($stmt, $result);

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

        self::resetExecution($stmt, $result);

        return $changes;
    }

    /**
     * Obtains the handler used to manage the database
     *
     * @throws \Inphinit\Exception
     * @return \mysqli
     */
    public function getHandler()
    {
        $this->boot();
        return $this->handle;
    }

    private function execute($query, array $binds, &$stmt, &$result)
    {
        $this->boot();

        $stmt = \mysqli_prepare($this->handle, $query);

        if ($stmt === false) {
            $stmt = null;
            $this->raiseLastError(4);
        }

        if (PHP_VERSION_ID >= 80100) {
            if (\array_is_list($binds) === false) {
                $binds = array_values($binds);
            }

            $execution = \mysqli_stmt_execute($stmt, $binds);
        } else {
            $types = '';
            $args = array($stmt, '');

            foreach ($binds as $bind => &$value) {
                if (is_int($value)) {
                    $types .= 'i';
                } elseif (is_float($value)) {
                    $types .= 'd';
                } elseif (is_string($value)) {
                    $types .= 's';
                } else {
                    $type = Inspector::type($value);
                    throw new Exception('Unexpected ' . $type . ' value in a bind entry', 0, 3);
                }

                $args[] = &$value;
            }

            $args[1] = $types;

            call_user_func_array('mysqli_stmt_bind_param', $args);

            $execution = \mysqli_stmt_execute($stmt);
        }

        if ($execution === false) {
            \mysqli_stmt_free_result($stmt);
            \mysqli_stmt_reset($stmt);

            $stmt = null;

            $this->raiseLastError(4);
        }

        // Returns the number of rows deleted, inserted, or updated
        return \mysqli_stmt_affected_rows($stmt);
    }

    private function raiseLastError($level)
    {
        $code = \mysqli_errno($this->handle);
        $message = \mysqli_error($this->handle);

        if ($message === '') {
            $message = 'Unknown error';
        }

        throw new Exception($message, $code, $level);
    }

    private function resetExecution(&$stmt, &$result)
    {
        if ($stmt !== null) {
            \mysqli_stmt_free_result($stmt);
            \mysqli_stmt_reset($stmt);
            $stmt = null;
        }

        if ($result !== null) {
            \mysqli_free_result($result);
            $result = null;
        }
    }

    public function __destruct()
    {
        self::resetExecution($this->fetchStmt, $this->fetchResult);

        if ($this->handle !== null) {
            \mysqli_close($this->handle);
        }
    }

    private static function isEmpty(array $array, $message)
    {
        if (empty($array)) {
            throw new Exception($message, 0, 3);
        }
    }
}
