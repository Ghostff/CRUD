<?php
require 'Dev\Dump.php';


use PDOConnection\DB as DB;

require 'DB.php';
    
DB::Config([
    'debug' => true,
    'forceDB' => false,
    'forcTable' => false
]);
DB::init('localhost', 'tester', 'root', '');


$query = new Query\InsertInto('Shop');
$query->column('fname, lname, price')
	  ->String('SELECT `title`, `price`, `name` FROM Product WHERE `name` = \'Foo\'');
echo $query->toString();



?>

