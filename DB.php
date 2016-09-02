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
                    self::$debug = $attribute['debug'];    
                }
                if (array_key_exists('forceDB', $attribute)) {
                    self::$force_DB = $attribute['forceDB'];    
                }
                if (array_key_exists('forceTbl', $attribute)) {
                    self::$force_table = $attribute['forceTbl'];    
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
            if (self::$debug) {
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            if (self::$force_DB) {
                $pdo->query('CREATE DATABASE IF NOT EXISTS ' . $DBName);
            }
            $pdo->query('USE ' . $DBName);
            self::$conn = $pdo;
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
					'query'	=> $query,
					'data'	=> $data
				);
			}
			else {
				$string = '<code>Query: '. $query . '</code><br />';
				$string .= '<code>Data: '. json_encode($data) . '</code>';
			}
			return $string;
		}
		
		public static function where($name, $value = null, $class)
		{
			if ( ! is_array($name)) {
				if ($value) {
					$class->where = '`' . $name . '` = :w'. $name;
					$class->col_and_val[':w' . $name] = $value;
				}
				else {
					$class->where = $name;
				}
			}
			else {
				
				if ( ! $value) {
					$opperator = 'AND';	
				} else {
					$opperator = strtoupper($value);
				}
				
				if ($class->where) {
					$class->where .= ' ' . $opperator . ' ';
				}
				
				$data = array_map(function ($name, $value) use ($opperator, $class) {					
					$class->where .= '`' . $name . '` = :w'. $name . ' ' . $opperator . ' ';
					$class->col_and_val[':w' . $name] = $value;
				}, array_keys($name), array_values($name));
				$class->where = rtrim($class->where, 'AND ');
				$class->where = rtrim($class->where, 'OR ');
			}
		}
		
		public static function andWhere($name, $value, $class)
		{
			if ($class->where) {
				static::where(array($name => $value), null, $class);
			}
			else {
				throw new \Exception(
					'Update::where method must be called first'
				);
			}
		}
		
		public static function orWhere($name, $value, $class)
		{
			if ($class->where) {
				static::where(array($name => $value), 'OR', $class);
			}
			else {
				throw new \Exception(
					'Update::where method must be called first'
				);
			}
		}
    }
}

/*
*  PDO quries
*/
namespace Query {
    
    use PDOConnection\DB as DB;	
	
	
	class Select
	{
		private $table = null;
		
		public $where = null;
		
		private $order = null;
		
		private $limit = null;
		
		private $columns = array();
		
		public $col_and_val = array();
		
		private $map = null;
		
		private $built = null;
		
		public $called = array();
		
		private $instantiated = null;
		
		public function __construct($columnNames, $map = null)
		{
			if (is_array($columnNames)) {
				$this->columns = $columnNames;
			}
			else {
				$this->columns = str_replace(' ', '', 
					trim($columnNames, ',')
				);
				$this->columns = explode(',', $this->columns);
			}
			$this->map = $map;
		}
		
		public function from($tableNames)
		{
			$this->table = $tableNames;
			return $this;
		}
		
		public function where($name, $value = null)
		{
			\Auxiliary\Methods::where($name, $value, $this);
			return $this;
		}
		
		public function andWhere($name, $value)
		{
			\Auxiliary\Methods::andWhere($name, $value, $this);
			return $this;
		}
		
		public function orWhere($name, $value)
		{
			\Auxiliary\Methods::orWhere($name, $value, $this);
			return $this;
		}
		
		public function order($columnName, $orderType = 'ASC')
		{
			$this->order = $columnName . ' ' . $orderType;
			return $this;
		}
		
		public function limit($limit)
		{
			$this->limit = $limit;
			return $this;
		}
		
