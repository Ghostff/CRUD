<?php

use PDOConnection\DB as DB;

require 'DB.php';
    
DB::Config([
    'debug' => true,
    'forceDB' => false,
    'forcTable' => false
]);
DB::init('localhost', 'tester', 'root', '');


$query = new Sql\Select('name,id');
$query->from('Product');
var_dump( $query->toString(true));


?>

