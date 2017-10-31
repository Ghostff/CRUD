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

use ArrayIterator;
use Closure;
use IteratorAggregate;
use JsonSerializable;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;


/**
 * Query object wrapping a PDO instance
 */
class Query implements IteratorAggregate, JsonSerializable
{
    /** @var null|PDO */
    protected $pdo = null;

    /** @var string */
    protected $identifierDelimiter = '`';

    /** @var array */
    protected $primary_keys = [];

    /** @var string */
    protected $table = null;

    /** @var array  */
    protected $binds = [];

    /** @var array */
    protected $condition_log = [];

    /** @var int  */
    private $position = 0;

    /** @var null  */
    private $condition = null;

    /** @var array  */
    protected $clause = [];

    /** @var string  */
    protected $query_string = '';

    /** @var bool  */
    protected $sub = false;

    /** @var array  */
    protected $fetch_arg  = [PDO::FETCH_OBJ];

    /** @var null  */
    private $results = null;

    /** @var bool  */
    private $debug = false;

    /** @var int  */
    private $transactionCount = 0;

    /** @var array  */
    private $comparisons = [
        'mysql' => ['=', '>', '<', '>=', '<=', '<>', '!=', '<=>']
    ];

    /** @var array  */
    private $conditions = [
        'mysql' => ['AND', 'OR']
    ];

    /**
     * Constructor. Sets PDO to exception mode.
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /**
     * Quote identifier
     *
     * @param string $identifier
     * @return string
     */
    private function quote(string $identifier): string
    {
        $delimiter = $this->identifierDelimiter;
        if (! (bool) $delimiter)
        {
            return $identifier;
        }

        $identifier = explode('.', $identifier);
        $identifier = array_map(function (string $part) use ($delimiter)
        {
            return $delimiter . str_replace($delimiter, $delimiter . $delimiter, $part) . $delimiter;
        }, $identifier);

        return implode('.', $identifier);
    }

    /**
     * Quotes array of columns.
     *
     * @param array $columns
     * @return array
     */
    private function mapAndQuote(array $columns): array
    {
        return array_map(function (string $values): string
        {
            if (preg_match('/^[a-z0-9_.]+$/', $values, $matches))
            {
                return $this->quote($matches[0]);
            }
        }, $columns);
    }

