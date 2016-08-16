<?php

require 'App/DB.php';

DB::Config([
	'debug' => true,
	'forceDB' => true,
	'forcTable' => true
]);
DB::init('localhost', 'tester', 'root', '');

/*
insert
$test = new DB('Product');
$test->title = 'hey';
$test->price = 20.33;
$test->date = date('Y-m-d');
$test->save();
*/

/*
update
$test = new DB('Product');
$test->title = 'Shoes';
$test->price = 233.6;

$test->update([
	'id' => 1
]);
*/

//select
$test = new DB('Product');
$test->where(['id' => 1])->select();
var_dump( $test->all);
