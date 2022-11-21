Hello,

I encountered an issue with the following code:
```phpt
$recordSet =  Query::db('mysql')
            ->select('attr1', 'attr2')
            ->from('table1')
            ->where('attr1', '>', 10)
            ->getArray();

foreach ($recordSet as $data){
 echo $data['attr1'];
}
```

WeCanTrack version: PUT HERE YOUR WECANTRACK VERSION (exact version)

PHP version: PUT HERE YOUR PHP VERSION

I expected to get:
```phpt
wct200514135314e7x4d
```
But I actually get:
```phpt
null
```
Thanks!