    /**
     * Render query strings based on called methods.
     *
     * @param bool $just_sub
     * @return string
     */
    public function evaluate(bool $just_sub = false): string
    {
        $query = '';
        foreach ($this->clause as $clause => $values)
        {
            $began = false;
            $has_where = false;

            if (! empty($values))
            {
                if (! $just_sub)
                {
                    if (isset($values['select']))
                    {
                        $query .= "SELECT {$values['select']} FROM {$this->quote($this->table)} ";
                        $began = true;
                    }
                    elseif (isset($values['count']))
                    {
                        $ref = $values['count'][1];
                        $ref = $ref ? "AS {$ref} " : '';
                        $query .= "SELECT COUNT({$values['count'][0]}) {$ref}FROM {$this->quote($this->table)} ";
                        $began = true;
                    }
                    elseif (isset($values['max']))
                    {
                        $ref = $values['max'][1];
                        $ref = $ref ? "AS {$ref} " : '';
                        $query .= "SELECT MAX({$values['max'][0]}) {$ref}FROM {$this->quote($this->table)} ";
                        $began = true;
                    }
                    elseif (isset($values['min']))
                    {
                        $ref = $values['min'][1];
                        $ref = $ref ? "AS {$ref} " : '';
                        $query .= "SELECT MIN({$values['min'][0]}) {$ref}FROM {$this->quote($this->table)} ";
                        $began = true;
                    }
                    elseif (isset($values['delete']))
                    {
                        $query .= "DELETE FROM {$this->quote($this->table)} ";
                        $began = true;
                    }
                    elseif (isset($values['update']))
                    {
                        $query .= "UPDATE {$this->quote($this->table)} SET {$values['update']} ";
                        $began = true;
                    }
                    elseif (isset($values['insert']))
                    {
                        $query .= "INSERT INTO {$this->quote($this->table)} ({$values['insert'][0]}) ";
                        $insert = $values['insert'];
                        if ($insert[1])
                        {
                            $sub = strpos($insert[1], 'SELECT');
                            $query .= ($sub === false) ? "VALUES{$insert[1]} " : "{$insert[1]} ";
                        }

                        if (isset($values['duplicate']))
                        {
                            $query .= 'ON DUPLICATE KEY UPDATE ' . $values['duplicate'];
                        }
                        $began = true;
                        $has_where = true;
                    }
                    elseif (isset($values['replace']))
                    {
                        $replace = $values['replace'];
                        $query .= "REPLACE INTO {$this->quote($this->table)} ({$replace[0]}) ";
                        if ($replace[1])
                        {
                            $sub = strpos($replace[1], 'SELECT');
                            $query .= ($sub === false) ? "VALUES{$replace[1]} " : "{$replace[1]} ";
                        }
                        $began = true;
                        $has_where = true;
                    }

                    if (isset($values['where']))
                    {
                        if ($began)
                        {
                            $query .= 'WHERE ';
                        }
                        $query .= "{$this->suffix($values['where'])} ";
                        $has_where = true;
                    }

                    if (isset($values['group']))
                    {
                        $query .= "GROUP BY {$values['group']} ";
                    }

                    if (isset($values['order']))
                    {
                        $query .= 'ORDER BY ';
                        foreach ($values['order'] as $orders)
                        {
                            $query .= $orders . ', ';
                        }
                        $query = trim($query, ', ');
                    }

                    if (isset($values['limit']))
                    {
                        $query .= "LIMIT {$values['limit']} ";
                    }

                    if (isset($values['in']))
                    {
                        foreach ($values['in'] as $key => $vals)
                        {
                            $query .= $has_where ? "{$vals[2]} " : "WHERE ";

                            $query .= "{$this->quote($vals[0])} " . ($vals[3] ? 'NOT IN ' : 'IN ');
                            $query .= "({$vals[1]}) ";
                            $has_where = true;
                        }
                    }

                    if (isset($values['where_row']))
                    {
                        foreach ($values['where_row'] as $key => $vals)
                        {
                            $query .= $has_where ? "{$vals[3]} " : "WHERE ";
                            $before = $vals[4] ? " {$vals[4]} " : '';
                            $query .= "({$vals[0]}) {$vals[1]} {$before}{$vals[2]}";
                        }
                    }

                    if (isset($values['exist']))
                    {
                        foreach ($values['exist'] as $key => $vals)
                        {
                            $query .= $has_where ? "{$vals[1]} " : "WHERE ";

                            $query .= $vals[2] ? 'NOT EXISTS ' : 'EXISTS ';
                            $query .= "({$vals[0]})";
                            $has_where = true;
                        }
                    }
                }

                if (isset($values['sub']))
                {
                    /** @var Query $sub */
                    $sub = $values['sub'];
                    $query .= $sub->evaluate();
                }

                if ($began)
                {
                   unset($this->clause[$clause]);
                   $query = trim($query);
                   if (! $this->sub)
                   {
                       $query .= ';';
                   }
                }

            }
        }

        $this->query_string .= $query;

        return $this->query_string;
    }

    /**
     * Push an IN or NOT IN cause to render queue
     *
     * @param string $column
     * @param $condition
     * @param string $join
     * @param bool $not_in
     * @return $this
     */
    private function in(string $column, $condition, string $join, bool $not_in)
    {
        $query = null;
        $this->condition = false;
        if (is_array($condition))
        {
            foreach ($condition as $col => $bind)
            {
                $query .= '?, ';
                $this->binds[] = $bind;
            }
            $query = rtrim($query, ', ');
        }
        elseif (is_callable($condition))
        {
            $query = $this->sub($condition);
        }
        else
        {
            $query = $condition;
        }

        $this->clause[$this->position]['in'][] = [$column, $query, $join, $not_in];

        return $this;
    }

    /**
     * Push EXISTS or NOT EXISTS to render queue.
     *
     * @param Closure $sub_query
     * @param string $join
     * @param bool $not
     * @return $this
     */
    private function exists(Closure $sub_query, string $join, bool $not)
    {
        $this->clause[$this->position]['exist'][] = [$this->sub($sub_query), $join, $not];

        return $this;
    }

    private function makePosition()
    {
        if (! $this->sub && ! empty($this->clause[$this->position]))
        {
            ++$this->position;
        }
    }

