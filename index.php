<?php

use PDOConnection\DB as DB;

require 'DB.php';
    
DB::Config([
    'debug' => true,
    'forceDB' => false,
    'forcTable' => false
]);
//DB::init('localhost', 'tester', 'root', '');

$query = new Sql\Select('count:name');
$query->from('Product')
	  ->where('price')
	  ->between(100, 220)
	  ->order('abs:key');
	  
echo( $query->toString());



?>

