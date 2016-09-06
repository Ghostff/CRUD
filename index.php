<?php

use PDOConnection\DB as DB;

require 'DB.php';
    
DB::Config([
    'debug' => true,
    'forceDB' => false,
    'forcTable' => false
]);
DB::init('localhost', 'tester', 'root', '');

$query = new Sql\Select();
$query->from('Product')
	  ->where('price')
	  ->between(100, 220)
	  ->commit();
	  
echo( $query->toString());



?>

