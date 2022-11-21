<?php

namespace Simsoft\ADOdb\Builder;

/**
 * Class MysqliQuery.
 *
 * Build query statement.
 */
class MysqliQuery extends ActiveQuery
{
    /** @var array The select statement */
    public array $selects = [];

    /** @var array The query conditions */
    public array $conditions = [];

    /** @var array The query group by */
    public array $groupBys = [];

    /** @var array The query havings */
    public array $havings = [];

    /** @var array The query order */
    public array $orderBys = [];

    /** @var array Jointed relateionship */
    public array $joins = [];

    /** @var null|string The table alias */
    protected ?string $alias = null;

    /** @var int The limit value */
    protected int $limit = 0;

    /** @var null|int The offset value */
    protected ?int $offset = null;

    /**
     * Merge with other query object.
     *
     * Only can merge if the other query has the same table/ alias.
     *
     * @param ActiveQuery $query the other Query object
     * @param string $logicalOperator The logical operator. Default 'AND'.
     */
    public function merge(ActiveQuery $query, string $logicalOperator = 'AND'): self
    {
        if ($this->table === $query->getTable()) {
            $properties = ['selects', 'conditions', 'groupBys', 'havings', 'orderBys', 'joins', 'binds'];
            foreach ($properties as $property) {
                if (!empty($query->{$property})) {
                    if (in_array($property, ['conditions', 'havings']) && !empty($this->{$property})) {
                        $this->{$property}[] = $logicalOperator;
                    }
                    $this->{$property} = array_merge($this->{$property}, $query->{$property});
                }
            }
        }

        return $this;
    }

    /**
     * Merge with other query object.
     * 
     * Prepand 'OR' to the query.
     * 
     * @param ActiveQuery $query the other Query object
     */
    public function orMerge(ActiveQuery $query): self
    {
        return $this->merge($query, 'OR');
    }

    /**
     * Is the current query has conditions
     * 
     * @return bool
     */
    public function hasConditions(): bool
    {
        return !(empty($this->conditions) && empty($this->groupBys) && empty($this->havings) && empty($this->orderBys));
    }

    /**
     * {@inheritdoc}
     */
    public function from(mixed $table): self
    {
        if (is_array($table)) {
            $this->alias = array_key_first($table);
            $subQuery = current($table);
            $this->table = "({$subQuery}) AS {$this->alias}";
        } else {
            $expressions = explode(' ', trim($table));
            $table = $expressions[0];
            $alias = end($expressions);

            if ($table === $alias) {
                $this->alias = $table;
                $this->table = "`{$table}`";
            } else {
                $this->alias = $alias;
                $this->table = "`{$table}` AS {$this->alias}";
            }
        }

        return $this;
    }

    /**
     * Join table.
     *
     * @param string        $table The table name
     * @param array<string> $on    The matching attributes. ['join_table_attribute' => 'main_table_attribute']
     * @param string        $type  Join type. LEFT, RIGHT, INNER, OUTER, etc
     */
    public function join(string $table, array $on = [], string $type = 'INNER'): self
    {
        $join = $type ? strtoupper($type) . ' JOIN' : 'JOIN';

        $foreignKey = array_key_first($on);
        $localKey = current($on);

        $expressions = explode(' ', trim($table));
        $table = $expressions[0];
        $alias = end($expressions);

        if ($table === $alias) {
            $this->joins[] = "{$join} `{$table}` ON `{$table}`.`{$foreignKey}` = " . $this->qualifier($localKey);
        } else {
            $this->joins[] = "{$join} `{$table}` AS `{$alias}` ON `{$alias}`.`{$foreignKey}` = " . $this->qualifier($localKey);
        }

        return $this;
    }

    /**
     * Cross join table.
     *
     * @param string        $table the join table
     * @param array<string> $on    The matching attributes. ['join_table_attribute' => 'main_table_attribute']
     */
    public function crossJoin(string $table, array $on = []): self
    {
        return $this->join($table, $on, 'CROSS');
    }

