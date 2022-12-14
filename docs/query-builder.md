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
            ->where('status', 'active')     // AND user.status = 'active'
            ->orWhere('age', '>=', 25)      // OR user.age >= 25
            ->not('gender', 'female')       // AND user.gender != 'female'
            ->orNot('gender', 'female')     // OR user.gender != 'female'

            ->isNull('first_name')          // AND user.first_name IS NULL
            ->orIsNull('first_name')        // OR user.first_name IS NULL
            ->notNull('first_name')         // AND user.first_name IS NOT NULL
            ->orNotNull('first_name')       // OR user.first_name IS NOT NULL

            ->in('role', [1, 2, 3])         // AND user.role IN (1,2,3)
            ->orIn('role', [1, 2, 3])       // OR user.role IN (1,2,3)
            ->notIn('role', [4, 5, 6])      // AND user.role NOT IN (4,5,6)
            ->orNotIn('role', [4, 5, 6])    // OR user.role NOT IN (4,5,6)

            ->like('nickname', '%john%')    // AND user.nickname LIKE '%john%'
            ->orLike('nickname', '%john%')  // OR user.nickname LIKE '%john%'
            ->notLike('nickname', '%john%') // AND user.nickname NOT LIKE '%john%'
            ->orNotLike('nickname', '%john%') // OR user.nickname NOT LIKE '%john%'

            ->between('age', 25, 40)        // AND user.age BETWEEN 25 AND 40
            ->orBetween('age', 25, 40)      // OR user.age BETWEEN 25 AND 40
            ->notBetween('age', 25, 40)     // AND user.age NOT BETWEEN 25 AND 40
            ->orNotBetween('age', 25, 40)  // OR user.age NOT BETWEEN 25 AND 40

            ->betweenDate('dob', '1999-08-01', '1999-08-31')        // AND user.dob >= '1999-08-01' AND user.dob < '1999-08-31'
            ->orBetweenDate('dob', '1999-08-01', '1999-08-31')      // OR user.dob >= '1999-08-01' AND user.dob < '1999-08-31'
            ->notBetweenDate('dob', '1999-08-01', '1999-08-31')     // AND user.dob < '1999-08-01' AND user.dob >= '1999-08-31'
            ->orNotBetweenDate('dob', '1999-08-01', '1999-08-31')   // OR user.dob < '1999-08-01' AND user.dob >= '1999-08-31'

            ->betweenDateInterval('dob', '1999-08-01', 7)        // AND user.dob >= '1999-08-01' AND user.dob < '1999-08-01' + INTERVAL 7 DAY
            ->orBetweenDateInterval('dob', '1999-08-01', 7)      // OR user.dob >= '1999-08-01' AND user.dob < '1999-08-01' + INTERVAL 7 DAY
            ->notBetweenDateInterval('dob', '1999-08-01', 7)     // AND user.dob < '1999-08-01' AND user.dob >= '1999-08-01' + INTERVAL 7 DAY
            ->orNotBetweenDateInterval('dob', '1999-08-01', 7);  // OR user.dob < '1999-08-01' AND user.dob >= '1999-08-01' + INTERVAL 7 DAY


```
## Other Clauses
```php
echo Query::from('user')
        ->groupBy('gender', 'role', '{profile.name}')     // GROUP BY user.gender, user.role, profile.name

        ->orderBy('first_name')         // ORDER BY user.first_name ASC
        ->orderBy('first_name', 'DESC') // ORDER BY user.first_name DESC

        ->orderBy([                     // ORDER BY user.role ASC, user.gender DESC, profile.name ASC
            'role' => 'ASC',
            'gender' => 'DESC',
            '{profile.name} => 'ASC',
        ])

        ->orderByRaw('{role} ASC, {gender} DESC, {profile.name} ASC') // ORDER BY user.role ASC, user.gender DESC, profile.name ASC

        ->having('age', 1)          // HAVING user.age = 1
        ->having('age', '>', 10)    // HAVING user.age > 10
        ->havingRaw('COUNT({age}) > 10') // HAVING COUNT(user.age) > 10

        ->limit(20)                     // LIMIT 20
        ->limit(20, 1);                 // LIMIT 1, 20
