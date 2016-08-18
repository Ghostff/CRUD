<?php

class DB
{	
	private $table = null;
	
	private $where = null;
	
	private $limit = null;
	
	private $order = null;
	
	private $result = null;
	
	private $datas = array();
	
	private $selected = null;
	
	private $were_data = null;
	
	private $primary_key = null;
	
	private static $conn = null;
	
	private $new_table = array();
	
	private static $debug = false;
	
	private $forein_keys = array();
	
	private $engine_name = 'InnoDB';
	
	private static $force_DB = false;
	
	private static $force_table = false;
	
	
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
	
	public static function Config($attribute)
	{
		if (is_array($attribute)) {
			if (array_key_exists('debug', $attribute)) {
				self::$debug = $attribute['debug'];	
			}
			if (array_key_exists('forceDB', $attribute)) {
				self::$force_DB = $attribute['forceDB'];	
			}
			if (array_key_exists('forceRow', $attribute)) {
				self::$force_table = $attribute['forceRow'];	
			}
		}
	}
	
	public function __construct($tableName)
	{
		$this->table = $tableName;
	}
	
	public function create()
	{
		$create = 'CREATE TABLE IF NOT EXISTS `'
			. $this->table . '` (';
			
		foreach ($this->new_table as $keys => $value) {
			$create .= '`' . $value['name'] . '` '
				. strtoupper($value['type']);
				
			if ($value['length'] != null) {
				$create .= '(' . $value['length'] . ')';
			}
			if ($value['null'] == true) {
				$create .= ' NULL';
			} else {
				$create .= ' NOT NULL';
			}
			if ($value['AI'] == true) {
				$create .= ' AUTO_INCREMENT';
			}
			$create .= ',';
		}
		if ($this->primary_key) {
			$create .= ' PRIMARY KEY (' . $this->primary_key . ')';
		}
		if ($this->forein_keys) {
			foreach ($this->forein_keys as $names) {
				$create .=  ' FOREIGN KEY (' . $names . '),';
			}
			$create = rtrim($create, ',');
		}
		$create .= ') ENGINE=' . $this->engine_name . ';';
		
		var_dump($create);
		if (self::$conn->exec($create)) {
			return true;
		} else {
			return false;
		}
	}
	
	public function engine($engineName)
	{
		$this->engine_name = $engineName;
		return $this;
	}
	
	public function forein()
	{
		$last = array_keys($this->new_table);
		$last = end($last);
		
		$this->forein_keys[] = $this->new_table[$last]['name'];
		
		return $this;
	}
	
	public function primary()
	{
		$last = array_keys($this->new_table);
		$last = end($last);
		
		$this->primary_key = $this->new_table[$last]['name'];
		
		return $this;
	}
	
	public function save()
	{
		$rows = rtrim(
			implode(', ', array_keys($this->datas)),
			', '
		);
		$mod = ':' . str_replace(', ', ', :', $rows);
		
		$query = self::$conn->prepare(
			'INSERT INTO ' . $this->table . '
				('. $rows . ')
			 VALUES 
				(' . $mod . ')');
		if ($query->execute($this->datas)) {
			return $query->rowCount();
		} else {
			return false;
		}
	}
	
	public function update()
	{
		$set = implode(', ', 
			array_map($sort = function ($row) {
				return $row . ' = :' . $row;
			}, array_keys($this->datas))
		);
		
		$data =& $this->were_data;
		$array = array_map(function($value) use (&$data) { 
			$data[':' . $value] = $data[$value];
			unset($data[$value]);
		}, array_keys($this->were_data));
		
		$this->datas = array_merge($this->datas, $this->were_data);
		$query = self::$conn->prepare('
			UPDATE ' . $this->table
			. ' SET ' . $set . $this->where
		);
		
		if ($query->execute($this->datas)){
			return $query->rowCount();
		} else {
			return false;
		}

	}
	
	public function select($select = '*')
	{
		$this->selected = $select;
		$stmt = self::$conn->prepare(
			'SELECT ' . $this->selected
			. ' FROM ' . $this->table 
			. $this->where
			. $this->order
			. $this->limit
		);
		if ($stmt->execute($this->datas)) {
			
			$this->result = $stmt->fetchAll(
				PDO::FETCH_CLASS
			);
			
			return $stmt->rowCount();
		} else {
			return false;
		}
	}
	
	public function limit($limit)
	{
		$this->limit = ' LIMIT ' . $limit;
		return $this;
	}
	
	public function order($order)
	{
		$this->order = ' ORDER ' . $order;
		return $this;
	}
	
	public function where($query)
	{
		if (is_array($query)) {
			$where = implode(', ', 
				array_map(function ($row) {
					return $row . ' = :' . $row;
				}, array_keys($query))
			);
			$this->where = ' WHERE '. $where;
		}
		$this->were_data = $query;
		return $this;
	}
	
	public function delete()
	{
		$query = self::$conn->prepare('
			DELETE FROM ' . $this->table . $this->where
		);
		if ($query->execute($this->were_data)) {
			return $query->rowCount();
		} else {
			return false;
		}
	}
	
	public function __set($name, $value)
	{
		$this->datas[$name] = $value;
	}
	
	public function from($resultKey)
	{
		if (isset($this->result[$resultKey])) {
			$this->datas = $this->result[$resultKey];
			return $this;
		}
		else {
			throw new Exception('invalid property set '. $resultKey);
		}
	}
	
	public function __get($name)
	{
		if (strcasecmp($name, 'all') == 0) {
			return $this->result;	
		}
		
		$result = ($this->datas) ?: $this->result[0];
		if (property_exists($result, $name)) {
			return $result->{$name};
		} else {
			throw new Exception($name . ' property does not exists');	
		}	
	}
	
	public function __call($name, $arguments)
	{
		if (! isset($arguments[0])) {
			throw new Exception('Column must have a name');	
		}
		$this->new_table[] = array (
			'type' 		=> $name,
			'name' 		=> $arguments[0],
			'length'	=> @ $arguments[1],
			'null'		=> @ $arguments[2],
			'AI'		=> @ $arguments[3],
			'default'	=> @ $arguments[4],
			'comment'	=> @ $arguments[5],
			'collation'	=> @ $arguments[6],
			'virtuality'=> @ $arguments[7]
		);
		return $this;
	}
}
