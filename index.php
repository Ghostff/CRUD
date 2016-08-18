<?php

require 'DB.php';

DB::Config([
	'debug' => true,
	'forceDB' => true,
	'forcTable' => true
]);
DB::init('localhost', 'tester', 'root', '');

//create table
$test = new DB('Shop');
$test->int('id', null, null, true)->primary()
	 ->varchar('fname', '255')
	 ->varchar('lname', '255')
	 ->varchar('email', '255')
	 ->int('time')
	 ->create();
