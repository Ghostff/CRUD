<?php

/*
*  Custom Exception handling;
*/
namespace Exception {
    
    class DBException
    {
        /*
        * outputs customized exception
        * return null
        *
        * @param php Exception object
        */
        public static function init($exception)
        {
            $line = $exception->getLine();
            $line -= 1;
            $file = file($exception->getFile());
        
            $err_top = $err_bottom = null;
            for ($i = 1, $k = 5; $i <= 5; $i++, $k--) {
                $current = $line - $k;
                if (isset($file[$current])) {
                    $err_top .= $current + 1 .' ' . $file[$current];
                }
                $current = $line + $i;
                if (isset($file[$current])) {
                    $err_bottom .= $current + 1  .' ' .  $file[$current];
                }
            }
            
            $new_line = $line+1;
            $current = '``@~~' . $new_line . $file[$line];
            $error = $err_top . $current . $err_bottom;
            
            $error = highlight_string('<?php ' . $error, true);
            
            $error = preg_replace(
                '#(``@~~)(.*?)<br\s*/>#', 
                '<div style="background:#EFEB8B">$2</div>',
                $error
            );
            
            $error = str_replace('&lt;?php&nbsp;', '', $error);
            die('<code><div style="width:60%">
                Error: <b style="background:#FF7275;">' . $exception->getMessage()
                . '</b><br />At ' .  $exception->getFile()
                . '<br />Line: ' . $new_line
                . '<p /><div style="border:1px solid #ddd;">' . $error . '</div>'
                . str_replace('#', '<br />#', $exception->getTraceAsString())
                . '</div></code>'
            );
        }
    }
    //initialize custom Exception
    set_exception_handler(array(
        'Exception\DBException',
        'init'
    ));
}

/*
*  PDO initialization and configuration
*/
namespace PDOConnection {
    
    use \PDO;
    
    class DB
    {        
        //hold pdo object on succesfull connection
        public static $conn = null;
        
        // allow PDO debuging
        private static $debug = false;
        
        // create active db if not created
        private static $force_DB = false;
        
        // create active table if not created
        private static $force_table = false;
        
        //holds global table name
        public static $table = null;
        
        //auto fix query switch
        public static $auto_fix = false;
        
        
        /*
        * Update DB properties
        * return null
        *
        * @param array of DB defined propertied where key is the property
        * name and value is the property new value
        *
        * debug = debug |  forceDB = force_DB | forceTbl = forceRow
        * 
        */
        public static function Config($attribute)
        {
            if (is_array($attribute)) {
                if (array_key_exists('debug', $attribute)) {
                    static::$debug = $attribute['debug'];    
                }
                if (array_key_exists('forceDB', $attribute)) {
                    static::$force_DB = $attribute['forceDB'];    
                }
                if (array_key_exists('forceTbl', $attribute)) {
                    static::$force_table = $attribute['forceTbl'];    
                }
                if (array_key_exists('autoFix', $attribute)) {
                    static::$auto_fix = $attribute['autoFix'];    
                }
            }
        }
        
        /*
        * initialize a new PDO connection
        * return null
        *
        * @param database host name
        * @parma database name
        * @param database username
        * @param databse password
        */
        public static function init($host, $DBName, $username, $password)
        {
            $pdo = new PDO('mysql:host=' . $host, $username, $password);
            if (static::$debug) {
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            if (static::$force_DB) {
                $pdo->query('CREATE DATABASE IF NOT EXISTS ' . $DBName);
            }
            
            if ($DBName) {
                $pdo->query('USE ' . $DBName);
            }
            static::$conn = $pdo;
        }
        
        /*
        * set a global table name
        * return null
        *
        * @param table name

        */
        public static function setTable($tableName)
        {
            static::$table = $tableName;
        }
        
    }
    
}

/*
*  Auxiliary functions
*/
namespace Auxiliary {
    
    class Methods
    {
        /*
        * renderes query as string
        * return string or rendered query
        *
        * @param pdo query
        * @param active class
        */
        public static function Stringfy($query, $data, $forQuery)
        {
            if ($forQuery) {
                $string = array(
                    'query'    => $query,
                    'data'    => $data
                );
            }
            else {
                $string = '<code>Query: '. $query . '</code><br />';
                $string .= '<code>Data: '. json_encode($data) . '</code>';
            }
            return $string;
        }
        
