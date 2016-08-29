# CRUDE
An easy PHP ORM


# Functions  
**Where(** [String|array] *$name*, &nbsp;&nbsp;&nbsp;[String|array]*$value* **)**  
If ``$name`` is a ``string``:  
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;``$name``:&nbsp;&nbsp;&nbsp;string of column.  
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;``$value``:&nbsp;&nbsp;string of column value.  
```php
->where('id', 44)
//SELECT ... WHERE id = 44
```
If ``$name`` is an ``array``:  
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;``$name``:&nbsp;&nbsp;array keys(s) should be a specified table column name and key value should be the column value.   
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;``$value``:&nbsp;&nbsp; Use to specify conditions opperator. default ( *AND* )  
```php
->where(array('id' => 33, 'Name' => 'Foo'))
//SELECT ... WHERE id = 33 AND name = 'Foo'
```
while   
```php
->where(array('id' => 33, 'Name' => 'Foo'), 'or')
//SELECT ... WHERE id = 33 OR name = 'Foo'

```
**_toString(** *null* **)**   
Return a string of query that will be executed
```php
$query = new Query\Insert('Product');
$query->title = 'top';
echo $query->_toString();

//OR--

echo $query->into('title')->value('Shoes')->_toString();

//Ouputs:
//        Query: INSERT INTO `Product` (`title`) VALUES (:title)
//        Data: {":title":"top"}

//        Query: INSERT INTO `Product` (`title`) VALUES (:title)
//        Data: {":title":"Shoes"}
```
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
    //OR --
    ->toString() //outputs query string

 ```
 OR
 ```php
    $query->into('title,price,name')
          ->select('Shop')
          ->from('title,price,name')
          ->where('id', 2)
          ->end();
           
    ->end(true) //returns last Inserted ID
    //OR --
    ->toString() //outputs query string

 ```
 
 
Method 3:  
```php
    $query = new Query\Insert;
    $query->query(
        "INSERT INTO Product (title, price, name) 
        VALUES ('Shirt', '2.55', 'FooBar')"
     )->end();
     
     ->end(true) //returns last Inserted ID
     //OR --
     ->toString() //outputs query string
```
UPDATE METHODS
-------
Method 1:
```php
    $query = new Query\Update('Product');
    $query->title = 'Bar';
    $query->price = 15.99;
    $query->name = 'Foo';
    $query->where('id', 1)
          ->end();
    $query->end(true) //returns numbers of updated columns
    //OR --
    ->toString() //outputs query string
```
Method 2:
```php
    $query = new Query\Update('Product');
    $query->set('title, price, name')
          ->to(['Foo', 20, 'Bar'])
          ->where(['id' => 5, 'name' => 'chrys'], 'or')
          ->end();
          
   ->end(true) //returns numbers of updated columns
```
Method 3:
```php
    $query = new Query\Update('Product');
    $query->query("UPDATE `Product` SET `title` =  'Foo' WHERE (`id` = 2)")
          ->end();
          
    ->end(true) //returns numbers of updated columns
    //OR --
    ->toString() //outputs query string
```
DELETE METHODS
-------
Method 1:
```php
    $query = new Query\Delete('Product');
    $query->where('id', 3)
          ->end();
          
    ->end(true) //returns numbers of deleted columns
    //OR --
    ->toString() //outputs query string
```
Method 2:
```php
    $query = new Query\Delete('Product');
    $query->query("DELETE FROM `Product` WHERE  (`id` = 2)")
          ->end();
          
    ->end(true) //returns numbers of deleted columns
    //OR --
    ->toString() //outputs query string
```    
SELECT METHODS
-------
Method 1:
```php
    $query = new Query\Select('Product');
    $query->from('title, price, name')
           ->where('id', 2);
           ->end();
          
    ->end(number) //returns they value of specified key(number)
    
    echo $query->title;
```   

Mapping the columns of each row to named properties in the class   

```php
class ProductClass
{
    public $title = null;
    public $price = null;
    public $name = null;

    public function __construct()
    {
        //echo $this->title;
        //echo $this->price;
        //echo $this->name;
    }

}

$query = new Query\Select('Product', 'ProductClass'); // 
$query->from('title, price, name')
      ->where('id', 2);
      ->end();
```

Return the results of calling the specified function

```php

function productFunction($title, $price, $name)
{
    //echo $title;
    //echo $price;
    //echo $name;
}

$query = new Query\Select('Product', 'function:productFunction');
$query->from('title, price, name')
      ->where('id', 2);
      ->end();
```