    /**
     * Queues a table primary key.
     *
     * @param string $table
     * @param string $primary_key
     */
    public function setPrimary(string $table, string $primary_key): void
    {
        $this->primary_keys[$table] = $primary_key;
    }

    /**
     * Set an active table.
     *
     * @param string $table_name
     * @return Query
     */
    public function table(string $table_name): Query
    {
        $this->makePosition();
        $this->table = $table_name;
        return $this;
    }

    /**
     * before a clone.
     */
    public function __clone()
    {
        $this->table = null;
        $this->position = 0;
        $this->sub = true;
        $this->clause = [];
    }

    /**
     * Adds suffix to string based on database driver
     *
     * @param array $data
     * @return string
     */
    private function suffix(array $data): string
    {
        $tmp = null;
        $started = false;

        array_shift($this->condition_log);
        foreach ($data as $conditions) {
            if ($started) {
                $tmp .= ' ' . array_shift($this->condition_log) . ' ';
            }

            if (is_string($conditions)) {
                $tmp .= "{$conditions}";
            } else {
                /** @var Query $conditions */
                $tmp .= "({$conditions->evaluate()})";
            }

            $started = true;
        }

        return $tmp;
    }

    /**
     * Sets up query parameter and values needed for table insertion or update
     *
     * @param array $params
     * @param string $method
     * @return array
     */
    private function params(array $params, string $method): array
    {
        $type = array_shift($params);
        $query = '';
        $binds = [];
        $values = null;

        switch ($method)
        {
            case 'insert':
                if (is_array($type))
                {
                    $values = '(';
                    $sub = false;
                    foreach ($type as $col => $bind)
                    {
                        $query .= "{$this->quote($col)}, ";
                        if (is_callable($bind))
                        {
                            $values = $this->sub($bind);
                            $sub = true;
                        }
                        else
                        {
                            $values .= '?, ';
                            $binds[] = $bind;
                        }
                    }
                    if (! $sub)
                    {
                        $values = rtrim($values, ', ') . ')';
                    }

                    if (! empty($params))
                    {
                        $multi = $this->params($params, $method);
                        if (! empty($multi[2]))
                        {
                            $values .= ", {$multi[1]}";
                            array_push($binds, ...$multi[2]);
                        }
                    }
                }
                elseif ($type instanceof Raw)
                {
                    $this->onDuplicate($type->get());
                }
                else
                {
                    $query = $type;
                    $binds = $params;
                }
                break;
            case 'update':
                if (is_array($type))
                {
                    foreach ($type as $col => $values)
                    {
                        if (is_callable($values))
                        {
                            $query .= "{$this->quote($col)} = {$this->sub($values, true)}, ";
                        }
                        else
                        {
                            $query .= "{$this->quote($col)} = ?, ";
                            $binds[] = $values;
                        }
                    }
                }
                else
                {
                    $query = $type;
                    $binds = $params;
                }
                break;
        }

        return [rtrim($query, ', '), $values, $binds];
    }

    /**
     * Push a query execution limit to render queue.
     *
     * @param string $limit
     * @return Query
     */
    public function limit(string $limit): Query
    {
        $this->clause[$this->position]['limit'] = $limit;
        return $this;
    }

    /**
     * Push ORDER BY to render queue.
     *
     * @param $column
     * @param string|null $sort
     * @return Query
     */
    public function order($column, string $sort = null): Query
    {
        $order = null;
        if (is_array($column))
        {
            foreach ($column as $col => $sort)
            {
                $order .= "{$this->quote($col)} {$sort}, ";
            }
            $order = rtrim($order, ', ');
        }
        else
        {
            $order = "{$this->quote($column)} {$sort}";
        }

        $this->clause[$this->position]['order'][] = $order;

        return $this;
    }

    /**
     * Push GROUP BY to render queue.
     *
     * @return Query
     */
    public function group(): Query
    {
        $this->clause[$this->position]['group'] = implode(', ', $this->mapAndQuote(func_get_args()));
        return $this;
    }

    /**
     * Sets PDO fetch type
     *
     * @return Query
     */
    public function fetchType(): Query
    {
        $this->fetch_arg = func_get_args();
        return $this;
    }

