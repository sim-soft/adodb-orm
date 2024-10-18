<?php

namespace Simsoft\ADOdb\Builder;

use Simsoft\ADOdb\ActiveRecord;
use Simsoft\ADOdb\DB;

/**
 * Class ActiveQuery.
 *
 * @method ADORecordSet|bool execute()
 * @method array|false       getAll()
 * @method array|bool        getCol()
 * @method array|false       getRow()
 * @method array|bool        getAssoc()
 * @method mixed             getOne()
 */
class ActiveQuery
{
    /** @var array The query bind values. */
    public array $binds = [];

    /** @var null|string The database name */
    protected ?string $database;

    /** @var null|string The table name */
    protected ?string $table = null;

    /** @var bool Is debug mode */
    protected bool $debugMode = false;

    /** @var bool BuildSQL return condition statement only. */
    protected bool $returnConditionOnly = false;

    /** @var null|string Built SQL statement */
    protected ?string $sqlStatement = null;

    /** @var string The SQL statement value placeholder */
    protected string $placeHolder = '?';

    /** @var bool Use complete SQL for the query. */
    protected bool $useCompleteSQL = false;

    /** @var array The select statement */
    public array $selects = [];

    /** @var array The query conditions */
    public array $conditions = [];

    /** @var array The query group by */
    public array $groupBys = [];

    /** @var array The query having */
    public array $having = [];

    /** @var array The query order */
    public array $orderBys = [];

    /** @var array Jointed relationship */
    public array $joins = [];

    /** @var null|string The table alias */
    protected ?string $alias = null;

    /** @var int The limit value */
    protected int $limit = 0;

    /** @var null|int The offset value */
    protected ?int $offset = null;


    /**
     * Constructor.
     *
     * @param null|DB|string $db The connection string
     * @param null|string $class The active record class name
     */
    public function __construct(
        protected DB|string|null $db = null,
        protected ?string $class = null
    ){
        $model = $this->class ? new $this->class(): null;
        if ($model && $this->db === null) {
            $this->db = $model->_dbat;
        }

        if ($model && $this->table === null) {
            $this->from($model->_table);
        }

        if (is_string($this->db)) {
            $this->db = DB::use($this->db);
        }
    }

    /**
     * Get current query SQL.
     */
    public function __toString(): string
    {
        return $this->getSQLStatement();
    }

    /**
     * Call connection methods.
     *
     * @param string $name      the method name
     * @param array  $arguments The arguments for the method. Not using.
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments = [])
    {
        try {
            $this->renderSQLDebug();

            $db = $this->getDB();
            if ($db->methodExists($name)) {
                return $this->useCompleteSQL
                    ? $db->{$name}($this->getCompleteSQLStatement())
                    : $db->{$name}((string) $this, $this->getBinds());
            }

            throw new \Exception(get_called_class() . ": Method {$name} not exist.");
        } catch (\Exception $e) {
            debug_print_backtrace();
            trigger_error($e->getMessage(), E_USER_ERROR);
        }
    }

    /**
     * Enable debug mode.
     *
     * @param bool $enabled Enable debug mode. Default: true.
     */
    public function debug(bool $enabled = true): self
    {
        $this->debugMode = $enabled;

        return $this;
    }

    /**
     * Generate conditions SQL statement only, without SELECT, FROM.
     *
     * @param bool $return set to generate conditions SQL statement
     */
    public function conditionOnly(bool $return = true): self
    {
        $this->returnConditionOnly = $return;

        return $this;
    }

    /**
     * Use complete SQL statement for the query execution.
     *
     * WARNING!!! This feature is not secured from SQL injection.
     *
     * When this feature is enabled. The complete SQL statement will be used for the query execution.
     * No binding values will be passed to the query execution.
     *
     * @param bool $enable Set to enable the feature.
     *
     * @return self
     */
    public function useCompleteSQL(bool $enable = true): self
    {
        $this->useCompleteSQL = $enable;
        return $this;
    }