    /**
     * Left join table.
     *
     * @param string        $table the join table
     * @param array<string> $on    The matching attributes. ['join_table_attribute' => 'main_table_attribute']
     */
    public function leftJoin(string $table, array $on = []): self
    {
        return $this->join($table, $on, 'LEFT');
    }

    /**
     * Right join table.
     *
     * @param string        $table the join table
     * @param array<string> $on    The matching attributes. ['join_table_attribute' => 'main_table_attribute']
     */
    public function rightJoin(string $table, array $on = []): self
    {
        return $this->join($table, $on, 'RIGHT');
    }

    /**
     * Left outer join table.
     *
     * @param string        $table the join table
     * @param array<string> $on    The matching attributes. ['join_table_attribute' => 'main_table_attribute']
     */
    public function leftOuterJoin(string $table, array $on = []): self
    {
        return $this->join($table, $on, 'LEFT OUTER');
    }

    /**
     * Right outer join table.
     *
     * @param string        $table the join table
     * @param array<string> $on    The matching attributes. ['join_table_attribute' => 'main_table_attribute']
     */
    public function rightOuterJoin(string $table, array $on = []): self
    {
        return $this->join($table, $on, 'RIGHT OUTER');
    }

    /**
     * With alias name condition.
     *
     * @param string   $alias     the alias to be used
     * @param callable $condition The
     */
    public function with(string $alias, callable $condition): self
    {
        $backup = $this->alias;
        $this->alias = $alias;
        $condition->bindTo($this)($this);
        $this->alias = $backup;

        return $this;
    }

    /**
     * Get qualifier attribute name.
     *
     * @param string $attribute the attribute name
     * @param bool   $isTable   whether the $attribute is a table name
     *
     * @return string the qualified name
     */
    public function qualifier(string $attribute, bool $isTable = false): string
    {
        if ($isTable) {
            return $this->database ? "`{$this->database}`.`{$attribute}`" : "`{$attribute}`";
        }

        if ($attribute === '*') {
            return $this->alias === null ? $attribute : "`{$this->alias}`.{$attribute}";
        }

        return $this->alias === null ? "`{$attribute}`" : "`{$this->alias}`.`{$attribute}`";
    }

    /**
     * Select statement.
     *
     * @param array<mixed> $attributes the list of attribute value to be select
     */
    public function select(...$attrbutes): self
    {
        foreach ($attrbutes as $attrbute) {
            $this->selects[] = $this->sqlField($attrbute);
        }

        return $this;
    }

    /**
     * Select raw statement.
     *
     * @param string $sql the raw select statement
     */
    public function selectRaw(string $sql): self
    {
        $this->selects[] = $sql;

        return $this;
    }

    /**
     * Raw condition.
     *
     * @param string       $sql             the raw SQL query statement
     * @param array<mixed> $binds           the bind values for the query statement
     * @param string       $logicalOperator The logical operator. Default: 'AND'.
     */
    public function whereRaw(string $sql, array $binds = [], string $logicalOperator = 'AND'): self
    {
        if ($binds) {
            $this->binds = array_merge($this->binds, $binds);
        }

        return $this->onCondition($sql, $logicalOperator);
    }

    /**
     * Or raw condition.
     *
     * @param string       $sql   the raw SQL query statement
     * @param array<mixed> $binds the bind values for the query statement
     */
    public function orWhereRaw(string $sql, array $binds = []): self
    {
        return $this->whereRaw($sql, $binds, 'OR');
    }

    /**
     * Construct the query conditions.
     *
     * @param callable|string $attribute       the attribute
     * @param mixed           $operator        the comparison operator or the attribute value
     * @param null|mixed      $value           the value for the attribute
     * @param string          $logicalOperator The logical operator. Default: 'AND'.
     */
    public function where(
        string|callable $attribute,
        ?string $operator = '=',
        $value = null,
        string $logicalOperator = 'AND'
    ): self {
        if (!is_string($attribute) && is_callable($attribute)) {
            $this->onCondition('(', $logicalOperator);
            // @var callable $attribute
            $attribute->bindTo($this)($this);
            $this->onCondition(')');

            return $this;
        }

        if ($value === null && $operator != '=') {
            $value = $operator;
            $operator = '=';
        }

        $this->onCondition("{$this->sqlField($attribute)} {$operator} {$this->placeHolder}", $logicalOperator);
        $this->binds[] = $value;

        return $this;
    }

