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
            die('<code>
                Error: ' . $exception->getMessage()
                . '<br />At ' .  $exception->getFile()
                . '<br />Line: ' . $new_line
                . '<p /><div style="border:1px solid #ddd;width:60%">' . $error . '</div>'
                . str_replace('#', '<br />#', $exception->getTraceAsString())
                . '</code>'
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
        * creates column name and parameters label instances
        * return active class object
        *
        * @param string comma(,) seperated column name
        * @param active class
        */
        public static function makeCols(&$object)
        {
            $class = ltrim(get_class($object), 'Query\\');
            if ($class == 'Insert') {
                $data = $object->data;
                $column = $binds = null;
                foreach ($data as $name => $value) {
                    
                    $binds .= ':' . $name . ', ';
                    $column .= '`' . $name . '`, ';
                    $object->data[':' . $name] = $value;
                    unset($object->data[$name]);
                }
                return array(
                    'name' => rtrim($column, ', '),
                    'binds' => rtrim($binds, ', ')
                );
            }
            elseif ($class == 'Update') {
                $data = $object->data;
                $column = null;
                foreach ($data as $name => $value) {
                    $column .= '`' . $name . '` = :' . $name . ', ';
                    $object->data[':' . $name] = $value;
                    unset($object->data[$name]);
                }
                return rtrim($column, ', ');
            }
        }
        
        /*
        * assign as value to a parameters label
        * return null
        *
        * @param array of column label values
        * @param active class
        */
        public static function makeColsVals($where, &$object)
        {
            $binds = null;
            foreach ($where as $name => $values) {
                $binds .= '`' . $name . '` = :W' . $name . ' ' . $object->statment .' ';
                $object->data[':W' . $name] = $values;
            }
            return rtrim($binds, $object->statment .' ');
        }
        
        /*
        * renderes query as string
        * return string or rendered query
        *
        * @param pdo query
        * @param active class
        */
        public static function Stringfy($query, $Object)
        {
            $string = '<code>Query: '. $query . '</code><br />';
            $string .= '<code>Data: '. json_encode($Object->data) . '</code>';
            return $string;
        }
    }
}

/*
*  PDO quries
*/
namespace Query {
    
    use PDOConnection\DB as DB;    
    
    class Insert
    {
        //hold column name and values
        public $data = null;
        
        private $set = null;
        
        private $select = null;
        
        private $table = null;
        
        private $built = null;
        
        public function __construct($tableNames)
        {
            $this->table = $tableNames;
        }
        
        public function __set($name, $value)
        {
            $this->data[$name] = $value;
        }
        
        private function buildQuery()
        {
            $column = \Auxiliary\Methods::makeCols($this);
            
            $query = 'INSERT INTO `' . $this->table;
            $query .= '` (' . $column['name'] . ')';
            
            if ($this->select) {
                $query .= $this->select;    
            } else {
                $query .= ' VALUES (' . $column['binds'] . ')';
            }
            $this->built = $query;
        }

        public function into($columnNames)
        {
            $this->set = array_map('trim', explode(',', $columnNames));
            return $this;
        }
        
        public function value($columnvalues)
        {
            if (is_array($columnvalues)){
                foreach ($columnvalues as $key => $value) {
                    $this->data[$this->set[$key]] = $value;
                }
            }
            else {
                $this->data[$this->set[0]] = $columnvalues;
            }
            return $this;
        }
        
        public function query($queryString)
        {
            $this->built = $queryString;
            return $this;
        }
        
        public function _toString()
        {
            if (! $this->built) {
                $this->buildQuery();
            }
            return \Auxiliary\Methods::Stringfy($this->built, $this);
        }
        
        public function end($lastID = false)
        {    
            if (! $this->built) {
                $this->buildQuery();
            }
            
            try {
                $query = DB::$conn->prepare($this->built);
                if ($query->execute($this->data)) {
                    if ($lastID) {
                        return DB::$conn->lastInsertId();
                    }
                }
            } catch (\PDOException $e) {  
               \Exception\DBException::init($e);  
            }
            
        }
    }
    
    class Select
    {
        //hold column name and values
        public $data = null;
        
        private $set = null;
        
        private $where = null;
        
        private $table = null;
        
        private $built = null;
        
        public $statment = 'AND';
        
        private $map = null;
        
        private $from = 0;
        
        public function __construct($tableNames, $map = null)
        {
            $this->table = $tableNames;
            $this->map = $map;
        }
        
        private function buildQuery()
        {
            $column = \Auxiliary\Methods::makeCols($this);
            
            $query = 'SELECT ' . $this->set;
            $query .= ' FROM `' . $this->table . '`';
            
            if ($this->where)  {
                
                $where = \Auxiliary\Methods::makeColsVals($this->where, $this);
                $query .= ' WHERE (' . $where . ')';
            }
            $this->built = $query;
        }
        
        public function from($columnNames)
        {
            if ($columnNames != '*') {
                $column = null;
                foreach (explode(',', $columnNames) as $value) {
                    $column .= '`' . $value . '`, ';
                }
                $this->set = rtrim($column, ', ');
            }
            else {
                $this->set = '*';
            }
            return $this;
        }
        
        public function query($queryString)
        {
            $this->built = $queryString;
            return $this;
        }
        