    /**
     * Get table.
     */
    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * Execute callable method queries.
     *
     * @param mixed $data The input data.
     * @param callable $method The anonymous method to be executed.
     */
    public function filter(mixed $data, callable $method): self
    {
        /** @var callable $method */
        $method->bindTo($this)($data, $this);
        return $this;
    }

    /**
     * Get SQL statement.
     */
    public function getSQLStatement(): string
    {
        if ($this->sqlStatement === null) {
            $this->sqlStatement = $this->buildSQL();
        }

        $limit = match (true) {
            //$this->returnConditionOnly === true => null,
            $this->limit && $this->offset !== null => "LIMIT {$this->offset}, {$this->limit}",
            $this->limit && $this->offset === null => "LIMIT {$this->limit}",
            default => null,
        };

        if ($limit) {
            return $this->sqlStatement . ' ' . $limit;
        }

        return $this->sqlStatement;
    }

    /**
     * Get condition SQL statement, which is without the SELECT and FROM.
     *
     * @return string
     */
    public function getConditionSQLStatement(): string
    {
        $this->returnConditionOnly = true;
        return $this->getSQLStatement();
    }

    /**
     * Get full SQL statement.
     *
     * @return string
     */
    public function getCompleteSQLStatement(): string
    {
        return $this->getBinds() === false
                    ? $this->getSQLStatement()
                    : $this->replaceArray($this->placeHolder, $this->getBinds(), $this->getSQLStatement());
    }

    /**
     * Get all bind values.
     *
     * @return array|bool
     */
    public function getBinds(): array|false
    {
        return empty($this->binds) ? false : $this->binds;
    }

    /**
     * Replace a given value in the string sequentially with an array.
     *
     * @param string $search String to be replaced.
     * @param array $replace Values to replaces
     * @param string $subject The full text.
     * @return string
     */
    public function replaceArray(string $search, array $replace, string $subject): string
    {
        $segments = explode($search, $subject);
        $result = array_shift($segments);
        foreach ($segments as $segment) {
            $value = (array_shift($replace) ?? $search);
            if (!is_numeric($value)) {
                $value = "'{$value}'";
            }
            $result .= $value . $segment;
        }

        return $result;
    }

    /**
     * Use connection.
     *
     * @param string|DB $connection The connection name
     *
     * @return self
     */
    public function db(string|DB $connection): self
    {
        $this->db = $connection;

        return $this;
    }

    /**
     * Get connection object.
     *
     * @return DB
     * @throws \Exception
     */
    public function getDB(): DB
    {
        if ($this->db === null) {
            debug_print_backtrace();
            throw new \Exception(__CLASS__ . ": 'connection' is not set.");
        }

        if (is_string($this->db)) {
            return DB::use($this->db)->debug($this->debugMode);
        }

        if ($this->db instanceof DB) {
            return $this->db->debug($this->debugMode);
        }

        debug_print_backtrace();
        throw new \Exception(__CLASS__ . ": 'connection' is unknown type.");
    }

    /**
     * Set active record class.
     *
     * @param string $class The active record class name.
     * @return self
     */
    public function activeRecordClass(string $class): self
    {
        $this->class = $class;

        return $this;
    }