    /**
     * Or condition.
     *
     * @param string|callable $attribute the attribute name
     * @param mixed  $operator  the comparison operator or the attribute value
     * @param mixed  $value     the value for the attribute
     */
    public function orWhere(string|callable $attribute, ?string $operator = '=', $value = null): self
    {
        return $this->where($attribute, $operator, $value, 'OR');
    }

    /**
     * Not condition query.
     *
     * @param string $attribute the attribute name
     * @param mixed  $value
     */
    public function not(string $attribute, $value): self
    {
        return $this->where($attribute, '!=', $value);
    }

    /**
     * Or not condition.
     *
     * @param string $attribute the attribute name
     * @param mixed  $value     the value for the attribute
     */
    public function orNot(string $attribute, $value): self
    {
        return $this->not($attribute, $value, 'OR');
    }

    /**
     * Is null condition.
     *
     * @param string $attribute       the attribute name
     * @param string $logicalOperator The logical operator. Either: 'AND' or 'OR'.
     */
    public function isNull(string $attribute, string $logicalOperator = 'AND'): self
    {
        return $this->onCondition("{$this->sqlField($attribute)} IS NULL", $logicalOperator);
    }

    /**
     * Or is null condition.
     *
     * @param string $attrbute the attribute name
     */
    public function orIsNull(string $attribute): self
    {
        return $this->isNull($attribute, 'OR');
    }

    /**
     * Not null condition.
     *
     * @param string $attribute       the attribute name
     * @param string $logicalOperator The logical operator. Either 'AND' or 'OR'.
     */
    public function notNull(string $attribute, string $logicalOperator = 'AND'): self
    {
        return $this->onCondition("{$this->sqlField($attribute)} IS NOT NULL", $logicalOperator);
    }

    /**
     * Or not null condition.
     *
     * @param string $attribute the attribute name
     */
    public function orNotNull(string $attribute): self
    {
        return $this->notNull($attribute, 'OR');
    }

    /**
     * In condition.
     *
     * @param string       $attribute       the attribute name
     * @param array<mixed> $values          the array of values for the query
     * @param string       $logicalOperator The logical operator. Either 'AND' or 'OR'.
     */
    public function in(string $attribute, array $values, string $logicalOperator = 'AND'): self
    {
        if ($values) {
            $symbols = implode(',', array_fill(0, count($values), $this->placeHolder));
            $this->binds = array_merge($this->binds, $values);

            return $this->onCondition("{$this->sqlField($attribute)} IN ({$symbols})", $logicalOperator);
        }

        return $this;
    }

    /**
     * Or in condition.
     *
     * @param string       $attribute the attribute name
     * @param array<mixed> $values    the array of values for the query
     */
    public function orIn(string $attribute, array $values): self
    {
        return $this->in($attribute, $values, 'OR');
    }

    /**
     * Not in condition.
     *
     * @param string       $attribute       the attribute name
     * @param array<mixed> $values          the array of values for the query
     * @param string       $logicalOperator The logical operator. Either 'AND' or 'OR'.
     */
    public function notIn(string $attribute, array $values, string $logicalOperator = 'AND'): self
    {
        if ($values) {
            $symbols = implode(',', array_fill(0, count($values), $this->placeHolder));
            $this->binds = array_merge($this->binds, $values);

            return $this->onCondition("{$this->sqlField($attribute)} NOT IN ({$symbols})", $logicalOperator);
        }

        return $this;
    }

    /**
     * Or not in condition.
     *
     * @param string       $attribute the attribute name
     * @param array<mixed> $values    the array of values for the query
     */
    public function orNotIn(string $attribute, array $values): self
    {
        return $this->notIn($attribute, $values, 'OR');
    }