        public function where($name, $value = null)
        {
            if (! is_array($name) && $value) {
                $this->where[$name] = $value;    
            }
            else {
                if (is_array($name)) {
                    if (strcasecmp('or', $value) == 0) {
                        $this->statment = 'OR';
                    }
                    $this->where = $name;
                }
            }
            return $this;
        }
        
        public function _toString()
        {
            if (! $this->built) {
                $this->buildQuery();
            }
            return \Auxiliary\Methods::Stringfy($this->built, $this);
        }
        
        public function end($from = null)
        {    
            if (! $this->built) {
                $this->buildQuery();
            }
            
            try {
                $query = DB::$conn->prepare($this->built);
                
                if ($query->execute($this->data)) {
                    
                    if ($this->map) {
                        
                        $type = null;
                        if (strpos($this->map, ':') !== false) {
                            list($type, $this->map) = explode(':', $this->map);    
                        }
                        if (strcasecmp('function', $type) == 0) {
                            $type = '\PDO::FETCH_FUNC';    
                        } else {
                            $type = '\PDO::FETCH_CLASS';
                        }
                        $query->fetchAll(constant($type), $this->map);
                    }
            		else {
						$result = $query->fetchAll(\PDO::FETCH_ASSOC);
					}
					if ($from === null) {
						return $result;	
					}
					else {
						if (isset($result[$from])) {
							foreach ($result[$from] as $name => $value) {
								$this->{$name} = $value;	
							}
						}
						else {
							throw new \Exception(
								'You are trying to access an unkown offset(' . $from . ')'
							);
						}
					}
				}
			} catch (\PDOException $e) {  
			   \Exception\DBException::init($e);  
			}
			
		}
		
	}
	
	class Update
	{
		//hold column name and values
		public $data = null;
		
		private $set = null;
		
		private $where = null;
		
        private $table = null;
		
		private $built = null;
		
		public $statment = 'AND';
		
		public function __construct($tableNames)
		{
			$this->table = $tableNames;
		}
		
		public function __set($name, $value)
		{
			$this->data[$name] = $value;
		}
		
		private function buildQuery()
		{
			$column = \Auxiliary\Methods::makeCols($this);
			
			$query = 'UPDATE `' . $this->table;
			$query .= '` SET ' . $column . '';
			
			if ($this->where)  {
				
				$where = \Auxiliary\Methods::makeColsVals($this->where, $this);
				$query .= ' WHERE (' . $where . ')';
			}
			$this->built = $query;
		}
		
		public function set($columnNames)
		{
			$this->set = array_map('trim', explode(',', $columnNames));
			return $this;
		}
		
		public function to($columnvalues)
		{
			if (is_array($columnvalues)){
				foreach ($columnvalues as $key => $value) {
					$this->data[$this->set[$key]] = $value;
				}
			}
			else {
				$this->data[$this->set[0]] = $columnvalues;
			}
			return $this;
		}
		
		public function query($queryString)
		{
			$this->built = $queryString;
			return $this;
		}
		
		public function where($name, $value = null)
		{
			if (! is_array($name) && $value) {
				$this->where[$name] = $value;	
			}
			else {
				if (is_array($name)) {
					if (strcasecmp('or', $value) == 0) {
						$this->statment = 'OR';
					}
					$this->where = $name;
				}
			}
			return $this;
		}
		
		public function _toString()
		{
			if (! $this->built) {
				$this->buildQuery();
			}
			return \Auxiliary\Methods::Stringfy($this->built, $this);
		}
		
		public function end($rowCount = false)
		{	
			if (! $this->built) {
				$this->buildQuery();
			}
			
			try {
				$query = DB::$conn->prepare($this->built);
				if ($query->execute($this->data)) {
					if ($rowCount) {
						return $query->rowCount();
					}
				}
			} catch (\PDOException $e) {  
			   \Exception\DBException::init($e);  
			}
			
		}
		
	}
	
	class Delete
	{
		//hold column name and values
		public $data = null;
		
		private $set = null;
		
		private $where = null;
		
        private $table = null;
		
		private $built = null;
		
		public $statment = 'AND';
		
		public function __construct($tableNames)
		{
			$this->table = $tableNames;
		}
		
		private function buildQuery()
		{
			$query = 'DELETE FROM `' . $this->table;
			if ($this->where)  {
				
				$where = \Auxiliary\Methods::makeColsVals($this->where, $this);
				$query .= '` WHERE (' . $where . ')';
			}
			$this->built = $query;
		}
		
		public function query($queryString)
		{
			$this->built = $queryString;
			return $this;
		}
		
		public function where($name, $value = null)
		{
			if (! is_array($name) && $value) {
				$this->where[$name] = $value;	
			}
			else {
				if (is_array($name)) {
					if (strcasecmp('or', $value) == 0) {
						$this->statment = 'OR';
					}
					$this->where = $name;
				}
			}
			return $this;
		}
		
		public function _toString()
		{
			if (! $this->built) {
				$this->buildQuery();
			}
			return \Auxiliary\Methods::Stringfy($this->built, $this);
		}
		
		public function end($rowCount = false)
		{	
			if (! $this->built) {
				$this->buildQuery();
			}
			
			try {
				$query = DB::$conn->prepare($this->built);
				if ($query->execute($this->data)) {
					if ($rowCount) {
						return $query->rowCount();
					}
				}
			} catch (\PDOException $e) {  
			   \Exception\DBException::init($e);  
			}
			
		}
	}
	
	
}


namespace {
}