```

## Group Conditions
```php
// output: SELECT * FROM user WHERE user.status = 'active' AND ( user.gender != 'male' or user.age > 25 );
echo Query::from('user')
            ->where('status', 'active')
            ->where(function($query) {
                $query
                    ->not('gender', 'male')
                    ->orWhere('age', '>', 25);
            });

// output: SELECT * FROM user WHERE user.status = 'active' OR ( user.gender != 'male' or user.age > 25 );
echo Query::from('user')
            ->where('status', 'active')
            ->orWhere(function($query) {
                $query
                    ->not('gender', 'male')
                    ->orWhere('age', '>', 25);
            });
```

## Join Table
```php
// output: SELECT user.first_name, role.name FROM user INNER JOIN role ON role.id => user.role_id;
echo Query::select('first_name', '{role.name}')
            ->from('user')
            ->join('role', ['id' => 'role_id']);   // `id` is field from `role`, `role_id` is from `user`

// Using table alias
// output: SELECT u.first_name, r.name FROM user AS u INNER JOIN role AS r ON r.id => u.role_id;
echo Query::select('first_name', '{r.name}')
            ->from('user AS u')
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
$count = Query::from('user')->where('age', '>=', 25)->count();

// output: SELECT COUNT(user.id) AS total FROM user WHERE user.age >= 25
$count = Query::from('user')->where('age', '>=', 25)->count('id', 'total');

// output: SELECT COUNT(DISTINCT user.gender) FROM user WHERE user.age >= 25
$count = Query::from('user')->where('age', '>=', 25)->count('DISTINCT {gender}');

// output: SELECT MAX(user.dob) FROM user
$max = Query::from('user')->max('dob');

// output: SELECT MAX(user.dob) AS max_dob FROM user WHERE user.gender = 'male'
$max = Query::from('user')->where('gender', 'male')->max('dob', 'max_dob');

// output: SELECT SUM(invoice.amount) FROM invoice WHERE invoice.status = 'unpaid'
$sum = Query::from('invoice')->where('status', 'unpaid')->sum('amount');

// output: SELECT AVG(competition.score) FROM competition WHERE competition.finished = 'yes'
$avg = Query::from('competition')->where('finished', 'yes')->avg('score');

```

# Merge One or More Query Objects with merge() and orMerge()

**IMPORTANT**: All query objects to be merged MUST have same table name.
```php
$query1 = Query::from('user)->where('age', '>=', 25)->orderBy('age');

// SELECT * FROM user WHERE user.gender = 'm' AND user.age >= 25 ORDER BY user.age ASC, user.name ASC
echo Query::from('user')->where('gender', 'm')->merge($query1)->orderBy('name');

// SELECT * FROM user WHERE user.gender = 'm' OR user.age >= 25 ORDER BY user.age ASC, user.name ASC
echo Query::from('user')->where('gender', 'm')->orMerge($query1)->orderBy('name');

```

# Retrieve Data

```php
use Simsoft\ADOdb\DB;
use Simsoft\ADOdb\Query;
use Model\User;

// use "mysql" connection and retrieve data in array
$array = Query::db('mysql')->from('user')->where('age', '>=', 25)->getArray();
print_r($array);
/*
0 => array('emp_no' => 1000,
           'emp_name' => 'Joe Smith',
           'hire_date' => '2014-01-12'
           ),
1 => array('emp_no' => 1001,
           'emp_name' => 'Fred Jones',
           'hire_date' => '2013-11-01'
           ),
2 => array('emp_no' => 1002,
           'emp_name' => 'Arthur Dent',
           'hire_date' => '2010-09-21'
           ),
*/

// use "mysql" connection and retrieve data in recordset
$db = DB::use('mysql');
$result = Query::db($db)->from('user')->where('age', '>=', 25)->execute();
while ($r = $result->fetchRow()) {
    echo $r[0];
    echo $r[1];
}

$array = Query::db($db)->select('emp_no', 'emp_name')->from('employees')->getAssoc();
print_r($array);
// output: [1000 => 'Joe Smith', 1001 => 'Fred Jones', 1002 => 'Arthur Dent']

// Return in data in Active Record.
$users = Query::db($db)->activeRecordClass(User::class)->where('age', '>=', 25)->findAll();
foreach($users as $user) {
    echo $user->first_name;
    echo $user->last_name;
}
```
# Debug
Enable debug by calling **debug()** method
```php
$users = Query::db($db)->where('age', '>=', 25)->debug()->findAll();
```
