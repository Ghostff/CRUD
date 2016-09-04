<?php

use PDOConnection\DB as DB;

require 'DB.php';
    
DB::Config([
    'debug' => true,
    'forceDB' => false,
    'forcTable' => false
]);
DB::init('localhost', 'tester', 'root', '');


$query = new Sql\Select('name');
$query->distinct = true;
$query->from('Product')
	  ->where(['id' => 10, 'group' => 5], 'AND', '>,<');
echo( $query->toString());


?>

