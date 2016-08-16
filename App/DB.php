<?php

class DB
{
	private static $conn = null;
	
	private static $debug = false;
	
	private static $force_DB = false;
	
	private static $force_table = false;
	
	private $datas = array();
	
	private $table = null;
	
	private $selected = null;
	
	private $result = null;
	
	private $where = null;
	
	private $limit = null;
	
	private $order = null;
	
	
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
		if (self::$force_table) {
			//self::$conn->query('CREATE TABLE IF NOT EXISTS ' . $tableName);
		}
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
		$query->execute($this->datas);
		return;
	}
	
	public function update($data)
	{
		$sort = function ($row) {
			return $row . ' = :' . $row;
		};
		
		$set = implode(', ', 
			array_map($sort, array_keys($this->datas))
		);

		$where = implode(', ', 
			array_map($sort, array_keys($data))
		);
		
		$array = array_map(function($value) use (&$data) { 
			$data[':' . $value] = $data[$value];
			unset($data[$value]);
		}, array_keys($data));

		$query = self::$conn->prepare('
			UPDATE ' . $this->table
			. ' SET ' . $set . ' WHERE '. $where
		);
		
		$exec = array_merge($this->datas, $data);
		$query->execute($exec);
		return;

	}
	
	public function select($select = null)
	{
		if (! $select) {
			$this->selected = '*';
		}
		else {
			$this->selected = $select;	
		}
		
		$stmt = self::$conn->prepare(
			'SELECT ' . $this->selected
			. ' FROM ' . $this->table 
			. $this->where
			. $this->order
			. $this->limit
		);
		$stmt->execute($this->datas);
		
		$this->result = $stmt->fetchAll(
			PDO::FETCH_CLASS
		);
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
		$this->datas = $query;
		return $this;
	}
	
	public function __set($name, $value)
	{
		$this->datas[$name] = $value;
	}
	
	public function __get($name)
	{
		if (strcasecmp($name, 'all') == 0) {
			return $this->result;	
		}
		if (count($this->result == 1)) {
			if (property_exists($this->result[0], $name)) {
				return $this->result[0]->{$name};
			} else {
				throw new Exception($name . ' property does not exists');	
			}	
		}
	}
}