    /**
     * Like condition.
     *
     * @param string $attribute       the attribute name
     * @param string $value           the like's value
     * @param bool   $is              the comparison operator
     * @param string $logicalOperator The logical operator. Either 'AND' or 'OR'.
     */
    public function like(string $attribute, string $value, bool $is = true, string $logicalOperator = 'AND'): self
    {
        $this->binds[] = $value;
        $comparison = $is ? '' : 'NOT ';

        return $this->onCondition("{$this->sqlField($attribute)} {$comparison}LIKE {$this->placeHolder}", $logicalOperator);
    }

    /**
     * Or like condition.
     *
     * @param string $attribute the attribute name
     * @param string $value     the like's value
     */
    public function orLike(string $attribute, string $value): self
    {
        return $this->like($attribute, $value, true, 'OR');
    }

    /**
     * Not like condition.
     *
     * @param string $attribute the attribute name
     * @param string $value     the like's value
     */
    public function notLike(string $attribute, string $value): self
    {
        return $this->like($attribute, $value, false);
    }

    /**
     * Or not like condition.
     *
     * @param string $attribute the attribute name
     * @param string $value     the like's value
     */
    public function orNotLike(string $attribute, string $value): self
    {
        return $this->like($attribute, $value, false, 'OR');
    }

    /**
     * Between condition.
     *
     * @param string $attribute       the attribute name
     * @param mixed  $start           the start value
     * @param mixed  $end             the end value
     * @param bool   $is              determine the comparison operator
     * @param string $logicalOperator The logical operator. Either 'AND' or 'OR'
     */
    public function between(string $attribute, $start, $end, bool $is = true, string $logicalOperator = 'AND'): self
    {
        $this->binds[] = $start;
        $this->binds[] = $end;
        $comparison = $is ? '' : 'NOT ';

        return $this->onCondition("{$this->sqlField($attribute)} {$comparison}BETWEEN {$this->placeHolder} AND {$this->placeHolder}", $logicalOperator);
    }

    /**
     * Or between condition.
     *
     * @param string $attribute the attribute name
     * @param mixed  $start     the start value
     * @param mixed  $end       the end value
     */
    public function orBetween(string $attribute, $start, $end): self
    {
        return $this->between($attribute, $start, $end, true, 'OR');
    }

    /**
     * Not between condition.
     *
     * @param string $attribute the attribute name
     * @param mixed  $start     the start value
     * @param mixed  $end       the end value
     */
    public function notBetween(string $attribute, $start, $end): self
    {
        return $this->between($attribute, $start, $end, false);
    }

    /**
     * Or not between condition.
     *
     * @param string $attribute the attribute name
     * @param mixed  $start     the start value
     * @param mixed  $end       the end value
     */
    public function orNotBetween(string $attribute, $start, $end): self
    {
        return $this->between($attribute, $start, $end, false, 'OR');
    }

    /**
     * Between date condition.
     *
     * @param string      $attribute       the attribute name
     * @param null|string $startDate       the start date value
     * @param null|string $endDate         the end date value
     * @param string      $logicalOperator The logical operator. Either 'AND' or 'OR'
     */
    public function betweenDate(
        string $attribute,
        ?string $startDate = null,
        ?string $endDate = null,
        bool $is = true,
        string $logicalOperator = 'AND'
    ): self {
        $attribute = $this->sqlField($attribute);

        if ($startDate && $endDate) {
            $this->binds[] = $startDate;
            $this->binds[] = $endDate;

            return $this->onCondition("{$attribute} >= {$this->placeHolder} AND {$attribute} < {$this->placeHolder}", $logicalOperator);
        }

        if ($startDate && $endDate === null) {
            $this->binds[] = $startDate;

            return $this->onCondition("{$attribute} >= {$this->placeHolder}", $logicalOperator);
        }

        if ($startDate === null && $endDate) {
            $this->binds[] = $endDate;

            return $this->onCondition("{$attribute} < {$this->placeHolder}", $logicalOperator);
        }

        return $this;
    }