        private static function uniqueKey(&$key, &$array, $value = null)
        {    
            if (array_key_exists(':' . $key, $array)) {
                $key = 'w' . $key;
                static::uniqueKey($key, $array, $value);
            }
            else {
                $array[':' . $key] = $value;
            }
        }
        
        public static function where($name, $value, $condition, $class, $const = null)
        {        
            $statement = null;
            if ( ! is_array($name)) {
                if ($value) {
                    
                    $key = 'w' . $name;
                    static::uniqueKey($key, $class->col_and_val, $value);
                    $statement = sprintf('`%s` %s :%s', $name, $condition, $key);
                }
                else {
                    $statement = $name;
                }
            }
            else {
                if ( ! $value) {
                    $opperator = 'AND';    
                } else {
                    $opperator = strtoupper($value);
                }
                
                $condition = explode(',', $condition);
                $key = 0;
                
                $data = array_map(
                    function ($name, $value)
                    use ($opperator, $class, $condition, &$statement, &$key, $const) {

                        if ($const && is_numeric($name)) {
                            $name = $const;
                        }
                        
                        if (isset($condition[$key])) {
                            $condition = trim($condition[$key]) ?: '=';
                        } else {
                            $condition = '=';
                        }
                        
                        //$statement  = `columnName` (=|>|<..) :wcolumnName (AND|OR..)
                        $uniq_key = 'w' . $name;
                        static::uniqueKey($uniq_key, $class->col_and_val, $value);
                        $statement .= sprintf(
                            '`%s` %s :%s %s ', $name, $condition, $uniq_key, $opperator
                        );
                        $key++;
                        
                    }, array_keys($name), array_values($name)
                );
                
                $statement = rtrim($statement, 'AND ');
                $statement = rtrim($statement, 'OR ');
            }
            return $statement;
        }
        
        public static function makeQueryFunc($column, $keyword = null)
        {
            var_dump($column);
            if ($keyword) {
                $pattern = sprintf(
                    '/^(%1$s)\:(.*)|(%1$s)\((.*)\)|(%1$s)$/i', 
                    $keyword
                );
            }
            else {
                $pattern = '/^(\w+)\:(.*)|(\w+)\((.*)\)$/i';
            }
            
            if (preg_match($pattern, $column, $matched)) {
                
                $matched = array_values(array_filter($matched));
                
                $count = null;
                $clause = null;
                
                if (isset($matched[2])) {
                    $count = trim($matched[2]);
                }
                else {
                    $count = '*';
                }
                
                $clause = strtoupper($matched[1]);
                
               return sprintf('%s(%s)', $clause, $count);
            }
            
            return false;
        }
    }
}

/*
*  PDO quries
*/
namespace Sql {
    
    use PDOConnection\DB as DB;
    use Auxiliary\Methods as Auxi;
       
    class Select
    {
        private $built = null;
        
        private $query = null;
        
        private $map = null;
        
        private $auto_fix = array();
        
        public $col_and_val = array();
        
        public $count = 0;
        
        public $fall_back = null;
        
        private function modifyColumn($columns)
        {
            $new_column = null;
            if ( ! is_array($columns)) {
                $columns = preg_split("/(?<!\'),/", $columns);
            }
            
            foreach ($columns as $column) {
                
                $column = trim($column);
                
                if ($column == '*') {
                    
                    $new_column = '*';
                }
                else {
                    
                    $asPathern = '/as:(.*)\s*|as (.*)\s*/i';
                    if (preg_match($asPathern, $column, $matched)) {
                        
                        $matched = (array_values(array_filter($matched)));
                        $column = str_replace($matched[0], 'AS ' . $matched[1], $column);
                        
                    }

                    if ($match = Auxi::makeQueryFunc($column)) {
                        $new_column .= $match . ', ';
                    }
                    elseif (strpos($column, '.') !== false) {
                    
                        $pattern = '/(.*)\.(\w+)\s*(.*)/i';
                        if (preg_match($pattern, $column, $matched)) {
                            @$new_column .= sprintf('%s.`%s` %s, ', $matched[1], $matched[2], $matched[3]);
                        }
                        
                    }
                    else {
                        $new_column .= sprintf('`%s`, ', $column);
                    }
                    
                }
            }
            
            return rtrim($new_column, ', ');
        }
        
        public function map($map)
        {
            if ( ! $this->map) {
                $this->map = $map;    
            }
            
            return $this;
        }
        