    /**
     * Executed rendered queries.
     *
     * @param int $fetch_type
     * @return array|mixed|null|PDOStatement
     */
    private function execute(int $fetch_type = 0)
    {
        $this->makePosition();
        if ($this->sub)
        {
            return null;
        }

        if ($this->debug)
        {
            $this->evaluate();
            return null;
        }
        $statement = $this->pdo->prepare($this->evaluate());
        $statement->execute($this->binds);
        switch ($fetch_type)
        {
            case 0:
                return $statement;
            case 1:
                return $statement->fetchAll(...$this->fetch_arg);
            case 2:
                return $statement->fetch(...$this->fetch_arg);
            case 3:
                return $statement->fetchColumn();
        }
    }

    /**
     * Fetch a single rows from a table.
     *
     * @return Query
     */
    public function one(): Query
    {
        $args = func_get_args();
        $this->clause[$this->position]['select'] = empty($args) ? '*' : implode(', ', array_map([$this, 'quote'], $args));
        $this->results = $this->execute(2);

        return $this;
    }

    /**
     * Fetch all rows from a table. uses pdo fetchAll
     *
     * @return Query
     */
    public function all(): Query
    {
        return $this->select(...func_get_args());
    }

    /**
     * Run a select query based on previously called methods
     *
     * @return Query
     */
    public function select(): Query
    {
        $args = func_get_args();
        $this->clause[$this->position]['select'] = empty($args) ? '*' : implode(', ', array_map([$this, 'quote'], $args));
        $this->results = $this->execute(1);

        return $this;
    }

    /**
     * Runs a delete query based on previously called methods.
     *
     * @param bool $restart_AI
     * @return Query
     */
    public function delete(bool $restart_AI = false): Query
    {
        $this->clause[$this->position]['delete'] = true;
        $this->results = $this->execute(0);
        if ($restart_AI)
        {
            $this->exec('ALTER TABLE ' . $this->quote($this->table) . ' AUTO_INCREMENT = 1');
        }

        return $this;
    }

    /**
     * Delete all records in a table.
     *
     * @return Query
     */
    public function nuke(): Query
    {
        switch (DB::getDriver())
        {
            case 'sqlite':
                $this->delete()->exec('VACUUM;');
                break;
            case 'mysql':
                $this->exec('TRUNCATE TABLE ' . $this->quote($this->table) . ';');
                break;
        }

        return $this;
    }

    public function drop()
    {
        $this->exec('DROP TABLE ' . $this->quote($this->table) .';');
        return $this;
    }

    /**
     * Run an update query based on previously called methods.
     *
     * @return Query
     */
    public function update(): Query
    {
        list($query, ,$binds) = $this->params(func_get_args(), 'update');

        if ($query)
        {
            $this->clause[$this->position]['update'] = $query;
            $this->binds = array_merge($binds, $this->binds);
        }
        $this->results = $this->execute(0);

        return $this;
    }

    /**
     * Run an insert query based on previously called methods.
     *
     * @return Query
     */
    public function insert(): Query
    {
        list($query, $values, $binds) = $this->params(func_get_args(), 'insert');

        if ($query )
        {
            $this->clause[$this->position]['insert'][] = $query;
            $this->clause[$this->position]['insert'][] = $values;
            $this->binds = array_merge($binds, $this->binds);
        }
        $this->results = $this->execute(0);

        return $this;
    }

    public function replace(): Query
    {
        list($query, $values, $binds) = $this->params(func_get_args(), 'insert');

        if ($query )
        {
            $this->clause[$this->position]['replace'][] = $query;
            $this->clause[$this->position]['replace'][] = $values;
            $this->binds = array_merge($binds, $this->binds);
        }
        $this->results = $this->execute(0);

        return $this;
    }

    /**
     * Run a row count query based on previously called methods.
     *
     * @param string $column
     * @param string|null $as
     * @return Query
     */
    public function count(string $column = '*', string $as = null): Query
    {
        if ($column != '*')
        {
            $column = $this->quote($column);
        }
        $this->clause[$this->position]['count'][] = $column;
        $this->clause[$this->position]['count'][] = $as;
        $this->results = (int) $this->execute(3);

        return $this;
    }

