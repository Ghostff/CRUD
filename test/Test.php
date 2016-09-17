<?php

class Test
{
	protected $name = null;
	
	private $method = null;
	
	protected $expected = null;
	
	protected function imagine($imagine, $with)
	{
		@eval("$with");	
		$imagine = preg_replace(array('/\s+/', '/\s*Data:/'), array(' ', 'Data:'), trim($imagine));
		$imagine = str_replace('\s', ' ', $imagine);
		
		//var_dump(strip_tags($test), $imagine);
		
		if ( $imagine == strip_tags($test)) {
			echo sprintf(
				'<code style="color:green">(%s) %s is --OK </code><br>',
				$this->method, $this->name
			);
		}
		else {
			echo sprintf(
				'<code style="color:red">(%s) %s --FAILED </code><br>',
				$this->method, $this->name
			);
		}
	}
	public function __construct()
	{
		foreach (get_class_methods($this) as $name) {
			
			if (substr($name, 0, 6) == '_test_') {
				
				$test_method_name = explode('_', $name);
				$this->method = $test_method_name[2];
				$this->{$name}();
				echo '<p/>';
			}
			
		}
	}
}
