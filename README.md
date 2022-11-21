# ADOdb ORM

This is an ORM wrapper for ADOdb (https://adodb.org/)

## Requirements

- PHP >= 7.4

## Installation

1. Install package using composer

```shell
$ composer require simsoft/adodb-orm
```

## Getting Started

```php
require_once "vendor/autoload.php";
require_once "vendor/adodb/adodb-php/adodb.inc.php";
require_once "vendor/adodb/adodb-php/adodb-active-record.inc.php";

use Sim\ADOdb\DB;
use Sim\ADOdb\Query;

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

$result = DB::use('mysql')->execute('SELECT * FROM table1 WHERE attr1 = ? AND attr2 = 2', ['value1', 'value2']);

while ($row = $result->fetchRow()) {
    print_r($row);
}

// Insert new record
$success = DB::use('mysql')->insert('table', ['attr1' => 'value1', 'attr2' => 'value2']);

// Update an record.
$success = DB::use('mysql')->update('table', , ['attr1' => 'value1', 'attr2' => 'value2'], 'id=2');
$success = DB::use('mysql')->update('table', , ['attr1' => 'value1', 'attr2' => 'value2'], Query::where('id', 2));

// Return result in array
$result = DB::use('mysql')->getArray('SELECT * FROM user WHERE name LIKE ?', ['%john%']);

// Enable debug mode
$result = DB::use('mysql')->debug()->getArray('SELECT * FROM user WHERE name LIKE ?', ['%john%']);

```


## Basic Usage

### Query Builder

```php
require_once "vendor/autoload.php";
require_once "vendor/adodb/adodb-php/adodb.inc.php";
require_once "vendor/adodb/adodb-php/adodb-active-record.inc.php";

use Sim\ADOdb\DB;
use Sim\ADOdb\Query;

$config = [
    'mysql' => [
        'driver' => 'mysqli',
        'host' => '127.0.0.1',
        'user' => 'username',
        'pass' => 'password',
        'schema' => 'db_example',
    ],    
];

DB::init($config);

$result = Query::db('mysql')->from('table')->where('attr1', 'value1')->where('attr2', 'value2')->execute();

while ($row = $result->fetchRow()) {
    print_r($row);
}

```

### Active Record

```php

namespace Model;

use Sim\ADOdb\ActiveRecord;

/**
 * Class User 
 */
class User extends ActiveRecord
{
    /** @var string Connection name */
    public $_dbat = 'mysql';

    /** @var string table name */
    public $_table = 'user';
}
```

```php
require_once "vendor/autoload.php";
require_once "vendor/adodb/adodb-php/adodb.inc.php";
require_once "vendor/adodb/adodb-php/adodb-active-record.inc.php";

use Sim\ADOdb\DB;
use Model\User;

$config = [
    'mysql' => [
        'driver' => 'mysqli',
        'host' => '127.0.0.1',
        'user' => 'username',
        'pass' => 'password',
        'schema' => 'db_example',
    ],    
];

DB::init($config);

$users = User::query()->where('age', '>', 20)->where('gender', 'male')->findAll();

foreach ($users as $user) {
    echo $user->first_name;
    echo $user->last_name;
}

```

## Documents
1. [DB class](docs/db.md)
2. [Query Builder](docs/query-builder.md)
3. [Active Record](docs/active-record.md)

## License
The WeCanTrack PHP API is licensed under the MIT License. See the [LICENSE](LICENSE) file for details
