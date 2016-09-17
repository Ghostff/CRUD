<?php

require 'Test.php';
require '../src/DB.php';

class DBTest extends Test
{

	public function _test_Insert_instances()
	{
		$expected = '
			Query: INSERT INTO Product (`title`, `price`, `name`) VALUES (:ititle, :iprice, :iname)
			Data: {":ititle":"Shoes",":iprice":10.99,":iname":"FooBar"}
		';	
		
		$this->name = 'Method 1';	
		$this->imagine(
			$expected,
			'	$query = new Sql\InsertInto(\'Product\');
				$query->title = \'Shoes\';
				$query->price = 10.99;
				$query->name = \'FooBar\';
				$test = $query->toString();
			'
		);
		
		$this->name = 'Method 2';	
		$this->imagine(
			$expected,
			'	$query = new Sql\InsertInto(\'Product\');
				$test = $query->values(array(
					\'title\' => \'Shoes\',
					\'price\'	=> 10.99,
					\'name\'	=> \'FooBar\'
				))->toString();
			'
		);
		
		
		$this->name = 'Method 3';
		$this->imagine(
			$expected,
			'	$query = new Sql\InsertInto(\'Product\');
				$test = $query->column(\'title\', \'price\', \'name\')
					  ->values(\'Shoes\', 10.99, \'FooBar\')
					  ->toString();
			'
		);
		
		$this->name = 'Method 4';		
		$this->imagine(
			$expected,
			'	$query = new Sql\InsertInto(\'Product\');
				$test = $query->column([\'title\', \'price\', \'name\'])
					  ->values([\'Shoes\', 10.99, \'FooBar\'])
					  ->toString();
			'
		);
		
		
		
		$this->name = 'Method 5';		
		$this->imagine(
			$expected,
			'	$query = new Sql\InsertInto(\'Product\');
				$test = $query->json(\'
					{
						"title": "Shoes",
						"price": 10.99,
						"name": "FooBar"
					}\'
				)->toString();
			'
		);
		
		
		$this->name = 'Method 6';		
		$this->imagine(
			$expected,
			'	$query = new Sql\InsertInto(\'Product\');
				$test = $query->json(\'insert.json\', true)->toString();
			'
		);
		
		
		$this->name = 'Method 7';
		$expected = '
			Query: INSERT INTO Product (`email`) VALUES (:iemail)Data: {":iemail":"Foo@bar.om"}
		';	
		$this->imagine(
			$expected,
			'	$query = new Sql\InsertInto(\'Product\');
				$test = $query->values(\'email\', \'Foo@bar.om\')
    	  				->toString();
			'
		);
		
		
		$this->name = 'Method 7';
		$expected = '
			Query: INSERT INTO Product (`fname`, `lname`, `price`) SELECT COUNT(*) FROM Product WHERE `name` = :wname OR `title` = :wtitle
			Data: {":wname":"Bar",":wtitle":"Shoes"}
		';	
		$this->imagine(
			$expected,
			'	$query = new Sql\InsertInto(\'Product\');
				$test = $query->column(\'fname\', \'lname\', \'price\')
					   ->Select(\'count\')
					   ->from(\'Product\')
					   ->where(\'name\', \'Bar\')
					   ->orWhere(\'title\', \'Shoes\')
					   ->fallBack()
					   ->toString();
			'
		);
		
		
		$this->name = 'Method 8';
		$this->imagine(
			$expected,
			'	$query = new Sql\InsertInto(\'Product\');
				$test = $query->column(\'fname\', \'lname\', \'price\')
					  ->Query(\'SELECT COUNT(*) FROM Product WHERE `name` = :wname OR `title` = :wtitle\')
					  ->setToken([\':wname\' => \'Bar\', \':wtitle\' => \'Shoes\'])
					  ->fallBack()
					  ->toString();
			'
		);
		
		
		

	}
	
	public function _test_Select_instantces()
	{
		
		$this->name = 'Method 1';
		$expected = '
			Query: SELECT `title`, `price`, `name` FROM ProductData: []
		';	
		$this->imagine(
			$expected,
			'	$query = new Sql\Select(\'title, price, name\');
    			$test = $query->from(\'Product\')->toString();
			'
		);
		
		
		$this->name = 'Method 2';
		$this->imagine(
			$expected,
			'	$query = new Sql\Select([\'title\', \'price\', \'name\']);
    			$test = $query->from(\'Product\')->toString();
			'
		);
		
		
		$this->name = 'Method 3';
		$expected = '
			Query: SELECT `title`, `price`, `name` FROM Product WHERE `name` = :wname
			Data: {":wname":"Bar"}
		';	
		$this->imagine(
			$expected,
			'	$query = new Sql\Select(\'title, price, name\');
    			$test = $query->from(\'Product\')
					  ->where(\'name\', \'Bar\')
					  ->toString();
			'
		);
		
		
		$this->name = 'Method 4';
		$expected = '
			Query: SELECT COUNT(*) FROM ProductData: []
		';	
		$this->imagine(
			$expected,
			'	$query = new Sql\Select(\'count\');
				$test = $query->from(\'Product\')->toString();
			'
		);
		
		$this->name = 'Method 5';
		$this->imagine(
			$expected,
			'	$query = new Sql\Select(\'COUNT(*)\');
				$test = $query->from(\'Product\')->toString();
			'
		);
		
		
		$this->name = 'Method 6';
		$expected = '
			Query: SELECT `title`, `price`, COUNT(name) FROM ProductData: []
		';	
		$this->imagine(
			$expected,
			'	$query = new Sql\Select([\'title\', \'price\', \'count:name\']);
				$test = $query->from(\'Product\')->toString();
			'
		);
		
		
		$this->name = 'Method 6';
		$expected = '
			Query: SELECT `title`, `price`, `name` FROM Product WHERE `name` = :wname AND `title` = :wtitle
			Data: {":wname":"Bar",":wtitle":"Shoes"}
		';	
		$this->imagine(
			$expected,
			'	$query = new Sql\Select(\'title, price, name\');
				$test = $query->from(\'Product\')
					  ->where([\'name\' => \'Bar\', \'title\' => \'Shoes\'])
					  ->toString();
			'
		);
		
		
		$this->name = 'Method 7';
		$this->imagine(
			$expected,
			'	$query = new Sql\Select(\'title, price, name\');
				$test = $query->from(\'Product\')
				  ->where(\'name\', \'Bar\')
				  ->andWhere(\'title\', \'Shoes\')
				  ->toString();
			'
		);
		
		
		$this->name = 'Method 8';
		$expected = '
			Query: SELECT `title`, `price`, `name` FROM Product WHERE `name` = :wname OR `title` = :wtitle
			Data: {":wname":"Bar",":wtitle":"Shoes"}
		';
		$this->imagine(
			$expected,
			'	$query = new Sql\Select(\'title, price, name\');
				$test =  $query->from(\'Product\')
						  ->where([\'name\' => \'Bar\', \'title\' => \'Shoes\'], \'OR\')
						  ->toString();
			'
		);
		
		$this->name = 'Method 9';
		$this->imagine(
			$expected,
			'	$query = new Sql\Select(\'title, price, name\');
				$test =  $query->from(\'Product\')
						  ->where(\'name\', \'Bar\')
						  ->orWhere(\'title\', \'Shoes\')
						  ->toString();
			'
		);
		
		
		$this->name = 'Method 10';
		$expected = '
			Query: SELECT `title`, `price`, `name` FROM Product WHERE `name` = :wname AND `price` = :wprice OR `title` = :wtitle ORDER BY id  LIMIT 4
			Data: {":wname":"Bar",":wprice":10.99,":wtitle":"Shoes"}
		';
		$this->imagine(
			$expected,
			'	$query = new Sql\Select(\'title, price, name\');
				$test =  $query->from(\'Product\')
						  ->where(\'name\', \'Bar\')
						  ->andWhere(\'price\', 10.99)
						  ->orWhere(\'title\', \'Shoes\')
						  ->order(\'id\')
						  ->limit(4)
						  ->toString();
			'
		);
		
		
		$this->name = 'Method 11';
		$expected = '
			Query: SELECT `title`, `price`, `name` FROM Product WHERE price BETWEEN 100 AND 220 ORDER BY ABS(key)\s
			Data: []
		';
		$this->imagine(
			$expected,
			'	$query = new Sql\Select(\'title, price, name\');
				$test =  $query->from(\'Product\')
						  ->where(\'price\')
						  ->between(100, 220)
						  ->order(\'abs:key\')
						  ->toString();
			'
		);
		
		
		$this->name = 'Method 12';
		$expected = '
			Query: SELECT `title`, `price`, `name` FROM Product WHERE `id` > :wid AND `group` = :wgroup
			Data: {":wid":10,":wgroup":5}
		';
		$this->imagine(
			$expected,
			'	$query = new Sql\Select(\'title, price, name\');
				$test =  $query->from(\'Product\')
                      ->where([\'id\' => 10, \'group\' => 5], \'AND\', \'>,<\')
                      ->toString();
			'
		);
		
		$this->name = 'Method 13';
		$expected = '
			Query: SELECT `title`, `price`, `name` DISTINCT FROM Product
			Data: []
		';
		$this->imagine(
			$expected,
			'	$query = new Sql\Select(\'title, price, name\');
				$test = $query->distinct()
                      ->from(\'Product\')
                      ->toString();
			'
		);
		
		$this->name = 'Method 14';
		$expected = '
			Query: SELECT `title`, `price`, `name` FROM Product WHERE `id` = :wid 
				UNION SELECT * FROM Shop WHERE `id` = :wwid 
				UNION ALL SELECT * FROM Product WHERE `id` = :wwwid
			Data: {":wid":1,":wwid":1,":wwwid":3}
		';
		$this->imagine(
			$expected,
			'	$query = new Sql\Select(\'title, price, name\');
				$test =  $query->from(\'Product\')
						  ->where(\'id\', 1)
						  ->union()
						  ->select()
						  ->from(\'Shop\')
						  ->where(\'id\', 1)
						  ->unionAll()
						  ->select()
						  ->from(\'Product\')
						  ->where(\'id\', 3)
						  ->toString();
			'
		);
		
		
		$this->name = 'Method 15';
		$expected = '
			Query: SELECT * FROM Product WHERE price BETWEEN 100 AND 220
			Data: []
		';
		$this->imagine(
			$expected,
			'	$query = new Sql\Select();
				$test =  $query->from(\'Product\')
						  ->where(\'price\')
						  ->between(100, 220)
						  ->toString();
			'
		);
		
		
		$this->name = 'Method 15';
		$expected = '
			Query: SELECT table.`name` AS t1, table.`user` AS t2 FROM Product WHERE price BETWEEN 100 AND 220
			Data: []
		';
		$this->imagine(
			$expected,
			'	$query = new Sql\Select(\'table.name as t1, table.user as:t2\');
                $test = $query->from(\'Product\')
                      ->where(\'price\')
                      ->between(100, 220)
                      ->toString();
			'
		);
		
		
		$this->name = 'Method 16';
		$expected = '
			Query: SELECT * FROM Product WHERE `name` = :wname
			Data: {":wname":"foo"}
		';
		$this->imagine(
			$expected,
			'	PDOConnection\DB::setTable(\'Product\');
				$test = Sql\Select::find_name(\'foo\')->toString();
			'
		);
		
		
		$this->name = 'Method 17';
		$expected = '
			Query: SELECT * FROM Product WHERE `name` = :wname OR `name` = :wwname
			Data: {":wname":"foo",":wwname":"Bar"}
		';
		$this->imagine(
			$expected,
			'	PDOConnection\DB::setTable(\'Product\');
				$test = Sql\Select::find_or_name(\'foo\', \'Bar\')->toString();
			'
		);
		
		$expected = '
			Query: SELECT * FROM Product FROM Product WHERE `p_pid` = :wp_pid ORDER BY id asc
			Data: {":wp_pid":10}
		';
		
		$this->name = 'Method 18';
		$this->imagine(
			$expected,
			'	$query = new Sql\Select();
				$test = $query->from(\'Product\')
							->where(\'p_pid\', 10)
							->order(\'id\', \'asc\')
							->toString();
			'
		);
		
		$expected = '
			Query: SELECT * FROM Product FROM Product WHERE `p_pid` = :wp_pid LIMIT 3
			Data: {":wp_pid":10}
		';
		$this->name = 'Method 19';
		$this->imagine(
			$expected,
			'	$query = new Sql\Select();
				$test = $query->from(\'Product\')
							->where(\'p_pid\', 10)
							->limit(3)
							->toString();
			'
		);
		
		$expected = '
			Query: SELECT * FROM Product FROM Product WHERE `p_pid` = :wp_pid ORDER BY id asc LIMIT 3
			Data: {":wp_pid":10}
		';
		$this->name = 'Method 20';
		$this->imagine(
			$expected,
			'	$query = new Sql\Select();
				$test = $query->from(\'Product\')
							->where(\'p_pid\', 10)
							->order(\'id\', \'asc\')
							->limit(3)
							->toString();
			'
		);
		
	}
	
	public function _test_Update_instantces()
	{
				
		$this->name = 'Method 1';
		$expected = '
			Query: UPDATE Product SET `name` = :uname WHERE `title` = :wtitle
			Data: {":uname":"Foo",":wtitle":"Shoes"}
		';
		$this->imagine(
			$expected,
			'	$query = new Sql\Update(\'Product\');
                $test = $query->set(\'name\', \'Foo\')
                      ->where(\'title\', \'Shoes\')
                      ->toString();
			'
		);
		
		$this->name = 'Method 2';
		$expected = '
			Query: UPDATE Product SET `name` = :uname, `price` = :uprice WHERE `title` = :wtitle
			Data: {":uname":"Foo",":uprice":10.88,":wtitle":"Shoes"}
		';
		$this->imagine(
			$expected,
			'	$query = new Sql\Update(\'Product\');
                $test = $query->set(array(
							\'name\' => \'Foo\', 
							\'price\' => 10.88
						))->where(\'title\', \'Shoes\')
						 ->toString();
			'
		);
		
		
		$this->name = 'Method 3';
		$expected = '
			Query: UPDATE Product SET `name` = :uname, `price` = :uprice WHERE `name` = :wname AND `price` = :wprice OR `title` = :wtitle
			Data: {":uname":"Foo",":uprice":10.88,":wname":"Bar",":wprice":10.99,":wtitle":"Shoes"}
		';
		$this->imagine(
			$expected,
			'	$query = new Sql\Update(\'Product\');
                $test = $query->set(array(
							\'name\' => \'Foo\', 
							\'price\' => 10.88
						))->where(\'name\', \'Bar\')
						  ->andWhere(\'price\', 10.99)
						  ->orWhere(\'title\', \'Shoes\')
						  ->toString();
			'
		);
		
		
		$this->name = 'Method 4';
		$expected = '
			Query: UPDATE Product SET `title` = :utitle, `price` = :uprice, `name` = :uname WHERE `title` = :wtitle
			Data: {":wtitle":"Shoes",":utitle":"Boots",":uprice":10.55,":uname":"Foo"}
		';
		$this->imagine(
			$expected,
			'	$query = new Sql\Update(\'Product\');
                $query->where(\'title\', \'Shoes\');
                
                $query->title = \'Boots\';
                $query->price = 10.55;
                $query->name = \'Foo\';
                
                $test = $query->toString();
			'
		);
		
		
		$this->name = 'Method 5';
		$expected = '
			Query: UPDATE Product SET `title` = :utitle, `price` = :uprice, `name` = :uname WHERE `title` = :wtitle
			Data: {":utitle":"Shoes",":uprice":10.99,":uname":"FooBar",":wtitle":"Shoes"}
		';
		$this->imagine(
			$expected,
			'	$query = new Sql\Update(\'Product\');
                $query->title = \'Shoes\';
                $query->price = 10.99;
                $query->name = \'FooBar\';
                
                $test = $query->where(\'title\', \'Shoes\')
                      ->toString();
			'
		);
		
		
		$this->name = 'Method 6';
		$this->imagine(
			$expected,
			'	$query = new Sql\Update(\'Product\');
                $test = $query->json(\'{
							"title": "Shoes",
							"price": 10.99,
							"name": "FooBar"
						}\')->where(\'title\', \'Shoes\')
						   ->toString();
			'
		);
		
		
		$this->name = 'Method 7';
		$this->imagine(
			$expected,
			'	$query = new Sql\Update(\'Product\');
                $test =  $query->json(\'insert.json\', true)
                      		->where(\'title\', \'Shoes\')
                      		->toString();
			'
		);
		
	}
	
	public function _test_Delete_instantces()
	{
		$this->name = 'Method 1';
		$expected = '
			Query: DELETE FROM Product WHERE `title` = :wtitle OR `name` = :wname
			Data: {":wtitle":"Shoes",":wname":"FooBar"}
		';
		$this->imagine(
			$expected,
			'	$query = new Sql\deleteFrom(\'Product\');
                $test = $query->where(\'title\', \'Shoes\')
						  ->orWhere(\'name\', \'FooBar\')
						  ->toString(); 
			'
		);
	}
	
	public function _test_Query_instantces()
	{
		$this->name = 'Method 1';
		$expected = '
			Query: SELECT `title`, `price`, `name` FROM Product WHERE `name` = "Foo"
			Data: null
		';
		$this->imagine(
			$expected,
			'	$query = new Sql\Query(\'SELECT `title`, `price`, `name` FROM Product WHERE `name` = "Foo"\');
                $test = $query->toString();
			'
		);
		
		
		$this->name = 'Method 2';
		$expected = '
			Query: SELECT `title`, `price`, `name` FROM Product WHERE `name` = :wname
			Data: {":wname":"Foo"}
		';
		$this->imagine(
			$expected,
			'	$query = new Sql\Query(\'SELECT `title`, `price`, `name` FROM Product WHERE `name` = :wname\');
                $query->col_and_val = array(\':wname\' => \'Foo\');
                $test = $query->toString();
			'
		);
		
		$this->name = 'Method 3';
		$this->imagine(
			$expected,
			'	$query = new Sql\Query(\'SELECT `title`, `price`, `name` FROM Product WHERE `name` = :wname\');
                $query->setToken(array(\':wname\' => \'Foo\'));
                $test = $query->toString();
			'
		);
	}
	
	public function _test_Find_instantces()
	{
		$this->name = 'Method 1';
		$expected = '
			Query: SELECT * FROM Product WHERE `name` = :wname LIMIT 1
			Data: {":wname":"Foo"}
		';
		$this->imagine(
			$expected,
			'
                $test = Sql\Find::Product_name(\'Foo\')->toString();
			'
		);
		
		$this->name = 'Method 2';
		$expected = '
			Query: SELECT COUNT(*) FROM Product WHERE `name` = :wname LIMIT 1
			Data: {":wname":"Foo"}
		';
		$this->imagine(
			$expected,
			'
               $test =  Sql\Find::Product_name(\'Foo\', \'count\')->toString();
			'
		);
		
		$this->name = 'Method 3';
		$expected = '
			Query: SELECT COUNT(name) FROM Product WHERE `name` = :wname LIMIT 1
			Data: {":wname":"Foo"}
		';
		$this->imagine(
			$expected,
			'
                $test = Sql\Find::Product_name(\'Foo\', \'count:name\')->toString();
			'
		);
	}
}

new DBTest;