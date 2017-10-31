<?php

/**
 * Bittr
 *
 * @license
 *
 * New BSD License
 *
 * Copyright (c) 2017, ghostff community
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *      1. Redistributions of source code must retain the above copyright
 *      notice, this list of conditions and the following disclaimer.
 *      2. Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in the
 *      documentation and/or other materials provided with the distribution.
 *      3. All advertising materials mentioning features or use of this software
 *      must display the following acknowledgement:
 *      This product includes software developed by the ghostff.
 *      4. Neither the name of the ghostff nor the
 *      names of its contributors may be used to endorse or promote products
 *      derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY ghostff ''AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL GHOSTFF COMMUNITY BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

declare(strict_types=1);

namespace DB;

use Closure;
use PDO;

/**
 * @method static Query evaluate(bool $just_sub = false): string
 * @method static Query setPrimary(string $table, string $primary_key): void
 * @method static Query table(string $table_name): Query
 * @method static Query limit(string $limit): Query
 * @method static Query order($column, string $sort = null): Query
 * @method static Query group(): Query
 * @method static Query fetchType(): Query
 * @method static Query one(): Query
 * @method static Query all(): Query
 * @method static Query select(): Query
 * @method static Query delete(bool $restart_AI = false): Query
 * @method static Query nuke(): Query
 * @method static Query drop()
 * @method static Query update(): Query
 * @method static Query insert(): Query
 * @method static Query replace(): Query
 * @method static Query count(string $column = '*', string $as = null): Query
 * @method static Query max(string $column, string $as = null)
 * @method static Query getArg(string $type = null, string $join = null, string $before = null): array
 * @method static Query getResult(bool $clear = false)
 * @method static Query where($condition, string $type = null, string $join = 'AND'): Query
 * @method static Query orWhere($condition, string $type = null): Query
 * @method static Query whereIn(string $column, $condition, string $join = 'AND'): Query
 * @method static Query whereAny(string $column, $condition, string $join = 'AND'): Query
 * @method static Query whereNotIn(string $column, $condition, string $join = 'AND'): Query
 * @method static Query whereExist(Closure $callback, string $join = 'AND')
 * @method static Query whereNotExist(Closure $callback, string $join = 'AND')
 * @method static Query whereRow(array $columns, $callback, string $type = null, string $join = null, string $before = null)
 * @method static Query sub(Closure $function, bool $sub = false): string
 * @method static Query __toString(): string
 * @method static Query getIterator(): ArrayIterator
 * @method static Query debug()
 * @method static Query query(): PDOStatement
 * @method static Query queryData(): array
 * @method static Query exec(string $statement): int
 * @method static Query lastInsertId(string $sequence = null): string
 * @method static Query begin(): bool
 * @method static Query commit(): bool
 * @method static Query rollback(): bool
 */
class DB
{
    private static $connection = null;

    public static $database = null;

    public static $listen = [];

    public static $dump_level = 0;

    public static $allow_clean = false;

    private static $driver;


    /**
     * Creates a static PDO instance representing a connection to a database
     *
     * @return DB
     */
    public static function init(): DB
    {
        if (empty($params = func_get_args()))
        {
            $driver = 'mysql';
            $host = 'localhost';
            $db_name = 'database_name';
            $db_user = 'database_username';
            $db_pass = 'database_password';
            $persistent_conn = false; # Use persistent connection.
            $save_path = __DIR__ . '/'; # Save path for sqlite

            if ($driver == 'sqlite')
            {
                $params = ["{$driver}:{$save_path}"];
            }
            else
            {
                $params = ["{$driver}:host={$host};dbname={$db_name}", $db_user, $db_pass];
            }
        }

        self::$connection = new PDO(...$params);
        self::$connection->setAttribute(PDO::ATTR_PERSISTENT, $persistent_conn ?? false);
        self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$connection->setAttribute(PDO::ATTR_STATEMENT_CLASS, [Statement::class, [&self::$database]]);
        self::$driver = self::$connection->getAttribute(PDO::ATTR_DRIVER_NAME);

        return new static;
    }

    /**
     * Close existing connection.
     *
     * @return DB
     */
    public static function close(): DB
    {
        self::$connection = null;
        self::$driver = null;
        self::$database = null;

        return new static;
    }

    /**
     * Creates a new connection instance
     *
     * @return DB
     */
    public static function restart(): DB
    {
        self::close();
        return self::init();
    }
    /**
     * Gets instance of Query class
     *
     * @return Query
     */
    public static function self(): Query
    {
        self::$database = new Query(self::connection());
        return self::$database;
    }

    /**
     * Executes a @Query command
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        /** @var Query $name */
        return self::self()->{$name}(...$arguments);
    }

    /**
     * Gets initialize PDO connection instance
     *
     * @return PDO
     */
    public static function connection(): PDO
    {
        if (! self::$connection)
        {
            self::init();
        }
        return self::$connection;
    }

    /**
     * Logs sql query,
     *
     * @param string|null $query
     * @param array $binds
     * @param string|null $error
     */
    public static function listen(string $query = null, array $binds = [], string $error = null): void
    {
        self::$listen[] = [
            'query' => $query,
            'binds' => $binds,
            'error' => $error
        ];
    }

    /**
     * Clear previous query log.
     *
     * @return static
     */
    public static function wipeRecord()
    {
        self::$listen = [];
        return new static();
    }

    /**
     * Show query log.
     *
     * @return array
     */
    public static function play(): array
    {
        return self::$listen;
    }

    /**
     * Get current driver.
     *
     * @return string
     */
    public static function getDriver(): string
    {
        return self::$driver;
    }

    /**
     * Add On duplicate argument to insert.
     *
     * @param array $duplicate
     * @return Raw
     */
    public static function onDuplicate(array $duplicate): Raw
    {
        return new Raw($duplicate);
    }
}
