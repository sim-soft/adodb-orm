# DB class

Basic usage of DB class.


## Initiate ADOdb DB

```php
require_once "vendor/autoload.php";
require_once "vendor/adodb/adodb-php/adodb.inc.php";
require_once "vendor/adodb/adodb-php/adodb-active-record.inc.php";

use Simsoft\ADOdb\DB;

$config = [
    'mysql' => [
        'driver' => 'mysqli',
        'host' => '127.0.0.1',
        'user' => 'username',
        'pass' => 'password',
        'schema' => 'db_example',
    ],
    /* Sample config
    'connection_name' => [
        'driver' => 'mysqli',
        'host' => '127.0.0.1',
        'user' => 'username',
        'pass' => 'password',
        'schema' => 'database_name',
        'execute' => [ // run once when the connection is successful.
            "SET @@session.time_zone='+00:00'",
        ],
    ],*/
];

DB::init($config);
```

## Insert & Update

```php
// Insert new record
$success = DB::use('mysql')->insert('table', ['attr1' => 'value1', 'attr2' => 'value2']);

// Update an record.
$success = DB::use('mysql')->update('table', , ['attr1' => 'value1', 'attr2' => 'value2'], 'id=2');

// Build the update condition with Query object
$success = DB::use('mysql')->update('table', , ['attr1' => 'value1', 'attr2' => 'value2'], Query::where('id', 2));
```

## Retrieve Data

```php
// Return query result (or RecordSet) object
$result = DB::use('mysql')->execute('SELECT * FROM table1 WHERE attr1 = ? AND attr2 = ?', ['value1', 'value2']);

while ($row = $result->fetchRow()) {
    print_r($row);
}

// Return data in array
$result = DB::use('mysql')->getArray('SELECT * FROM table1 WHERE attr1 = ? AND attr2 = ?', ['value1', 'value2']);

foreach($result as $row) {
    echo $row['attr1'];
}

// Other connection methods
$result = DB::use('mysql')->getRandRow('SELECT * FROM table1 WHERE attr1 = ? AND attr2 = ?', ['value1', 'value2']);
$result = DB::use('mysql')->getAssoc('SELECT * FROM table1 WHERE attr1 = ? AND attr2 = ?', ['value1', 'value2']);
$result = DB::use('mysql')->getOne('SELECT * FROM table1 WHERE attr1 = ? AND attr2 = ?', ['value1', 'value2']);
$result = DB::use('mysql')->getRow('SELECT * FROM table1 WHERE attr1 = ? AND attr2 = ?', ['value1', 'value2']);
$result = DB::use('mysql')->getCol('SELECT attr1 FROM table1 WHERE attr1 = ? AND attr2 = ?', ['value1', 'value2']);
$result = DB::use('mysql')->selectLimit('SELECT * FROM table1 WHERE attr1 = ? AND attr2 = ?', 20, 3, ['value1', 'value2']);

// Return result in Active Record object.
$result = DB::use('mysql')->getActiveRecordsClass(
    User::class,            // An active record class
    'user',                 // table name
    'age = ? AND name = ?', // The where condition
    [25, 'john']            // The bind values
);
```

## Transaction

```php

$db = DB::use('mysql');

// transaction method expecting a callable which return a bool value.
// Return true will commit,
// Return false will rollback.
$db->transaction(function() use ($db) {

    $error = 0;
    if (!$db->insert('table1', ['attr1' => 'value1'])) {
        ++$error;
    }

    if (!$db->insert('table2', ['attr1' => 'value1'])){
        ++$error;
    }

    return $error == 0;
});

// Using smart transaction.
$db->smartTransaction(function() use ($db) {

    $error = 0;
    if (!$db->insert('table1', ['attr1' => 'value1'])) {
        ++$error;
    }

    if (!$db->insert('table2', ['attr1' => 'value1'])){
        ++$error;
    }

    return $error == 0;
});
```

## Debug

```php
// Enable debug mode by calling debug() method.
$result = DB::use('mysql')->debug()->execute('SELECT * FROM table1 WHERE attr1 = ? AND attr2 = ?', ['value1', 'value2']);
$result = DB::use('mysql')->debug()->getArray('SELECT * FROM table1 WHERE attr1 = ? AND attr2 = ?', ['value1', 'value2']);
```