        public function __construct($columnNames = '*', $map = null)
        {
            $this->query .= 'SELECT ';
            $columnNames = $this->modifyColumn($columnNames);
            
            $this->query .= $columnNames;
               
            if ( ! $this->map) {
                $this->map = $map;
            }
            
            if (DB::$table) {
                $this->from(DB::$table);    
            }
            $this->auto_fix['column'] = $columnNames;
            
        }
        
        public static function __callStatic($name, $arguments)
        {
            if (preg_match('/find_(or_)?(\w+)/', $name, $matched)) {
                
                if ( !DB::$table) {
                    throw new \Exception(
                        'not table defined, use DB::setTable(...) to define your table name'
                    );
                }
                
                $thix = new Select();
                $column = $matched[2];
                if (trim($matched[1]) != false) {    
                    $thix->where($arguments, 'OR', '=', $column);
                }
                else {
                    $thix->where($column, $arguments[0]);
                }
                return $thix;
            }
        }
        
        public function __call($name, $arguments)
        {
            if ($name == 'select') {
                if ( ! isset($arguments[0])) {
                    $arguments[0] = '*';
                }
                $this->__construct($arguments[0]);
                return $this;
            }
            elseif ($name == 'fallBack') {
                return $this->fall_back;
            }
        }
        
        public function union($type = null)
        {
            if ($type) {
                $type = strtoupper($type) . ' ';    

            }
            
            $this->buildQuery();
            
            if ($this->built && DB::$auto_fix == true) {
                $this->built = $this->built . ' UNION ' . $type . '';
            }
            else {
                $this->query .= ' UNION ' . $type . '';
            }
            
            unset($this->auto_fix);
            return $this;
        }
        
        public function unionAll()
        {
            $this->union('ALL');
            return $this;
        }
        
        public function innerJoin()
        {
            if ( ! $this->built) {
                $this->buildQuery();
            }
            $this->innerJoin .= $this->built . 'INNER JOIN (';
            return $this;
        }
        
        public function from($tableNames)
        {
            $this->auto_fix['from'] = $tableNames;
            $this->query .= ' FROM ' . $tableNames;
            
            if (preg_match('/FROM (\w+) FROM (\w+)/', $this->query)) {
                throw new \Exception('
                    You can\'t call the method from(...) because you
                    already set a global table with DB::setTable(...)'
                );
            }
            return $this;
        }
        
        public function distinct()
        {
            $this->auto_fix['distinct'] = true;
            $this->query .= ' DISTINCT';
            return $this;
        }
        
