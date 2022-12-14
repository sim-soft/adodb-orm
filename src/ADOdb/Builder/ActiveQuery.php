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
abstract class ActiveQuery
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
        if (empty($this->db)) {
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
        $this->getConditionSQLStatement();

        $this->renderSQLDebug();

        $model = new $activeRecordClass();

        return $this->from($model->_table)->db($model->_dbat)->getDB()->getActiveRecordsClass(
            $activeRecordClass,
            $model->_table,
            $this->getConditionSQLStatement(),
            $this->getBinds()
        );
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
    abstract protected function from(string|array $table): self;

    /** @method Return the built SQL statement. */
    abstract protected function buildSQL(): string;

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

}
