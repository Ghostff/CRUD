<?php
require 'Dev\Dump.php';


use PDOConnection\DB as DB;

require 'DB.php';
    
DB::Config(['debug' => true]);
DB::init('localhost', 'tester', 'root', '');


$query = new Query\InsertInto('Product');
$query = new Query\InsertInto('Product');
$query->title = 'shoes';
$query->price = 10.99;
$query->name = 'FooBar';
	  
echo $query->toString();
//echo $query->commit(true);
?>

