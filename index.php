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
	  ->where('id', 1)
	  ->unionAll()
	  ->select('*')
	  ->from('Product')
	  ->where('id', 3);
		    
var_dump( $query->commit()); 



?>

