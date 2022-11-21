# Active Record

Enhanced Active Record.

## Create Active Record Class

```php

namespace Model;

use Sim\ADOdb\ActiveRecord;

class User extends ActiveRecord
{
    /** @var string Connection name */
    public $_dbat = 'mysql';

    /** @var string table name */
    public $_table = 'user';

    /** @var string|array Primary key fields */
    protected mixed $primaryKey = 'id';

    /** @var array Attributes that cannot be mass assigned. */
    protected array $guarded = [];

    /** @var array Attributes that are mass assignable */
    protected array $fillable = [];

    /**
     * @var array Attributes casts. Supported casts int, bool, float, string, array 
     * 
     * protected array $casts = [
     *  'attribute1' => 'int',
     *  'attribute2' => 'bool',
     *   ...
     * ];
     */
    protected array $casts = [];   
}

```

## Insert & Update
```php
use Model\User;

// Insert new user
$user = new User();
$user->first_name = 'john';
$user->last_name = 'Doe';
$user->save();

// Insert new user with mass assignment
$user = new User();
$user->fill([
    'first_name' => 'john',
    'last_name' => 'Doe',
]);
$user->save();


// Update user where its primary key is 2
$user = (new User())->findByPk(2);
$user->first_name = 'john';
$user->last_name = 'doe2';
$user->dob = '2000-01-01';
$user->save();


// Update user where its primary key is 2 with mass assignment
$user = (new User())->findByPk(2);
$user->fill([
    'first_name' => 'john',
    'last_name' => 'doe2',
    'dob' => '2000-01-01',
]);
$user->save();


```

## Retrieve Data
```php
// Get user info by primary key is 2
$user = (new User())->findByPk(2);
echo $user->getKey();   // display primary key value
echo $user->first_name;
echo $user->last_name;

// output: [ first_name => 'john', last_name => 'doe', ..., ... ]
print_r($user->getAttributes());

// Get one record
$user = User::query()->where('age', '>=', 25)->findOne():

// Get all users records
$users = User::query()->where('age', '>=', 25)->findAll():
foreach($users as $user) {
    echo $user->first_name;
    echo $user->last_name;
}

// Get total user.
echo User::query()->where('age', '>=', 25)->count();

// Other aggregation query
echo User::query()->where('age', '>=', 25)->max('dob');
echo User::query()->where('age', '>=', 25)->min('dob');
```

## Debug
```php
// Enable debug mode by calling debug() method
$users = User::query()->where('age', '>=', 25)->debug()->findAll():
```