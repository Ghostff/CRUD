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
	  ->where('id', 1)
	  ->union()
	  ->select()
	  ->from('Shop')
	  ->where('id', 1);
		    
print_r( $query->toString(true));	  
echo( $query->commit());



?>