    /**
     * Run a MAX query based on previously called methods.
     *
     * @param string $column
     * @param string|null $as
     * @return $this
     */
    public function max(string $column, string $as = null)
    {
        $this->clause[$this->position]['max'][] = $column;
        $this->clause[$this->position]['max'][] = $as;
        $this->results = $this->execute(0);

        return $this;
    }

    /**
     * Run a MIN query based on previously called methods
     *
     * @param string $column
     * @param string|null $as
     * @return $this
     */
    public function min(string $column, string $as = null)
    {
        $this->clause[$this->position]['min'][] = $column;
        $this->clause[$this->position]['min'][] = $as;
        $this->results = $this->execute(0);

        return $this;
    }


    public function onDuplicate(array $arguments): Query
    {
        $duplicate = null;
        foreach ($arguments as $column => $values)
        {
            if (is_callable($values))
            {
                $values = $this->sub($values, true);
            }
            $duplicate .= "{$this->quote($column)} = {$values}, ";
        }
        $this->clause[$this->position]['duplicate'] = rtrim($duplicate, ', ');

        return $this;
    }
    /**
     * Get appropriate argument for each parameter (smartArg)
     *
     * @param string|null $type
     * @param string|null $join
     * @param string|null $before
     * @return array
     */
    public function getArg(string $type = null, string $join = null, string $before = null): array
    {
        $driver = DB::getDriver();
        if ($driver == 'sqlite')
        {
            $driver = 'mysql';
        }
        $sorted = [$type, $join, $before];
        switch ($sorted)
        {
            case [$type, null, null]:
                if (! in_array($type, $this->comparisons[$driver]))
                {
                    if (in_array($type, $this->conditions[$driver]))
                    {
                        $sorted = [null, $type, null];
                    }
                    else
                    {
                        $sorted = [null, null, $type];
                    }
                }
                break;
            case [$type, $join, null]:
                $sorted = array_filter($this->getArg($type)) + $this->getArg($join);
                break;
            default:
                $sorted = array_filter($this->getArg($type)) + array_filter($this->getArg($join)) + $this->getArg($before);
        }
        return $sorted;
    }

    /**
     * Get result of last executed query.
     *
     * @param bool $clear
     * @return null
     */
    public function getResult(bool $clear = false)
    {
        $result = $this->results;
        if ($clear)
        {
            $this->results = null;
        }
        return $result;
    }

    /**
     * Push a WHERE condition to query queue.
     *
     * @param $condition
     * @param string|null $type
     * @param string $join
     * @return Query
     */
    public function where($condition, string $type = null, string $join = 'AND'): Query
    {
        if (! is_string($condition))
        {
            list($type, $join) = $this->getArg($type, $join);
        }

        $this->condition_log[] = $join;
        $query =& $this->clause[$this->position];

        if (is_array($condition))
        {
            $tmp = null;
            foreach ($condition as $cols => $values)
            {
                $type = $type ?? '=';
                $tmp .= "{$this->quote($cols)} {$type} ?";
                $this->binds[] = $values;
            }
            $query['where'][] = $tmp;
        }
        elseif (is_callable($condition))
        {
            $query['where'][] = $this->sub($condition, true);
        }
        else
        {
            $query['where'][] = $condition;
            if ($type)
            {
                $this->binds[] = $type;
            }
        }

        $this->condition = true;
        return $this;
    }

    /**
     * Push OR WHERE condition to query queue.
     *
     * @param $condition
     * @param string|null $type
     * @return Query
     */
    public function orWhere($condition, string $type = null): Query
    {
        return $this->where($condition, $type, 'OR');
    }

    /**
     * Push WHERE IN condition to condition to query queue.
     *
     * @param string $column
     * @param $condition
     * @param string $join
     * @return Query
     */
    public function whereIn(string $column, $condition, string $join = 'AND'): Query
    {
        return $this->in($column, $condition, $join, false);
    }

    /**
     * Push WHERE ANY condition condition to query queue.
     *
     * @param string $column
     * @param $condition
     * @param string $join
     * @return Query
     */
    public function whereAny(string $column, $condition, string $join = 'AND'): Query
    {
        return $this->in($column, $condition, $join, false);
    }

    /**
     * Push WHERE NOT IN condition condition to query queue.
     *
     * @param string $column
     * @param $condition
     * @param string $join
     * @return Query
     */
    public function whereNotIn(string $column, $condition, string $join = 'AND'): Query
    {
        return $this->in($column, $condition, $join, true);
    }

