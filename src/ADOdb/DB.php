<?php

namespace Simsoft\ADOdb;

use ADOConnection;
use Simsoft\ADOdb\Builder\ActiveQuery;
use Throwable;

/**
 * Class DB.
 *
 * @method \ADORecordSet|bool  execute(string $sql, array|bool $inputarr = false)
 * @method \ADORecordSet|bool cacheExecute(int $cacheTimeInSeconds, string|bool $sql=false, array|bool $bindvars=false)
 * @method bool               autoExecute(string $table, array $arrFields, string $mode='INSERT', string|bool $where=false, bool $forceUpdate=false)
 * @method array|false        getAll(string $sql, array|bool $inputarr = false)
 * @method array|false        cacheGetAll(int $cacheSeconds, string|bool $sql=false, array|bool $bindvars=false)
 * @method array|false        getArray(string $sql, array|bool $inputarr = false)
 * @method array|false        getRandRow(string $sql, array|bool $inputarr = false)
 * @method array|bool         getCol(string $sql, array|bool $inputarr = false, bool $trim = false)
 * @method array|bool         cacheGetCol(int $cacheSeconds, string|bool $sql=false, array|bool $bindvars=false, bool $trimString=false)
 * @method array|false        getRow(string $sql, array|bool $inputarr = false)
 * @method array|false        cacheGetRow(int $cacheSeconds, string|bool $sql=false, array|bool $bindvars=false)
 * @method array|bool         getAssoc(string $sql, array|bool $inputarr = false)
 * @method array|false        cacheGetAssoc(int $cacheSeconds, string|bool $sql=false, array|bool $bindvars=false, bool $forceArray=false, bool $first2Cols=false)
 * @method mixed              getOne(string $sql, array|bool $inputarr = false)
 * @method mixed              cacheGetOne(int $cacheSeconds, string|bool $sql=false)
 * @method \ADORecordSet|false selectLimit(string $sql, int $nrows = -1, int $offset = -1, array|bool $inputarr = false, int $sec2cache = 0)
 * @method \ADORecordSet|false cacheSelectLimit(int $cacheSeconds, string $sql, int $rowsToReturn=-1, int $startOffset=-1, array|bool $bindvars=false)
 * @method void               cacheFlush(string|bool $sql=false, array|bool $bindVariables=false)
 * @method int                replace(string $table, array $fieldArray, string $keyCol, bool $autoQuote = false, bool $has_autoinc= false)
 * @method bool               updateBlob(string $table, string $column, string $val, mixed $where, string $blobtype)
 * @method array              getActiveRecordsClass(mixed $class, mixed $table, mixed $whereOrderBy = false, mixed $bindarr = false, mixed $primkeyArr = false, array $extra = [], mixed $relations = [])
 * @method array              getActiveRecords(mixed $table, mixed $where = false, mixed $bindarr = false, mixed $primkeyArr = false)
 * @method string|false       getInsertSql(mixed $recordSet, array $fieldArray, bool $placeholder=false, ?bool $forceType=null)
 * @method string|false       getUpdateSql(obj $result, array $fieldArray, bool $forceUpdate=false, bool $placeHolder=false, ?bool $forceType=null)
 * @method int                genId(string $seqname='adodbseq', int $startID=1)
 * @method int                insert_Id(string $table='', string $column='')
 * @method string             dbDate(float $timestamp)
 */
class DB
{
    /** @var array */
    private static array $config = [];

    /** @var ADOConnection[] */
    private static array $connections = [];

    /**
     * Constructor.
     *
     * @param ADOConnection $db
     */
    public function __construct(protected ADOConnection $db)
    {
    }

    /**
     * Call database object method.
     *
     * @param string $name      the method name
     * @param array $arguments the arguments to be used
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        if (in_array($name, ['GetInsertSQL', 'getInsertSql', 'GetUpdateSQL', 'getUpdateSql'])) {
            $arguments[0] = &$arguments[0];
        }

        $result = call_user_func_array([$this->db, $name], $arguments);
        if ($result === false) {
            debug_print_backtrace();
            $message = $this->db->errorMsg();
            trigger_error(empty($message) ? "$name: query error." : $message, E_USER_ERROR);
        }

        return $result;
    }

    /**
     * Enable debug mode.
     *
     * @param bool $enabled whether to enable debug mode
     */
    public function debug(bool $enabled = true): self
    {
        $this->db->debug = $enabled;

        return $this;
    }

    /**
     * Get database type.
     * @return string
     */
    public function getDatabaseType(): string
    {
        return $this->db->databaseType;
    }

    /**
     * Check if method exists.
     *
     * @param string $method Method name
     * @return bool
     */
    public function methodExists(string $method): bool
    {
        return method_exists($this->db, $method);
    }

    /**
     * Get error message.
     *
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->db->errorMsg();
    }

    /**
     * Performs insert data.
     *
     * @param string $table      the table name
     * @param array  $attributes the attributes => values pair to be inserted
     *
     * @return bool return true on success
     */
    public function insert(string $table, array $attributes = []): bool
    {
        return $this->db->autoExecute($table, $attributes, 'INSERT');
    }