    /**
     * Aggregate function.
     *
     * @param string $func The aggregate function name.
     * @param string $attribute The attribute name or raw select SQL statement.
     * @param string|null $alias The alias name for the aggregate function value.
     *
     * @return mixed
     * @throws \Exception
     */
    public function aggregate(string $func, string $attribute, ?string $alias = null): mixed
    {
        $alias = $alias ? " AS `{$alias}` " : ' ';

        if ($attribute != '*' && $attribute[0] != '{') {
            $attribute = '{' . $attribute . '}';
        }

        $func = strtoupper($func);

        if (!in_array($func, ['AVG', 'COUNT', 'MAX', 'MIN', 'SUM'])) {
            $attribute = str_ireplace('DISTINCT', '', $attribute);
        }

        $table = $this->class ? $this->from((new $this->class())->_table)->getTable() : $this->getTable();

        $sql = $this->mapQualifier("SELECT {$func}({$attribute}){$alias}FROM {$table}");

        $condition = $this->getConditionSQLStatement();
        if ($condition) {
            $sql .= ' ' . implode(' ', $this->joins);
            $sql .= " WHERE {$condition}";
        }

        $this->renderSQLDebug();

        $this->returnConditionOnly = false;
        $this->sqlStatement = null;
        return $this->getDB()->getOne($sql, $this->getBinds());
    }

    /**
     * AVG function
     *
     * @param string $attribute The attribute name or raw select SQL statement.
     * @param string|null $alias The alias name for the aggregate function value.
     *
     * @return mixed
     * @throws \Exception
     */
    public function avg(string $attribute, ?string $alias = null): mixed
    {
        return $this->aggregate(__FUNCTION__, $attribute, $alias);
    }

    /**
     * COUNT function
     *
     * @param string $attribute The attribute name or raw select SQL statement.
     * @param string|null $alias The alias name for the aggregate function value.
     *
     * @return int
     * @throws \Exception
     */
    public function count(string $attribute = '*', ?string $alias = null): int
    {
        return $this->aggregate(__FUNCTION__, $attribute, $alias);
    }

    /**
     * MAX function.
     *
     * @param string $attribute The attribute name or raw select SQL statement.
     * @param string|null $alias The alias name for the aggregate function value.
     *
     * @return mixed
     * @throws \Exception
     */
    public function max(string $attribute, ?string $alias = null): mixed
    {
        return $this->aggregate(__FUNCTION__, $attribute, $alias);
    }

    /**
     * MIN function
     *
     * @param string $attribute The attribute name or raw select SQL statement.
     * @param string|null $alias The alias name for the aggregate function value.
     *
     * @return mixed
     * @throws \Exception
     */
    public function min(string $attribute, ?string $alias = null): mixed
    {
        return $this->aggregate(__FUNCTION__, $attribute, $alias);
    }

    /**
     * SUM function
     *
     * @param string $attribute The attribute name or raw select SQL statement.
     * @param string|null $alias The alias name for the aggregate function value.
     *
     * @return mixed
     * @throws \Exception
     */
    public function sum(string $attribute, ?string $alias = null): mixed
    {
        return $this->aggregate(__FUNCTION__, $attribute, $alias);
    }

    /**
     * Find all active records
     *
     * @return array
     * @throws \Exception
     */
    public function find(): array
    {
        return $this->findAll();
    }

    /**
     * Find all active records.
     *
     * @return array
     * @throws \Exception
     */
    public function findAll(): array
    {
        return $this->class === null
                ? $this->getAll()
                : $this->getActiveRecords($this->class);
    }

    /**
     * Find one active record.
     *
     * @return null|ActiveRecord|array
     * @throws \Exception
     */
    public function first(): mixed
    {
        return $this->findOne();
    }

    /**
     * Find one active record.
     *
     * @return null|ActiveRecord|array
     * @throws \Exception
     */
    public function findOne(): mixed
    {
        $result = $this->limit(1)->findAll();
        return $result ? $result[0] : null;
    }

    /**
     * Update all
     *
     * @param array $attributes New values for the attribute => value pairs to be saved.
     *
     * @return bool
     * @throws \Exception
     */
    public function updateAll(array $attributes): bool
    {
        $this->getConditionSQLStatement();

        $this->renderSQLDebug();

        if ($this->class) {
            $model = new $this->class();
            return $this->from($model->_table)->db($model->_dbat)->getDB()
                    ->update($model->_table, $attributes, $this);
        }

        return $this->getDB()->update(trim($this->table, '`'), $attributes, $this);
    }

