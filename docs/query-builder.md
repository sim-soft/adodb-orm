# Query Builder

Basic usage of query builder.

## Generate SQL statement

```php
use Simsoft\ADOdb\Query;

// output: SELECT * FROM user
echo Query::from('user');

// output: SELECT user.first_name, user.last_name FROM user WHERE user.age >= 25 AND user.gender = male;
echo Query::select('first_name', 'last_name')->from('user')->where('age', '>=', 25)->where('gender', 'male');

// output: SELECT user.gender, COUNT(user.id) AS total FROM user WHERE user.status = 'active' GROUP BY user.gender.
echo Query::select('gender')
                ->selectRaw('COUNT({id}) AS total')
                ->from('user')
                ->where('status', 'active')
                ->groupBy('gender');

```

## Conditions Method
```php
echo Query::from('user')
            ->where('status', 'active') // AND user.status = 'active'
            ->orWhere('age', '>=', 25)  // OR user.age >= 25
            ->not('gender', 'female')   // AND user.gender != 'female'
            ->orNot('gender', 'female') // OR user.gender != 'female'
            ->isNull('first_name')      // AND user.first_name IS NULL
            ->notNull('first_name')      // OR user.first_name IS NOT NULL
            ->in('role', [1, 2, 3])     // AND user.role IN (1,2,3)
            ->notIn('role', [4, 5, 6])  // AND user.role NOT IN (4,5,6)
            ->like('nickname', '%john%') // AND user.nickname LIKE '%john%'
            ->between('age', 25, 40);    // AND user.age BETWEEN 25 AND 40   
```
## Other Clauses
```php
echo Query::from('user')
        ->groupBy('gender', 'role')     // GROUP BY user.gender, user.role
        ->orderBy('first_name')         // ORDER BY user.first_name ASC
        ->orderBy('first_name', 'DESC') // ORDER BY user.first_name DESC
        ->orderBy([                     // ORDER BY user.role ASC, user.gender DESC
            'role' => 'ASC',
            'gender' => 'DESC',
        ])
        ->limit(20)                     // LIMIT 20
        ->limit(20, 1);                 // LIMIT 1, 20
```

## Group query
```php

// output: SELECT * FROM user WHERE user.status = 'active' AND ( user.gender != 'male' or user.age > 25 );
echo Query::from('user')
            ->where('status', 'active')
            ->where(function() {
                $this
                    ->not('gender', 'male')
                    ->orWhere('age', '>', 25);
            });
```

## Join Table
```php
// output: SELECT user.first_name, r.name FROM user INNER JOIN role AS r ON r.id => user.role_id;
echo Query::select('first_name', '{r.name}')
            ->from('user')
            ->join('role AS r', ['id' => 'role_id']);

// Using table alias
// output SELECT u.first_name, r.name FROM user u 
//    INNER JOIN role r ON r.id = u.role_id
//    LEFT JOIN status s ON s.id = u.status_id;
echo Query::select('first_name', '{r.name}')
            ->from('user u')
            ->join('role r', ['id' => 'role_id'])
            ->leftJoin('status s', ['id' => 'status_id']);
```

## Raw Methods
```php
// output: SELECT u.first_name, u.last_name, r.name FROM user u LEFT JOIN role r ON r.id = u.role_id WHERE u.age > 25 AND r.name = 'admin'
echo Query::select('first_name')
            ->selectRaw('{last_name}, {r.name}')
            ->from('user u')
            ->leftJoin('role r', ['id' => 'role_id'])
            ->whereRaw('{age} > 25 AND {r.name} = "admin"');
```

## Aggregation Methods
```php
// output: SELECT COUNT(*) FROM user WHERE user.age >= 25
echo Query::from('user')->where('age', '>=', 25)->count();

// output: SELECT COUNT(user.id) AS total FROM user WHERE user.age >= 25
echo Query::from('user')->where('age', '>=', 25)->count('id', 'total');

// output: SELECT COUNT(DISTINCT user.gender) FROM user WHERE user.age >= 25
echo Query::from('user')->where('age', '>=', 25)->count('DISTINCT {gender}');

// output: SELECT MAX(user.dob) FROM user
echo Query::from('user')->max('dob');

// output:: SELECT MAX(user.dob) AS max_dob FROM user WHERE user.gender = 'male'
echo Query::from('user')->where('gender', 'male')->max('dob', 'max_dob');

// output:: SELECT SUM(invoice.amount) FROM invoice WHERE invoice.status = 'unpaid'
echo Query::from('invoice')->where('status', 'unpaid')->sum('amount');

```

# Retrieve Data

```php
use Simsoft\ADOdb\DB;
use Simsoft\ADOdb\Query;
use Model\User;

// use "mysql" connection and retrieve data in array
$result = Query::db('mysql')->from('user')->where('age', '>=', 25)->getArray();

// use "mysql" connection and retrieve data in recordset
$db = DB::use('mysql');
$result = Query::db($db)->from('user')->where('age', '>=', 25)->execute();

// Return in data in Active Record.
$users = Query::db($db)->class(User::class)->where('age', '>=', 25)->findAll();
foreach($users as $user) {
    echo $user->first_name;
    echo $user->last_name;
}
```
Debug
```php
// Enable debug by calling debug() method
$users = Query::db($db)->where('age', '>=', 25)->debug()->findAll();
```