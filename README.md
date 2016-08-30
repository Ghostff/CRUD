# CRUD
An easy PHP ORM

INSERT METHOD
------- 
```php 
    $query = new Query\InsertInto('Product');
    $query->title = 'shoes';
    $query->price = 10.99;
    $query->name = 'FooBar';
    $query->commit();
```
SELECT METHOD
-------
```php
    $query = new Query\Select('title, price, name');
    $query->from('Product')->commit();
```   

UPDATE METHOD
-------
```php
    $query = new Query\Update('Product');
    $query->set('name', 'Foo')
    	  ->where('title', 'Shoes')
          ->commit();
```
DELETE METHOD
-------
```php
    $query = new Query\deleteFrom('Product');
    $query->where('title', 'Shoes')
    	  ->orWhere('name', 'FooBar')
          ->commit(); 
```    

