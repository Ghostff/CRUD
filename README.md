# CRUDE
An easy PHP ORM

INSERT METHODS
-------

Method 1:   
```php 
$query = new Query\Insert('Product');
$query->title = 'Shirt';
$query->price = 2.55;
$query->date = date('Y-m-d');
```

Method 2:   
```php
$query = new Query\Insert('Product');
$query->into('title, price, date')
	  ->values(['Shirt', 2.55, date('Y-m-d')]);
 ```
 
Method 3:  
```php
$query = new Query\Insert;
$query->query("INSERT INTO Product (title, price, date) 
			   VALUES ('Shirt', '2.55', '2016-08-20 00:00:00')"
);
```