    /**
     * Get results in active records.
     *
     * Should provide the Active record class name to the constructor before use.
     *
     * @param string $activeRecordClass the active record model class
     * @return array
     * @throws \Exception
     */
    public function getActiveRecords(string $activeRecordClass): array
    {
        $this->renderSQLDebug();

        $model = new $activeRecordClass();
        $rows = $this->from($model->_table)
            ->db($model->_dbat)->getAll();

        $results = [];
        if ($rows) {
            foreach($rows as $row) {
                /** @var ActiveRecord $model */
                $model = new $activeRecordClass();
                $model->_saved = true;

                $protectedKey = $model->isKeyProtected();
                if ($protectedKey) {
                    $model->protectKey(false);
                }

                $model->fill(array_filter($row, function($key) {
                    return is_string($key);
                }, ARRAY_FILTER_USE_KEY));

                foreach($model->getPrimaryKeyAttributes() as $attribute) {
                    $model->$attribute = $row[$attribute] ?? null;
                }

                $model->protectKey($protectedKey);
                $results[] = $model;
            }
        }

        return $results;
    }

    /**
     * Rendering full SQL statement if it is debug mode.
     *
     * @return void
     */
    protected function renderSQLDebug(): void
    {
        if ($this->debugMode) {
            echo get_called_class() . ': "' . $this->getCompleteSQLStatement() . '" ';
            echo '<== Bind values: ';
            var_dump($this->getBinds());
        }
    }

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
            $properties = ['selects', 'conditions', 'groupBys', 'having', 'orderBys', 'joins', 'binds'];
            foreach ($properties as $property) {
                if (!empty($query->{$property})) {
                    if (in_array($property, ['conditions', 'having']) && !empty($this->{$property})) {
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
     * Prepend 'OR' to the query.
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
        return !(empty($this->conditions) && empty($this->groupBys) && empty($this->having) && empty($this->orderBys));
    }

    /**
     * Set from table for the query.
     *
     * Example usage:
     * $this->from('tableName');                    // FROM tableName
     * $this->from('tableName t')                   // FROM tableName AS t
     * $this->from('tableName AS t')                // FROM tableName AS t
     * $this->from(['t' => 'SELECT * FROM ..'])     // FROM (SELECT * FROM ...) AS t  sub query
     *
     * @param string|array $table the table name
     */
    public function from(string|array $table): self
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
     * @param string|array  $table The table name
     * @param array<string> $on    The matching attributes. ['join_table_attribute' => 'main_table_attribute']
     * @param string        $type  Join type. LEFT, RIGHT, INNER, OUTER, etc
     */
    public function join(string|array $table, array $on = [], string $type = 'INNER'): self
    {
        if (is_array($table)) {
            $alias = array_key_first($table);
            $subQuery = current($table);
            $table = "({$subQuery}) AS {$alias}";
        } else {
            $expressions = explode(' ', trim($table));
            $table = $expressions[0];
            $alias = end($expressions);
        }

        $join = $type ? strtoupper($type) . ' JOIN' : 'JOIN';

        $foreignKey = array_key_first($on);
        $localKey = current($on);

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
     * @param string|array  $table the join table
     * @param array<string> $on    The matching attributes. ['join_table_attribute' => 'main_table_attribute']
     */
    public function crossJoin(string|array $table, array $on = []): self
    {
        return $this->join($table, $on, 'CROSS');
    }

    /**
     * Left join table.
     *
     * @param string|array  $table the join table
     * @param array<string> $on    The matching attributes. ['join_table_attribute' => 'main_table_attribute']
     */
    public function leftJoin(string|array $table, array $on = []): self
    {
        return $this->join($table, $on, 'LEFT');
    }

    /**
     * Right join table.
     *
     * @param string|array  $table the join table
     * @param array<string> $on    The matching attributes. ['join_table_attribute' => 'main_table_attribute']
     */
    public function rightJoin(string|array $table, array $on = []): self
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
     * @param array $attributes the list of attribute value to be select
     */
    public function select(...$attributes): self
    {
        foreach ($attributes as $attribute) {
            $this->selects[] = $this->sqlField($attribute);
        }

        return $this;
    }

    /**
     * Select raw statement.
     *
     * @param string $sql the raw select statement
     * @return $this
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
     * @param array        $binds           the bind values for the query statement
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
     * @param array        $binds the bind values for the query statement
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
     * @param mixed           $value           the value for the attribute
     * @param string          $logicalOperator The logical operator. Default: 'AND'.
     */
    public function where(
        string|callable $attribute,
        mixed $operator = '=',
        mixed $value = null,
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
    public function orWhere(string|callable $attribute, ?string $operator = '=', mixed $value = null): self
    {
        return $this->where($attribute, $operator, $value, 'OR');
    }

    /**
     * Not condition query.
     *
     * @param string $attribute the attribute name
     * @param mixed  $value
     * @param string $logicalOperator The logical operator. Default: 'AND'.
     * @return $this
     */
    public function not(string $attribute, mixed $value, string $logicalOperator = 'AND'): self
    {
        return $this->where($attribute, '!=', $value, $logicalOperator);
    }

    /**
     * Or not condition.
     *
     * @param string $attribute the attribute name
     * @param mixed  $value     the value for the attribute
     */
    public function orNot(string $attribute, mixed $value): self
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
     * @param string $attribute the attribute name
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
     * @param array        $values          the array of values for the query
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
     * @param array        $values    the array of values for the query
     */
    public function orIn(string $attribute, array $values): self
    {
        return $this->in($attribute, $values, 'OR');
    }

    /**
     * Not in condition.
     *
     * @param string       $attribute       the attribute name
     * @param array        $values          the array of values for the query
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
     * @param array        $values    the array of values for the query
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
    public function between(string $attribute, mixed $start, mixed $end, bool $is = true, string $logicalOperator = 'AND'): self
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
    public function orBetween(string $attribute, mixed $start, mixed $end): self
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
    public function notBetween(string $attribute, mixed $start, mixed $end): self
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
    public function orNotBetween(string $attribute, mixed $start, mixed $end): self
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

        if ($is) {
            if ($startDate && $endDate) {
                $this->binds[] = $startDate;
                $this->binds[] = $endDate;

                return $this->onCondition("{$attribute} >= {$this->placeHolder} AND {$attribute} <= {$this->placeHolder}", $logicalOperator);
            }

            if ($startDate && $endDate === null) {
                $this->binds[] = $startDate;

                return $this->onCondition("{$attribute} >= {$this->placeHolder}", $logicalOperator);
            }

            if ($startDate === null && $endDate) {
                $this->binds[] = $endDate;

                return $this->onCondition("{$attribute} <= {$this->placeHolder}", $logicalOperator);
            }
        } else {
            if ($startDate && $endDate) {
                $this->binds[] = $startDate;
                $this->binds[] = $endDate;

                return $this->onCondition("{$attribute} < {$this->placeHolder} AND {$attribute} >= {$this->placeHolder}", $logicalOperator);
            }

            if ($startDate && $endDate === null) {
                $this->binds[] = $startDate;

                return $this->onCondition("{$attribute} < {$this->placeHolder}", $logicalOperator);
            }

            if ($startDate === null && $endDate) {
                $this->binds[] = $endDate;

                return $this->onCondition("{$attribute} > {$this->placeHolder}", $logicalOperator);
            }
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
        return $this->betweenDate($attribute, $startDate, $endDate, true, 'OR');
    }

    /**
     * Not between date condition.
     *
     * @param string      $attribute the attribute name
     * @param null|string $startDate the start date value
     * @param null|string $endDate   the end date value
     */
    public function notBetweenDate(string $attribute, ?string $startDate = null, ?string $endDate = null): self
    {
        return $this->betweenDate($attribute, $startDate, $endDate, false);
    }

    /**
     * Or not between date condition.
     *
     * @param string      $attribute the attribute name
     * @param null|string $startDate the start date value
     * @param null|string $endDate   the end date value
     */
    public function orNotBetweenDate(string $attribute, ?string $startDate = null, ?string $endDate = null): self
    {
        return $this->betweenDate($attribute, $startDate, $endDate, false, 'OR');
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
     * Implement exists condition.
     *
     * @param ActiveQuery $query The query object.
     * @param bool $is Is exists condition. Default: true.
     * @param string $logicalOperator The logical operator. Either 'AND' or 'OR'
     * @return $this
     */
    public function exists(ActiveQuery $query, bool $is = true, string $logicalOperator = 'AND'): static
    {
        $this->onCondition(($is ? '': 'NOT ') . 'EXISTS (', $logicalOperator);

        $this->onCondition($query, '');
        $binds = $query->getBinds();
        if ($binds) {
            $this->binds = [...$this->binds, ...$binds];
        }

        return $this->onCondition(')');
    }

    /**
     * The not exists condition.
     *
     * @param ActiveQuery $query The query object.
     * @return $this
     */
    public function notExists(ActiveQuery $query): static
    {
        return $this->exists($query, false);
    }

    /**
     * The or exists condition.
     *
     * @param ActiveQuery $query The query object.
     * @return $this
     */
    public function orExists(ActiveQuery $query): static
    {
        return $this->exists($query, logicalOperator: 'OR');
    }

    /**
     * The or not exists condition.
     *
     * @param ActiveQuery $query The query object.
     * @return $this
     */
    public function orNotExists(ActiveQuery $query): static
    {
        return $this->exists($query, false, 'OR');
    }

    /**
     * Group by statement.
     *
     * Example usages:
     *
     * $this->groupBy('attribute');                 // GROUP BY table.attribute.
     * $this->groupBy('attribute', '!p.attribute'); // GROUP BY table.attribute, p.attribute
     *
     * @param array $attributes the attribute
     * @return $this
     */
    public function groupBy(...$attributes): self
    {
        foreach ($attributes as $name) {
            $this->groupBys[] = $this->sqlField($name);
        }

        return $this;
    }

    /**
     * Having clause
     *
     * @param string $attribute The attribute
     * @param string|null $operator The comparison operator or the attribute value
     * @param mixed|null $value The value for the attribute
     * @return $this
     */
    public function having(string $attribute, ?string $operator = '=', mixed $value = null): self
    {
        if ($value === null && $operator != '=') {
            $value = $operator;
            $operator = '=';
        }

        $this->having[] = "{$this->sqlField($attribute)} {$operator} {$this->placeHolder}";
        $this->binds[] = $value;
        return $this;
    }

    /**
     * Having raw statement
     *
     * @param string $sql The raw having statement
     * @return $this
     */
    public function havingRaw(string $sql): self
    {
        $this->having[] = $sql;
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
     * @param string|array  $attribute the attribute
     * @param string        $direction the order direction for the attribute
     * @return $this
     */
    public function orderBy(string|array $attribute, string $direction = 'ASC'): self
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
     * @return $this
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

        return $this->mapQualifier(implode(' ', array_filter([
            $selects,
            $from,
            empty($this->joins) || $this->returnConditionOnly ? null : implode(' ', $this->joins),
            empty($this->conditions) ? null : (($this->returnConditionOnly ? '': 'WHERE ') . implode(' ', $this->conditions)),
            empty($this->groupBys) ? null : 'GROUP BY ' . implode(', ', $this->groupBys),
            empty($this->having) ? null : 'HAVING ' . implode(', ', $this->having),
            empty($this->orderBys) ? null : 'ORDER BY ' . implode(', ', $this->orderBys),
        ])));
    }
}
