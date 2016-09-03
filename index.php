<?php

use PDOConnection\DB as DB;

require 'DB.php';
    
DB::Config([
    'debug' => true,
    'forceDB' => false,
    'forcTable' => false
]);
DB::init('localhost', 'tester', 'root', '');



$query = new Sql\InsertInto('Shop');
$query->column('fname, lname, price')
	  ->Query('SELECT `title`, `price`, `name` FROM Product WHERE `name` = \'Foo\'');
echo $query->toString();

?>

