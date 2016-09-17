<?php

use PDOConnection\DB as DB;

require 'src/DB.php';
    
DB::Config([
    'debug' 	=> true,
    'forceDB' 	=> false,
    'forcTable' => false,
	'autoFix'	=> true
]);

DB::init('localhost', 'tester', 'root', '');


//DB::setTable('Product');


$query = new Sql\Select('any_value:address');
echo $query->from('Product')
    	   ->where('price',22)
    	   ->group('id')
		   ->have('c = 1')
           ->toString();