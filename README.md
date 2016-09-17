# CRUD
An easy PHP ORM

INSERT METHODS
------- 
```php 
    $query = new Sql\InsertInto('Product');
    $query->title = 'shoes';
    $query->price = 10.99;
    $query->name = 'FooBar';
    $query->commit();
```
SELECT METHODS
-------
```php
    $query = new Sql\Select('title, price, name');
    $query->from('Product')->commit();
```   
Similarly:
```php
	 Sql\Find::Product_name('Foo')->commit();
	 //select * from Product where name = Foo
```

UPDATE METHODS
-------
```php
    $query = new Sql\Update('Product');
    $query->set('name', 'Foo')
    	  ->where('title', 'Shoes')
          ->commit();
```
DELETE METHODS
-------
```php
    $query = new Sql\deleteFrom('Product');
    $query->where('title', 'Shoes')
    	  ->orWhere('name', 'FooBar')
          ->commit(); 
```    
QUERY METHODS
-------
```php
    new Sql\Query('SELECT * FROM Product WHERE `id` = 10')->commit();
```    


