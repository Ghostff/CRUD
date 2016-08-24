# CRUDE
An easy PHP ORM

# CRUDE
An easy PHP ORM

INSERT METHODS
-------

Method 1:   
```php 
    $query = new Query\Insert('Product');
    $query->title = 'shoes';
    $query->price = 10.99;
    $query->name = 'Chrys';
    $query->end();

    $query->end(true) //returns last Inserted ID
```

Method 2:   
```php
    $query->into('title,price,name')
               ->value(['Shoes', 10.99, 'aba'])
               ->end();
               
    ->end(true) //returns last Inserted ID

 ```
 
Method 3:  
```php
    $query = new Query\Insert;
    $query->query(
        "INSERT INTO Product (title, price, name) 
        VALUES ('Shirt', '2.55', 'FooBar')"
     )->end();
     
     ->end(true) //returns last Inserted ID
```
UPDATE METHODS
-------
Method 1:
```php
    $query = new Query\Update('Product');
    $query->title = 'Bar';
    $query->price = 15.99;
    $query->name = 'Foo';
    
    //Match Cases
    $query->where('id', 1) | $query->where(['id', 1]); //single match
    
    $query->where(['id' => 1, 'name' => 'Bar']); //multiple matches [WHERE .. AND ...] (defualt AND)
    $query->where(['id' => 1, 'name' => 'Bar'], 'OR'); //multiple matches [WHERE .. OR ...]
    
    $query->end();
    $query->end(true) //returns numbers of updated columns
```
Method 2:
```php
    $query = new Query\Update('Product');
    $query->set('title, price, name')
            ->to(['Foo', 20, 'Bar'])
            ->where(['id' => 5, 'name' => 'chrys'], 'Or')
            ->end();
          
   ->end(true) //returns numbers of updated columns
```
Method 3:
```php
    $query = new Query\Update('Product');
    $query->query("UPDATE `Product` SET `title` =  'Foo' WHERE (`id` = 2)")
          ->end();
          
    ->end(true) //returns numbers of updated columns
```
DELETE METHODS
-------
Method 1:
```php
    $query = new Query\Delete('Product');
    $query->where('id', 3) //Single match | $query->where(['id' => 1, 'name' => 'Bar'], ..OR..)
          ->end();
          
    ->end(true) //returns numbers of deleted columns
```
Method 2:
```php
    $query = new Query\Delete('Product');
    $query->query("DELETE FROM `Product` WHERE  (`id` = 2)")
          ->end();
          
    ->end(true) //returns numbers of deleted columns
```

