# Norm (Not Only SQL ORM)

A very simple PHP-ORM for both SQL and NoSQL databases.

### Features:
- Simple unified interface for SQL and NoSQL databases
- Relational database support (MySQL/MSSql/SQLite via PDO)
- NoSQL database support (MongoDB)
- Multiple simultaneous connections support
- CRUD ready (Add, Get/Find, Edit, Kill)

------------


### Usage:
##### Connection:
```php
$nrm = new Norm("driver=mysql;host=127.0.0.1;user=mysql;password=mysql;database=norm");
```
or
```php
$nrm = new Norm("driver=mongo;host=127.0.0.1;database=norm");
```
or
```php
$nrm = Norm::init([
	"driver=mysql;host=127.0.0.1;user=mysql;password=mysql;database=norm", // name: `norm` (first-default)
	"driver=mysql;host=127.0.0.2;user=mysql;password=mysql;database=norm", // name: `norm_mysql`
	"driver=mongo;host=127.0.0.1;database=norm", // name: `norm_mongo`
	"name=mybase;driver=mongo;host=127.0.0.2;database=norm2", // name: `mybase`
	"driver=mongo;host=127.0.0.3;database=test", // name: `test`
]);
```

#### Insert single record/document:
```php
// return ID of new item or 0 on fail
$newID = $nrm("mongo")->users->add([
	'name'=>'Ivan',
	'age'=>'20'
]);
```

#### Get single record(document) by id:
```php
$assocArray = $nrm->users->get(1234);
```
or
```php
$assocArray = $nrm("mongo2")->products->get('5f7771bb05f3b512a43ea6b2')
```

#### Update records(documents):
```php
$affectedRows = $nrm->users->edit(1234,[  // by id
	'age'=>21
]);
```
or
```php
$affectedRows = $nrm->users->edit('5f7771bb05f3b512a43ea6b2',[ // by id
	'age'=>21
]);
```
or 
```php
$affectedRows = $nrm->users->edit(
	['name'=>'Ivan'], // where
	['age'=>21]	// set
);
```

#### Delete(Remove) records(documents):
```php
$affectedRows = $nrm->users->kill(1234);
```
or
```php
$affectedRows = $nrm->users->kill('5f7771bb05f3b512a43ea6b2');
```
or
```php
$affectedRows = $nrm->users->kill([
	'name'=>'Ivan',
	'age'=>21,
]);
```
#### Select/Find records(documents):
```php
$generator = $nrm->users->find([
	'name'=>'Ivan',
	'age'=>21,
]);

foreach($generator as $item){
	print_r($item);
}
```

#### Dereferencing(Cached MongoDB DBRef resolving):
```php
$generator = $nrm("mongo")->catalog->find([],[
	'dereferencing'=>100, // 0=disable; 1=oneByOne(very slow); >1=dereferencing pageSize
]);
```

#### Left joining for mysql(output has dereferenced form):
```php
$generator = $nrm->sales([],[
	"product"=>"products.id",
	"seller"=>"users.id",
	"client"=>"users", // id by default
])->find([
	'sum >'=>10, // sales.sum
	'seller.name LIKE%'=>'Joh',
	'seller.id NOTIN'=>[101,102],
	'client.x IN'=>'SELECT x FROM users', // nested queries is plan
]);
```