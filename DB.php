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
*  Inserting new data into database
*/
namespace Query {
    
    use PDOConnection\DB as DB;
    
    class Insert
    {
        //hold working or active table
        private $table = null;
        
        //holds columns
        private $columns = array();
        
        //hold binds for undefined type
        private $hold = array();
        
        //holds data
        private $data = array();
        
        //hold suplied column values
        private $arguments = null;
        
        //hold select
        private $select = null;
        
        //hold dynmaic property values
        private $set = array();
        
        //holds custom query
        private $query = null;
        
		//last ID flag
        private $lastID = false;
        
        /*
        * sets active or working table
        * return null
        *
        * @param table name
        */
        public function __construct($tableName = null)
        {
            $this->table = $tableName;
            register_shutdown_function(array($this, 'lastID'));
        }
        
        public function __set($name, $argument)
        {
            $this->set[$name] = $argument;
        }
        
        public function into($columns)
        {
            $name = $value = null;
            foreach (explode(',', $columns) as $key => $cols) {
                $cols = trim($cols);
                $this->hold[$cols] = ':' . $cols;
                $this->hold[$key] = ':' . $cols;
                $name .= '`' . $cols . '`, ';
                $value .= ':' . $cols . ', ';
            }
            $name = rtrim($name, ', ');
            $value = rtrim($value, ', ');
            
            $this->columns = array(
                    'name'         => $name,
                    'value'        => $value
            );
            return $this;
        }
        
        public function values($values)
        {
            
            if (! is_array($values)) {
                if (count($this->hold) > 1) {
                    throw new \Exception(
                        'Columns Does not match Specified values'
                    );    
                } else {
                    
                    //for single value eg[->into('fname')->value('chrys')]
                    $this->data[$this->hold[0]] = $values;    
                }
            } else {
                
                //for multi values eg[->into('fname,lname'..)->value(array('chrys','ugwu'..))]    
                foreach ($values as $key => $val) {

                    if (isset($this->hold[$key])) {
                        $this->data[$this->hold[$key]] = $val;
                    } else {
                        throw new \Exception(
                            'Array Argument ' . $key . ' Has no Name'
                        );    
                    }    
                }
            }
        }
        
        public function query($queryString)
        {
            $this->query = $queryString;
        }
        
        public function lastID()
        {
            $last_id = null;
            if (! $this->lastID) {
                if (! empty($this->set)) {
                    $into = implode(',', array_keys($this->set));    
                    $values = array_values($this->set);
                    
                    $this->into($into);
                    $this->values($values);
                }
                
                if (! $this->query) {
                    $query = 'INSERT INTO `' . $this->table;
                    $query .= '` (' . $this->columns['name'] . ')';
					
                    if ($this->select) {
                        $query .= $this->select;    
                    } else {
                        $query .= ' VALUES (' . $this->columns['value'] . ')';
                    }
					
                } else {
                    $query = $this->query;
                }
                try {
					
                    $query = DB::$conn->prepare($query);
                    if ($query->execute($this->data)) {
                        $last_id =  DB::$conn->lastInsertId();
                    } else {
                        return false;
                    }
					
                } catch (\PDOException $e) {  
                   \Exception\DBException::init($e);  
                }
            }
            $this->lastID = true;
            return $last_id;    
        }
    }
}


namespace {

}