        public function where($name, $value = null, $condition = '=', $const = null)
        {
            if (DB::$auto_fix == true) {
                if ( is_string($name) && ! $value) {
                    throw new \Exception(sprintf('
                        No value assigned to %1$s ->where(\'%1$s\', [required_value])',
                        $name
                    ));    
                }
            }
            $this->auto_fix['where'] = Auxi::where(
                $name, $value, $condition, $this, $const
            );
            
            $this->query .= ' WHERE ' . $this->auto_fix['where'];
            return $this;
        }
        
        public function andWhere($name, $value, $condition = '=')
        {
            $this->auto_fix['andwhere'] = Auxi::where($name, $value, $condition, $this);
            $this->query .= ' AND ' . $this->auto_fix['andwhere'];
            return $this;
        }
        
        public function orWhere($name, $value, $condition = '=')
        {
            $this->auto_fix['orwhere'] = Auxi::where($name, $value, $condition, $this);
            $this->query .= ' OR ' . $this->auto_fix['orwhere'];
            return $this;
        }
        
        public function between($match1, $match2)
        {
            $this->auto_fix['between'] = sprintf(' BETWEEN %s AND %s', $match1, $match2);
            $this->query .= $this->auto_fix['between'];
            return $this;
        }
        
        public function order($columnName, $orderType = null)
        {
            if ($columnName == 'rand') {
                $columnName = 'RAND()';
            }
            elseif ($columnName == '`rand`') {
                $columnName = 'rand';
            }
            elseif ($match = Auxi::makeQueryFunc($columnName)) {
                $columnName = $match;
            }
            
            $this->auto_fix['order'] = $columnName . ' ' . $orderType;
            $this->query .= ' ORDER BY ' . $this->auto_fix['order'];
            return $this;
        }
        
        public function group()
        {
            $grouping = null;
            foreach (func_get_args() as $value) {
                
                $grouped = Auxi::makeQueryFunc($value);
                
                if ( ! $grouped) {
                    $grouping .= $value;
                }
                else {
                    $grouping .= $grouped;
                }
                
                $grouping .= ', ';
            }
            
            $grouping = rtrim($grouping, ', ');
            
            $this->auto_fix['group'] = $grouping;
            $this->query .= ' GROUP BY ' . $grouping;
            return $this;
        }
        
        public function have($name, $value = null, $condition = '=', $const = null)
        {
            if (DB::$auto_fix == true) {
                if ( is_string($name) && ! $value) {
                    throw new \Exception(sprintf('
                        No value assigned to %1$s ->group(\'%1$s\', [required_value])',
                        $name
                    ));    
                }
            }

            $this->auto_fix['have'] = Auxi::where(
                $name, $value, $condition, $this, $const
            );
            $this->query .= ' HAVING ' . $this->auto_fix['have'];
            return $this;
        }
        
        public function limit($limit)
        {
            $this->auto_fix['limit'] = $limit;
            $this->query .= ' LIMIT ' . $limit;
            return $this;
        }
        
        public function offset($offset)
        {
            $this->auto_fix['offset'] = $offset;
            $this->query .= ' OFFSET ' . $offset;
            return $this;
        }
        
        private function buildQuery()
        {
            if (DB::$auto_fix == true)
            {
                $new_query = null;
                $new_query = 'SELECT';
                if (isset($this->auto_fix['distinct'])) {
                    $new_query .= ' ' . $this->auto_fix['distinct'];    
                }
                
                if (isset($this->auto_fix['column'])) {
                    $new_query .= ' ' . $this->auto_fix['column'];    
                }
                else {
                    throw new \Exception('No column selected');
                }
                
                if (isset($this->auto_fix['from'])) {
                    $new_query .= ' FROM ' . $this->auto_fix['from'];
                }
                else {
                    throw new \Exception('No table selected');
                }
                
                if (isset($this->auto_fix['where'])) {
                    $new_query .= ' WHERE ' . $this->auto_fix['where'];
                }
                
                if (isset($this->auto_fix['between'])) {
                    $new_query .= $this->auto_fix['between'];
                }
                
                if (isset($this->auto_fix['andwhere'])) {
                    $new_query .= ' AND ' . $this->auto_fix['andwhere'];
                }
                
                if (isset($this->auto_fix['orwhere'])) {
                    $new_query .= ' OR ' . $this->auto_fix['orwhere'];
                }

                if (isset($this->auto_fix['group'])) {
                    $new_query .= ' GROUP BY ' . $this->auto_fix['group'];
                }
                
                if (isset($this->auto_fix['have'])) {
                    $new_query .= ' HAVING ' . $this->auto_fix['have'];
                }
                
                if (isset($this->auto_fix['order'])) {
                    $new_query .= ' ORDER BY ' . $this->auto_fix['order'];
                }
                
                if (isset($this->auto_fix['limit'])) {
                    $new_query .= ' LIMIT ' . $this->auto_fix['limit'];
                }
                
                if (isset($this->auto_fix['offset'])) {
                    $new_query .= ' OFFSET ' . $this->auto_fix['offset'];
                }
                
                $this->built .= $new_query;
            }
            else {
                $this->built = $this->query;    
            }
            
            $this->built = preg_replace('/\s+/', ' ', $this->built);
        }
        
        public function toString($forQuery = false)
        {
            if ( (DB::$auto_fix && ! $this->built) || $this->query ) {
                $this->buildQuery();    
            }
            
            return \Auxiliary\Methods::Stringfy(
                $this->built,
                $this->col_and_val,
                $forQuery
            );
        }
        
        public function commit($from = null)
        { 
            if ( (DB::$auto_fix && ! $this->built) || $this->query ) {
                $this->buildQuery();    
            }
                        
            try {
                $query = DB::$conn->prepare($this->built);
                if ($query->execute($this->col_and_val)) {
                    
                    $result = null;
                    if ($this->map) {
                        if ($this->map === 'object') {
                            $result = $query->fetchAll(\PDO::FETCH_OBJ);
                        }
                        elseif (is_object($this->map)) {
                            $stm = $query->fetchAll(\PDO::FETCH_ASSOC);
                            foreach ($stm as $values) {
                                foreach ($values as $key => $value) {
                                    if (property_exists($this->map, $key)) {
                                        $this->map->{$key}[] = $value;    
                                    }
                                }
                            }
                        }
                        else {
                            $type = null;
                            if (strpos($this->map, ':') !== false) {
                                list($type, $this->map) = explode(':', trim($this->map));
                            }
                            if (strcasecmp('function', $type) == 0) {
                                $type = '\PDO::FETCH_FUNC';    
                            } else {
                                $type = '\PDO::FETCH_CLASS';
                            }
                            $query->fetchAll(constant($type), $this->map);
                        }
                    }
                    else {
                        $result = $query->fetchAll(\PDO::FETCH_ASSOC);
                    }
                    
                    $this->count =  count($result);
                    if ($result) {            
                        if ($from === null) {
                            foreach ($result as $values) {
                                foreach ($values as $key => $value) {
                                    $this->{$key}[] = $value;
                                }
                            }
                            return $result;    
                        }
                        elseif ($from === 'count') {
                            return $this->count;
                        }
                        else {
                            if (isset($result[$from])) {
                                foreach ($result[$from] as $name => $value) {
                                    $this->{$name} = $value;    
                                }
                                return $result[$from];
                            }
                            else {
                                throw new \Exception(
                                    'You are trying to access an unkown offset(' . $from . ')'
                                );
                            }
                        }
                    }
                }
            } catch (\PDOException $e) {  
               \Exception\DBException::init($e);  
            }
        }
        
        public function count()
        {
            return $this->commit('count');
        }
        
    }
    
    class InsertInto
    {
        private $table = null;
        
        private $col_and_val = array();
        
        private $columns = array();
        
        private $built = null;
        
        private $last_was_col = false;
        
        private $select = null;
        
        private $instantiated = null;
        
        private $duplicate = null;
        
        public $last_id = 0;
        
        public function __construct($tableName)
        {
            $this->table = $tableName;
        }
        
        public function __set($name, $value)
        {
            $this->columns[] = $name;
            $this->col_and_val[':i' .$name] = $value;
            return $this;
        }
        
        public function __call($name, $arguments)
        {
            $classNamespace = __NAMESPACE__ . '\\' . $name;
            if (class_exists($classNamespace)) {
                
                if ( ! isset($arguments[1])) {
                    $arguments[1] = null;
                }
                $refrence =  new $classNamespace($arguments[0], $arguments[1]);
                
                $refrence->fall_back = &$this;
                $this->instantiated = $refrence;
                return $refrence;
            }
            else {
                throw new \Exception(
                    'Class \'' . $classNamespace . '\' not found'
                );    
            }
        }
        
        public function values($name, $value = null)
        {
            if ($this->last_was_col) {
                if ( ! is_array($name)) {
                    $name = func_get_args();
                }
                
                foreach ($name as $key => $value) {
                    $this->col_and_val[':i' . $this->columns[$key]] = $value;
                }

                $this->last_was_col = false;
            }
            elseif ( ! $this->col_and_val) {
                if (is_string($name) && $value) {
                    $this->columns[] = $name;
                    $this->col_and_val[':i' . $name] = $value;
                }
                else {    
                    $this->columns = array_keys($name);
                    
                    $data = array_map(function ($name, $value) {                    
                        $this->col_and_val[':i' . $name] = $value;
                    }, array_keys($name), array_values($name));
                }
            }
            return $this;
        }
        
        public function json($jsonObject, $isFile = false)
        {
            if ($isFile) {
                $jsonObject = file_get_contents($jsonObject);    
            }
            $this->values(json_decode($jsonObject, true));
            return $this;
        }
        
        public function column($columnNames)
        {
            $this->last_was_col = true;
            
            if (is_array($columnNames)) {
                $this->columns = $columnNames;
            }
            else {
                $this->columns = array_map('trim', func_get_args());
            }
            return $this;
        }
        
        public function onDuplicate($name, $value)
        {
            if ( ! is_array($name)) {
                
                if (strpos($value, 'val:') !== false) {
                    list($null, $column) = explode('val:', $value);
                    $this->duplicate = '`' . $name . '` = VALUES(' . $column . ')';
                }
                elseif (preg_match('/values\(\w+\)/i', $value, $column)) {
                    $this->duplicate = '`' . $name . '` = ' . $value;
                }
                else {
                    $this->duplicate = '`' . $name . '` = ' . $value;
                }
            }
            else {
                
            }
        }
        
        private function buildQuery()
        {    
            $query = 'INSERT INTO ' . $this->table;
            $query .= ' (`' . implode('`, `', $this->columns) . '`)';
            
            if ($this->instantiated) {
                $new_data = $this->instantiated->toString(true);
                $query .= ' ' . $new_data['query'];
                $this->col_and_val = $new_data['data'];
            }
            else {
                $query .= ' VALUES (:i' . implode(', :i', $this->columns) . ')';
            }
            
            if ($this->duplicate) {
                $query .= ' ON DUPLICATE KEY UPDATE' . $this->duplicate;
            }
            $this->built = preg_replace('/\s+/', ' ', $query);
        }
        
        public function toString($forQuery = false)
        {
            if ( ! $this->built) {
                $this->buildQuery();    
            }
            return \Auxiliary\Methods::Stringfy(
                $this->built,
                $this->col_and_val,
                $forQuery
            );
        }
        
        public function commit($lastID = false)
        {
            if (! $this->built) {
                $this->buildQuery();
            }
            
            try {
                $query = DB::$conn->prepare($this->built);

                if ($query->execute($this->col_and_val)) {
                    
                    $this->last_id = DB::$conn->lastInsertId();
                    if ($lastID) {
                        return $this->last_id;
                    }
                }
            } catch (\PDOException $e) {  
               \Exception\DBException::init($e);  
            }
        }
    }
    
    class Update
    {
        private $table = null;
        
        public $where = null;
        
        private $columns = array();
        
        public $col_and_val = array();
        
        private $built = null;
        
        private $set = null;
        
        
        public function __construct($tableNames)
        {
            $this->table = $tableNames;
        }
        
        public function set($name, $value = null)
        {
            if ( ! is_array($name)) {
                if ($value) {
                    $this->set = '`' . $name . '` = :u'. $name;
                    $this->col_and_val[':u' . $name] = $value;
                }
                else {
                    $this->where = $name;
                }
            }
            else {
                $data = array_map(function ($name, $value) {                    
                    $this->set .= '`' . $name . '` = :u'. $name . ', ';
                    $this->col_and_val[':u' . $name] = $value;
                }, array_keys($name), array_values($name));
                
                $this->set = rtrim($this->set, ', ');
            }
            return $this;
        }
        
        public function __set($name, $value)
        {
            if ($this->set) {
                $this->set .= ', ';
            }
            $this->set .= '`' . $name . '` = :u'. $name;
            
            $this->col_and_val[':u' . $name] = $value;
        }
        
        public function where($name, $value = null, $condition = '=', $const = null)
        {
            $this->where .= Auxi::where(
                $name, $value, $condition, $this, $const
            );
            return $this;
        }
        
        public function andWhere($name, $value, $condition = '=')
        {
            $this->where .= ' AND ' .  Auxi::where(
                $name, $value, $condition, $this
            );
            return $this;
        }
        
        public function orWhere($name, $value, $condition = '=')
        {
            $this->where .= ' OR ' . Auxi::where(
                $name, $value, $condition, $this
            );
            return $this;
        }
        
        public function json($jsonObject, $isFile = false)
        {
            if ($isFile) {
                $jsonObject = file_get_contents($jsonObject);    
            }
            $this->set(json_decode($jsonObject, true));
            return $this;
        }
        
        private function buildQuery()
        {    
            $query = 'UPDATE ' . $this->table;
            $query .= ' SET ' . $this->set;
            
            if ($this->where) {
                $query .= ' WHERE ' . $this->where;
            }
            
            $this->built = preg_replace('/\s+/', ' ', $query);
        }
        
        public function toString($forQuery = false)
        {
            if ( ! $this->built) {
                $this->buildQuery();    
            }
            return \Auxiliary\Methods::Stringfy(
                $this->built,
                $this->col_and_val,
                $forQuery
            );
        }
        
        public function commit($rowCount = false)
        {    
            if (! $this->built) {
                $this->buildQuery();
            }
            
            try {
                $query = DB::$conn->prepare($this->built);
                if ($query->execute($this->col_and_val)) {
                    if ($rowCount) {
                        return $query->rowCount();
                    }
                }
            } catch (\PDOException $e) {  
               \Exception\DBException::init($e);  
            }
            
        }
        
    }
    
    class deleteFrom
    {
        private $table = null;
        
        public $where = null;
        
        private $columns = array();
        
        public $col_and_val = array();
        
        private $built = null;

        
        public function __construct($tableName)
        {
            $this->table = $tableName;
        }
        
        public function where($name, $value = null, $condition = '=', $const = null)
        {
            $this->where .= Auxi::where(
                $name, $value, $condition, $this, $const
            );
            return $this;
        }
        
        public function andWhere($name, $value, $condition = '=')
        {
            $this->where .= ' AND ' .  Auxi::where(
                $name, $value, $condition, $this
            );
            return $this;
        }
        
        public function orWhere($name, $value, $condition = '=')
        {
            $this->where .= ' OR ' . Auxi::where(
                $name, $value, $condition, $this
            );
            return $this;
        }
                
        private function buildQuery()
        {    
            $query = 'DELETE FROM ' . $this->table;
            if ($this->where) {
                $query .= ' WHERE ' . $this->where;
            }
            
            $this->built = preg_replace('/\s+/', ' ', $query);
        }
        
        public function toString($forQuery = false)
        {
            if ( ! $this->built) {
                $this->buildQuery();    
            }
            return \Auxiliary\Methods::Stringfy(
                $this->built,
                $this->col_and_val,
                $forQuery
            );
        }
        
        public function commit($rowCount = false)
        {    
            if (! $this->built) {
                $this->buildQuery();
            }
            
            try {
                $query = DB::$conn->prepare($this->built);
                if ($query->execute($this->col_and_val)) {
                    if ($rowCount) {
                        return $query->rowCount();
                    }
                }
            } catch (\PDOException $e) {  
               \Exception\DBException::init($e);  
            }
            
        }
    }
    
    class Query
    {
        public $col_and_val = null;
        
        private $built = null;
        
        public $called = array();
        
        private $instantiated = null;
        
        public $fall_back = null;
        
        
        private function buildQuery()
        {    
            if ( ! $this->built) {
                $this->buildQuery();    
            }
            if ( ! empty($this->called) && ! $this->instantiated) {
                $this->external = array(
                    'object' => $this,
                    'query'     => $query
                );
            }
            $this->built = preg_replace('/\s+/', ' ', $query);
        }
        
        public function toString($forQuery = false)
        {
            return \Auxiliary\Methods::Stringfy(
                $this->built,
                $this->col_and_val,
                $forQuery
            );
        }
        
        public function __construct($queryString)
        {
            $this->built = $queryString;
        }
        
        public function __call($name, $arg)
        {
            if ($name == 'fallBack') {
                return $this->fall_back;
            }
        }
        
        public function setToken($bind_token = array())
        {
            $this->col_and_val = $bind_token;
            return $this;
        }
        
        public function commit($method = null, $casting = false)
        {
            if (! $this->built) {
                $this->buildQuery();
            }
            
            try {
                $query = DB::$conn->query($this->built);
                
                if ($query->execute($this->col_and_val))
                {
                    if ($method == 'pdo') {
                        return DB::$conn;
                    }
                    elseif ($method == 'stm') {
                        return $query;
                    }
                    elseif ($method) {
                        $argument = null;
                        if (strpos($method, '(') !== false) {
                            list($method, $argument) = explode('(', rtrim($method, ')'));                                        
                        }
                        
                        if (method_exists($query, $method)) {    
                            if ( ! $casting) {
                                return $query->{$method}(@constant($argument));
                            }
                            else {
                                return $query->{$method}($casting);
                            }
                        }
                        elseif (method_exists(DB::$conn, $method)) {
                            if ( ! $casting) {
                                return DB::$conn->{$method}(@constant($argument));
                            }
                            else {
                                return DB::$conn->{$method}($casting);
                            }
                        }
                        else {
                            throw new \Exception(
                                sprintf('Method \'%s\' not Found', $method)
                            );    
                        }
                    }
                }
            } catch (\PDOException $e) {  
               \Exception\DBException::init($e);  
            }
        }
    }
    
    class Find
    {
        public static function __callStatic($name, $arg)
        {
            DB::setTable(null);
            preg_match('/([\w][a-z0-9]+)_(\w+)/', $name, $matched);
            list(, $table, $column) = $matched;
            
            $query = new Select(isset($arg[1]) ? $arg[1] : '*');
            $query->from($table)
                  ->where($column, $arg[0])
                  ->limit(1);
                  
            return $query;
        }
    }
    
}