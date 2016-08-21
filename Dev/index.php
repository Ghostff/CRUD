<?php

require 'Dump.php';


use PDOConnection\DB as DB;


require 'DB.php';
	
DB::Config([
	'debug' => true,
	'forceDB' => false,
	'forcTable' => false
]);
DB::init('localhost', 'tester', 'root', '');

$query = new Query\Select('Product');
$query->columns();