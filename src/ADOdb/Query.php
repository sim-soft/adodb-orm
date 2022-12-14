<?php

namespace Simsoft\ADOdb;

use Simsoft\ADOdb\Builder\ActiveQuery;
use Simsoft\ADOdb\Builder\MysqliQuery;

/**
 * Class Query.
 *
 * @method static ActiveQuery activeRecordClass(string $class)
 * @method static ActiveQuery merge(ActiveQuery $query)
 * @method static ActiveQuery from(string|array $table)
 * @method static ActiveQuery join(string|array $table, array $on = [], string $type = 'INNER')
 * @method static ActiveQuery crossJoin(string|array $table, array $on = [])
 * @method static ActiveQuery leftJoin(string|array $table, array $on = [])
 * @method static ActiveQuery rightJoin(string|array $table, array $on = [])
 * @method static ActiveQuery leftOuterJoin(string|array $table, array $on = [])
 * @method static ActiveQuery rightOuterJoin(string|array $table, array $on = [])
 * @method static ActiveQuery with(string $alias, callable $condition)
 * @method static ActiveQuery select(...$attributes)
 * @method static ActiveQuery selectRaw(string $sql)
 * @method static ActiveQuery whereRaw(string $sql, array $binds = [], string $logicalOperator = 'AND')
 * @method static ActiveQuery where(string|callable $attribute, ?string $operator = '=', mixed $value = null, string $logicalOperator = 'AND')
 * @method static ActiveQuery in(string $attribute, array $values, string $logicalOperator = 'AND')
 * @method static ActiveQuery like(string $attribute, string $value, bool $is = true, string $logicalOperator = 'AND')
 * @method static ActiveQuery between(string $attribute, mixed $start, mixed $end, bool $is = true, string $logicalOperator = 'AND')
 * @method static ActiveQuery betweenDate(string $attribute, ?string $startDate = null, ?string $endDate = null, bool $is = true, string $logicalOperator = 'AND')
 * @method static ActiveQuery betweenDateInterval(string $attribute, string $startDate, int $interval = 7, bool $is = true, string $logicalOperator = 'AND')
 * @method static ActiveQuery groupBy(...$attributes)
 * @method static ActiveQuery orderBy(string|array $attribute, string $direction = 'ASC')
 * @method static ActiveQuery orderByRaw(string $sql)
 * @method static ActiveQuery limit(int $max, ?int $offset = null)
 */
class Query
{
    /**
     * Constructor.
     *
     * @param null|DB|string $db The connection string
     * @param string|null $class ActiveRecord class
     */
    public function __construct(
        protected DB|string|null $db = null,
        protected ?string $class = null
    ) {
        if ($this->class && $this->db === null) {
            $this->db = (new $this->class())->_dbat;
        }

        if (is_string($this->db)) {
            $this->db = DB::use($this->db);
        }
    }

    /**
     * Get query object.
     *
     * @return ActiveQuery
     */
    public function __invoke(): ActiveQuery
    {
        return $this->createActiveQuery();
    }

    /**
     * Get active query object
     *
     * @param string|DB|null $db The connection string
     * @param string|null $class ActiveRecord class
     * @return ACtiveQuery
     */
    public static function db(mixed $db = null, ?string $class = null): ActiveQuery
    {
        return (new static($db, $class))->createActiveQuery();
    }

    /**
     * Get active query object
     *
     * @param string $class ActiveRecord class
     *
     * @return ACtiveQuery
     */
    public static function class(string $class): ActiveQuery
    {
        return (new static(class: $class))->createActiveQuery();
    }

    /**
     * Get active query instance.
     *
     * @return ActiveQuery
     */
    public function getInstance(): ActiveQuery
    {
        return $this->createActiveQuery();
    }

    /**
     * Get active query object method.
     *
     * @param string $method    the method name of the active query object
     * @param array  $arguments the arguments to be sent to the method
     *
     * @return ActiveQuery
     */
    public static function __callStatic(string $method, array $arguments = []): ActiveQuery
    {
        return call_user_func_array([(new static())->createActiveQuery(), $method], $arguments);
    }

    /**
     * Get active query object method.
     *
     * @param string $method    the method name of the active query object
     * @param array  $arguments the arguments to be sent to the method
     *
     * @return ActiveQuery
     */
    public function __call(string $method, array $arguments = []): ActiveQuery
    {
        return call_user_func_array([$this->createActiveQuery(), $method], $arguments);
    }

    /**
     * Get query object.
     *
     * @return ActiveQuery
     */
    public function createActiveQuery(): ActiveQuery
    {
        if ($this->db === null) {
            return new MysqliQuery();
        }

        return match ($this->db->getDatabaseType()) {
            //'mysqli' => new MysqliQuery($this->db, $this->class),
            default => new MysqliQuery($this->db, $this->class),
        };
    }
}