    /**
     * Push WHERE EXIST condition condition to query queue.
     *
     * @param Closure $callback
     * @param string $join
     * @return Query
     */
    public function whereExist(Closure $callback, string $join = 'AND')
    {
        return $this->exists($callback, $join, false);
    }

    /**
     * Push WHERE NOT EXIST condition to query queue.
     *
     * @param Closure $callback
     * @param string $join
     * @return Query
     */
    public function whereNotExist(Closure $callback, string $join = 'AND')
    {
        return $this->exists($callback, $join, true);
    }

    /**
     * Push WHERE (ROW(row, row) = (...)) condition to query queue.
     *
     * @param array $columns
     * @param $callback
     * @param string|null $type
     * @param string|null $join
     * @param string|null $before
     * @return $this
     */
    public function whereRow(array $columns, $callback, string $type = null, string $join = null, string $before = null)
    {
        list($type, $join, $before) = $this->getArg($type, $join, $before);

        $sub = is_callable($callback) ? $this->sub($callback, true) : $callback;
        $this->clause[$this->position]['where_row'][] = [
            implode(',', $this->mapAndQuote($columns)),
            $type ?? '=',
            $sub,
            $join ?? 'AND',
            $before
        ];

        return $this;
    }

    /**
     * Evaluates a sub query.
     *
     * @param Closure $function
     * @param bool $sub
     * @return string
     */
    public function sub(Closure $function, bool $sub = false): string
    {
        $clone = clone $this;
        $function($clone);
        $this->binds = $clone->binds;
        $this->condition_log = $clone->condition_log;

        return $sub ? "({$clone->evaluate()})" : $clone->evaluate();
    }

    /**
     * When string access.
     * @return string
     */
    public function __toString(): string
    {
        return $this->query_string;
    }

    /**
     * On array access.
     *
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->results);
    }

    /**
     * On json access.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->results ?? [];
    }

    /**
     * Asserts that all proceeding query is not to be executed.
     *
     * @return $this
     */
    public function debug()
    {
        $this->debug = true;
        return $this;
    }

    /**
     * Run a PDO query
     *
     * @return PDOStatement
     */
    public function query(): PDOStatement
    {
        $arguments = func_get_args();
        try {
            $query = $this->pdo->query(...$arguments);
            DB::listen($arguments[0]);
            return $query;
        }
        catch (Throwable $exception) {
            DB::listen($arguments[0], [], $exception->getMessage());
            throw new PDOException($exception->getMessage());
        }
    }

    /**
     * Execute an SQL statement and return the number of affected rows.
     *
     * @param string $statement
     * @return int
     */
    public function exec(string $statement): int
    {
        try {
            $exec = $this->pdo->exec($statement);
            DB::listen($statement);
            return $exec;
        }
        catch (Throwable $exception) {
            DB::listen($statement, [], $exception->getMessage());
            throw new PDOException($exception->getMessage());
        }
    }

    /**
     * Get query data.
     *
     * @return array
     */
    public function queryData(): array
    {
        return ['query' => trim($this->query_string), 'binds' => $this->binds];
    }

    /**
     * Return last inserted id.
     *
     * @param string|null $sequence
     * @return string
     */
    public function lastInsertId(string $sequence = null): string
    {
        return $this->pdo->lastInsertId($sequence);
    }

    /**
     * Begin a transaction
     *
     * @return Query
     */
    public function begin(): Query
    {
        if (! $this->transactionCount++)
        {
            $this->pdo->beginTransaction();
            return $this;
        }
        $this->exec('SAVEPOINT trans' . $this->transactionCount);

        return $this;
    }

    /**
     * Commit changes of transaction
     *
     * @return Query
     */
    public function commit(): Query
    {
        if (! --$this->transactionCount)
        {
            $this->pdo->commit();
        }

        return $this;
    }
    /**
     * Rollback any changes during transaction
     *
     * @return Query
     */
    public function rollback(): Query
    {
        if (--$this->transactionCount) {
            $this->exec('ROLLBACK TO trans' . ($this->transactionCount + 1));
            return $this;
        }

        $this->pdo->rollBack();

        return $this;
    }
}
