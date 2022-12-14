<?php declare(strict_types=1);

//require_once 'bootstrap.php';

use PHPUnit\Framework\TestCase;
use Simsoft\ADOdb\Builder\ActiveQuery;
use Simsoft\ADOdb\Query;

final class QueryTest extends TestCase
{
    public function dataProvider(): array
    {
        return [
            'Simple select' => [Query::from('user'), 'SELECT * FROM `user`'],
        ];
    }

    public function dataProvider2(): array
    {
        return [
            'Simple merge' => [Query::from('user')->where('name', 'john'), 'SELECT * FROM `user` WHERE `user`.`age` >= 25 AND `user`.`name` = \'john\''],
            'Simple merge 2' => [Query::from('user')->where('name', 'john')->where('gender', 'f'), 'SELECT * FROM `user` WHERE `user`.`age` >= 25 AND `user`.`name` = \'john\' AND `user`.`gender` = \'f\''],
        ];
    }

    public function dataProvider3(): array
    {
        return [
            'Simple merge' => [Query::from('user')->where('name', 'john'), 'SELECT * FROM `user` WHERE `user`.`age` >= 25 OR `user`.`name` = \'john\''],
            'Simple merge 2' => [Query::from('user')->where('name', 'john')->where('gender', 'f'), 'SELECT * FROM `user` WHERE `user`.`age` >= 25 OR `user`.`name` = \'john\' AND `user`.`gender` = \'f\''],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testSQL(ActiveQuery $generatedSQL, string $expectedSQL): void
    {
        $this->assertSame((string) $generatedSQL, $expectedSQL);
    }

    /**
     * @dataProvider dataProvider2
     */
    public function testMerge(ActiveQuery $query, string $expectedSQL): void
    {
        $generatedSQL = Query::from('user')->where('age', '>=', 25)->merge($query)->getCompleteSQLStatement();
        $this->assertSame($generatedSQL, $expectedSQL);
    }

    /**
     * @dataProvider dataProvider3
     */
    public function testOrMerge(ActiveQuery $query, string $expectedSQL): void
    {
        $generatedSQL = Query::from('user')->where('age', '>=', 25)->orMerge($query)->getCompleteSQLStatement();
        $this->assertSame($generatedSQL, $expectedSQL);
    }
}