    /**
     * Or between date condition.
     *
     * @param string      $attribute the attribute name
     * @param null|string $startDate the start date value
     * @param null|string $endDate   the end date value
     */
    public function orBetweenDate(string $attribute, ?string $startDate = null, ?string $endDate = null): self
    {
        return $this->betweenDate($attribute, $startDate, $endDate, 'OR');
    }

    /**
     * Between date interval condition.
     *
     * @param string $attribute       the attribute name
     * @param string $startDate       the start date value
     * @param int    $interval        the interval days from the start date
     * @param bool   $is              determine the comparison operator
     * @param string $logicalOperator The logical operator. Either 'AND' or 'OR'
     */
    public function betweenDateInterval(
        string $attribute,
        string $startDate,
        int $interval = 7,
        bool $is = true,
        string $logicalOperator = 'AND'
    ): self {
        $this->binds[] = $startDate;
        $this->binds[] = $startDate;

        $attribute = $this->sqlField($attribute);
        return $is
            ? $this->onCondition("{$attribute} >= {$this->placeHolder} AND {$attribute} < {$this->placeHolder} + INTERVAL {$interval} DAY", $logicalOperator)
            : $this->onCondition("{$attribute} < {$this->placeHolder} AND {$attribute} >= {$this->placeHolder} + INTERVAL {$interval} DAY", $logicalOperator);
    }

    /**
     * Or between date interval condition.
     *
     * @param string $attribute the attribute name
     * @param string $startDate the start date value
     * @param int    $interval  The interval days value. Default 7 days.
     */
    public function orBetweenDateInterval(string $attribute, string $startDate, int $interval = 7): self
    {
        return $this->betweenDateInterval($attribute, $startDate, $interval, true, 'OR');
    }

    /**
     * Not between date interval condition.
     *
     * @param string $attribute the attribute name
     * @param string $startDate the start date value
     * @param int    $interval  The interval days value. Default 7 days.
     */
    public function notBetweenDateInterval(string $attribute, string $startDate, int $interval = 7): self
    {
        return $this->betweenDateInterval($attribute, $startDate, $interval, false);
    }

    /**
     * Or not between date interval condition.
     *
     * @param string $attribute the attribute name
     * @param string $startDate the start date value
     * @param int    $interval  The interval days value. Default 7 days.
     */
    public function orNotBetweenDateInterval(string $attribute, string $startDate, int $interval = 7): self
    {
        return $this->betweenDateInterval($attribute, $startDate, $interval, false, 'OR');
    }

    /**
     * Group by statement.
     *
     * Example usages:
     *
     * $this->groupBy('attribute');                 // GROUP BY table.attribute.
     * $this->groupBy('attribute', '!p.attribute'); // GROUP BY table.attribute, p.attribute
     *
     * @param array<mixed>|string $attribute the attribute
     * @param string              $direction the order direction for the attribute
     */
    public function groupBy(...$attributes): self
    {
        foreach ($attributes as $name) {
            $this->groupBys[] = $this->sqlField($name);
        }

        return $this;
    }

    /**
     * Order by statement.
     *
     * Example usages:
     *
     * $this->orderBy('attribute')           // ORDER BY table.attribute ASC
     * $this->orderBy('attribute', 'DESC');  // ORDER by table.attribute DESC
     * $this->orderBy([                      // ORDER BY table.attribute1 ASC, table.attribute2 DESC
     *  'attribute1' => 'ASC',
     *  'attribute2' => 'DESC',
     * ]);
     * $this->orderBy([                      // ORDER BY table.attribute1 ASC, p.attribute2 DESC
     *  'attribute1' => 'ASC',
     *  '!p.attribute2' => 'DESC',
     * ]);
     *
     * @param array<mixed>|string $attribute the attribute
     * @param string              $direction the order direction for the attribute
     */
    public function orderBy($attribute, string $direction = 'ASC'): self
    {
        if (is_array($attribute)) {
            foreach ($attribute as $name => $direction) {
                $direction = strtoupper($direction);
                $this->orderBys[] = "{$this->sqlField($name)} {$direction}";
            }
        } else {
            $direction = strtoupper($direction);
            $this->orderBys[] = "{$this->sqlField($attribute)} {$direction}";
        }

        return $this;
    }