		private function buildQuery()
		{	
			$new_column = null;
			foreach ($this->columns as $column) {
				
				$pattern = '/^count\:(.*)|count\((.*)\)|count$/i';
				if (preg_match($pattern, $column, $matched)) {
					
					$count = null;
					if (isset($matched[1]) && trim($matched[1]) != false) {
						$count = trim($matched[1]);
					}
					elseif (isset($matched[2]) && trim($matched[2]) != false) {
						$count = trim($matched[2]);
					}
					else {
						$count = '*';
					}
					$new_column .= sprintf('COUNT(%s)', $count);
				}
				else {
					$new_column .= sprintf('`%s`, ', $column);	
				}
			}
			$new_column = rtrim($new_column, ', ');
			$query = 'SELECT ' . $new_column;
			$query .= ' FROM ' . $this->table;
			
			if ($this->where) {
				$query .= ' WHERE ' . $this->where;
			}
			if ($this->order) {
				$query .= ' ORDER BY ' . $this->order;
			}
			if ($this->limit) {
				$query .= ' LIMIT ' . $this->limit;
			}
			$this->built = $query;
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
		
		public function commit($from = null)
		{
			if (! $this->built) {
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
					
					if ($result) {			
						if ($from === null) {
							foreach ($result as $values) {
								foreach ($values as $key => $value) {
									$this->{$key}[] = $value;
								}
							}
							return $result;	
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
			$classNamespace = '\Query\\' . $name;
			if (class_exists($classNamespace)) {
				
				if ( ! isset($arguments[1])) {
					$arguments[1] = null;
				}
				$refrence =  new $classNamespace($arguments[0], $arguments[1]);
				$refrence->called = array('init');
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
				$this->columns = str_replace(' ', '', $columnNames);
				$this->columns = explode(',', $this->columns);
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
				$query .= ' (`' . implode('`, `', $this->columns) . '`)';
			}
			
			if ($this->duplicate) {
				$query .= ' ON DUPLICATE KEY UPDATE' . $this->duplicate;
			}
			$this->built = $query;
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
					if ($lastID) {
						return DB::$conn->lastInsertId();
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
		
		public function where($name, $value = null)
		{
			\Auxiliary\Methods::where($name, $value, $this);
			return $this;
		}
		
		public function andWhere($name, $value)
		{
			\Auxiliary\Methods::andWhere($name, $value, $this);
			return $this;
		}
		
		public function orWhere($name, $value)
		{
			\Auxiliary\Methods::orWhere($name, $value, $this);
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
			
			$this->built = $query;
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
		
		public function where($name, $value = null)
		{
			\Auxiliary\Methods::where($name, $value, $this);
			return $this;
		}
		
		public function andWhere($name, $value)
		{
			\Auxiliary\Methods::andWhere($name, $value, $this);
			return $this;
		}
		
		public function orWhere($name, $value)
		{
			\Auxiliary\Methods::orWhere($name, $value, $this);
			return $this;
		}
				
		private function buildQuery()
		{	
			$query = 'DELETE FROM ' . $this->table;
			if ($this->where) {
				$query .= ' WHERE ' . $this->where;
			}
			
			$this->built = $query;
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
	
	class String
	{
		public $col_and_val = null;
		private $built = null;
		public $called = array();
		private $instantiated = null;
		
		
		public function toString($forQuery = false)
		{
			return \Auxiliary\Methods::Stringfy(
				$this->built,
				$this->col_and_val,
				$forQuery
			);
		}
		
		private function buildQuery()
		{	
			if ( ! $this->built) {
				$this->buildQuery();	
			}
			if ( ! empty($this->called) && ! $this->instantiated) {
				$this->external = array(
					'object' => $this,
					'query'	 => $query
				);
			}
			$this->built = $query;
		}
		
		public function __construct($queryString)
		{
			$this->built = $queryString;
		}
		
		public function commit($method = null, $casting = false)
		{
			if (! $this->built) {
				$this->buildQuery();
			}
			
			try {
				$query = DB::$conn->prepare($this->built);

				if ($query->execute($this->col_and_val)) {
					if ($method) {
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
							if ( ! $castiing) {
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
	
}