    /**
     * Performs update data.
     *
     * @param string                   $table      the table name to be updated
     * @param array                    $attributes the attributes => values pair to be updated
     * @param ActiveQuery|false|string $conditions The condition for the update. Example: 'id=12' or 'name like "john%"'
     *
     * @return bool return true on success
     */
    public function update(string $table, array $attributes = [], ActiveQuery|false|string $conditions = false): bool
    {
        if ($conditions instanceof ActiveQuery) {
            $conditions = $conditions->getCompleteSQLStatement();
        }

        return $this->db->autoExecute($table, $attributes, 'UPDATE', $conditions);
    }

    /**
     * Perform delete data
     *
     * @param string $table The table name to be deleted
     * @param ActiveQuery|false|string $conditions The condition for to delete. Example: 'id=12' or 'name like "john%"'
     * @return bool
     */
    public function delete(string $table, ActiveQuery|false|string $conditions = false): bool
    {
        if ($conditions instanceof ActiveQuery) {
            return (bool) $this->db->execute('DELETE FROM ' .  $table . ' WHERE ' . $conditions->getConditionSQLStatement(), $conditions->getBinds());
        }

        return false;
    }

    /**
     * Get number of rows affected by UPDATE/DELETE.
     */
    public function affectedRows(): int
    {
        $count = $this->db->affected_rows();
        if ($count === false) {
            debug_print_backtrace();
            trigger_error($this->db->errorMsg(), E_USER_ERROR);
        }

        return $count;
    }

    /**
     * Performs transactions.
     *
     * The callable must return a bool value.
     *
     * DB::use('connection')->transaction(function(){
     *  // inserts or updates
     *
     *  return true;
     * });
     *
     * @param callable $query The callable which contains query for insert, update, delete operations. Must return bool value.
     */
    public function transaction(callable $query): bool
    {
        if ($this->db->beginTrans()) {
            if ($query() === true) {
                $this->db->commitTrans();
                return true;
            }
            $this->db->rollbackTrans();
        }
        return false;
    }

    /**
     * Performs smart transactions.
     *
     * The callable must return a bool value.
     *
     * DB::use('connection')->transaction(function(){
     *  // inserts or updates
     *
     *  return true.
     * });
     *
     * @param callable $query The callable which contains query for insert, update, delete operations. Must return bool value.
     */
    public function smartTransaction(callable $query): bool
    {
        $this->db->startTrans();
        if ($query($this->db) === false) {
            $this->db->failTrans();
        }

        $this->db->completeTrans();
        return true;
    }

    /**
     * Initialize.
     *
     * @param array $config The db
     */
    public static function init(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Connect to a database connection.
     *
     * @param string $name the database connection name
     * @return ADOConnection|null
     */
    public static function connectTo(string $name): ?ADOConnection
    {
        if (array_key_exists($name, self::$connections)) {
            return self::$connections[$name];
        }

        try {
            if (!array_key_exists($name, self::$config)) {
                throw new \Exception("DB connection '$name' missing config data.");
            }

            extract(self::$config[$name]);

            /** @var string $driver */
            $db = ADONewConnection($driver);
            if ($db === false) {
                throw new \Exception("DB failed to create connection '$name'");
            }

            /*
            * @var string $host
            * @var string $user
            * @var string $pass
            * @var string $schema
            */
            $db->pConnect($host, $user, $pass, $schema);
            if ($db->IsConnected()) {
                /** @var string[] $execute */
                if (!empty($execute) && is_array($execute)) {
                    foreach ($execute as $sql) {
                        $db->execute($sql);
                    }
                }

                /**
                 * @var bool $debug
                 */
                if (!empty($debug)) {
                    $db->debug = $debug;
                }

                \ADODB_Active_Record::SetDatabaseAdapter($db, $name);
                return self::$connections[$name] = $db;
            } else {
                throw new \Exception("DB connection '$name' failed to connect.");
            }
        } catch (Throwable $throwable) {
            debug_print_backtrace();
            trigger_error($throwable->getMessage(), E_USER_ERROR);
        }

        return null;
    }

    /**
     * Use a database connection.
     *
     * @param string $name the database connection name
     *
     * @return DB The database connection object
     */
    public static function use(string $name): self
    {
        return new static(static::connectTo($name));
    }

    /**
     * Get connection object.
     *
     * @param string $name The database connection name.
     * @return ADOConnection|null
     */
    public static function getConnection(string $name): ?ADOConnection
    {
        return self::connectTo($name);
    }

    /**
     * Get quoted escaped string
     *
     * @param string $value The value to be escaped.
     * @return string Quoted escaped string
     */
    public static function qStr(string $value): string
    {
        if (self::$connections) {
            return current(self::$connections)->qStr($value);
        }
        return $value;
    }

    /**
     * Get escaped string
     *
     * @param string $value The value to be escaped.
     * @return string Escaped string
     */
    public static function eStr(string $value): string
    {
        if (self::$connections) {
            return current(self::$connections)->addQ($value);
        }
        return $value;
    }
}