    /**
     * Raw order by query.
     *
     * $this->orderByRaw('COUNT({attribute}) DESC');    // ORDER BY COUNT(table.attribute) DESC
     * $this->orderByRaw('COUNT({attribute}) DESC, {p.attribute} ASC) // // ORDER BY COUNT(table.attribute) DESC, p.attribute ASC
     *
     * @param string $sql the order by SQL statement
     */
    public function orderByRaw(string $sql): self
    {
        $this->orderBys[] = $sql;

        return $this;
    }

    /**
     * Limit statement.
     *
     * @param int      $max    the maximum records to be returned
     * @param null|int $offset the offset value
     */
    public function limit(int $max, ?int $offset = null): self
    {
        $this->limit = $max;
        if ($offset) {
            $this->offset = $offset;
        }

        return $this;
    }

    /**
     * Offset statement.
     *
     * @param int $value the offset value
     */
    public function offset(int $value): self
    {
        $this->offset = $value;

        return $this;
    }

    /**
     * Limit per page statement.
     *
     * @param int $limit       the max records returned per current page
     * @param int $currentPage the current page
     */
    public function limitPerPage(int $limit, int $currentPage): self
    {
        $this->limit($limit);
        $this->offset(--$currentPage * $limit);

        return $this;
    }

    /**
     * Qualifier attributes from raw SQL statement.
     *
     * @param string $sql the SQL statement
     */
    protected function mapQualifier(string $sql): string
    {
        preg_match_all('/\{(.*?)\}/', $sql, $matches);
        if ($matches[1]) {
            $attributes = [];
            foreach ($matches[1] as $key => $attribute) {
                $attr = explode('.', $attribute);
                if (empty($attr[1])) {
                    $attributes[$key] = $this->qualifier($attr[0]);
                } else {
                    $attributes[$key] = $attr[1] === '*' ? "`{$attr[0]}`.*" : "`{$attr[0]}`.`{$attr[1]}`";
                }
            }

            return str_replace($matches[0], $attributes, $sql);
        }

        return $sql;
    }

    /**
     * Convert to SQL field format.
     *
     * @param string $attribute the attribute name
     */
    protected function sqlField(string $attribute): string
    {
        if ($attribute[0] === '!') {
            return ltrim($attribute, '!');
        }

        return $attribute[0] === '{' ? $attribute : "{{$attribute}}";
    }

    /**
     * Append to existing query conditions.
     *
     * @param string      $query    the SQL query statement
     * @param null|string $operator The logical operator. Either: "AND" or "OR".
     */
    protected function onCondition(string $query, ?string $operator = null): self
    {
        if ($operator && $this->conditions && end($this->conditions) != '(') {
            $this->conditions[] = $operator;
        }

        $this->conditions[] = $query;

        return $this;
    }

    /**
     * Build query SQL from the conditions.
     */
    protected function buildSQL(): string
    {
        if ($this->table && $this->returnConditionOnly === false) {
            if ($this->selects) {
                $selects = 'SELECT ' . implode(', ', $this->selects);
            } else {
                $selects = 'SELECT *';
            }

            $from = "FROM {$this->table}";
        } else {
            $selects = null;
            $from = null;
            $this->returnConditionOnly = true;
        }

        $limit = match (true) {
            //$this->returnConditionOnly === true => null,
            $this->limit && $this->offset => "LIMIT {$this->offset}, {$this->limit}",
            $this->limit && $this->offset === null => "LIMIT {$this->limit}",
            default => null,
        };

        return $this->mapQualifier(implode(' ', array_filter([
            $selects,
            $from,
            empty($this->joins) ? null : implode(' ', $this->joins),
            empty($this->conditions) ? null : (($this->returnConditionOnly ? '': 'WHERE ') . implode(' ', $this->conditions)),
            empty($this->groupBys) ? null : 'GROUP BY ' . implode(', ', $this->groupBys),
            // having
            empty($this->orderBys) ? null : 'ORDER BY ' . implode(', ', $this->orderBys),
            $limit,
        ])));
    }

}
