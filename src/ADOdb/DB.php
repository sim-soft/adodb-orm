<?php

namespace Simsoft\ADOdb;

use Simsoft\ADOdb\Builder\ActiveQuery;

/**
 * Class DB.
 *
 * @method ADORecordSet|bool  execute(string $sql, array|bool $inputarr = false)
 * @method array|false        getAll(string $sql, array|bool $inputarr = false)
 * @method array|false        getArray(string $sql, array|bool $inputarr = false)
 * @method array|false        getRandRow(string $sql, array|bool $inputarr = false)
 * @method array|bool         getCol(string $sql, array|bool $inputarr = false, bool $trim = false)
 * @method array|false        getRow(string $sql, array|bool $inputarr = false)
 * @method array|bool         getAssoc(string $sql, array|bool $inputarr = false)
 * @method mixed              getOne(string $sql, array|bool $inputarr = false)
 * @method ADORecordSet|false selectLimit(string $sql, int $nrows = -1, int $offset = -1, array|bool $inputarr = false, int $sec2cache)
 * @method int                replace(string $table, array $fieldArray, string $keyCol, bool $autoQuote = false, bool $has_autoinc= false)
 * @method bool               updateBlob(string $table, string $column, string $val, mixed $where, string $blobtype)
 * @method array              getActiveRecordsClass(mixed $class, mixed $table, mixed $whereOrderBy = false, mixed $bindarr = false, mixed $primkeyArr = false, array $extra = [], mixed $relations = [])
 * @method array              getActiveRecords(mixed $table, mixed $where = false, mixed $bindarr = false, mixed $primkeyArr = false)
 */
final class DB
{
    /** @var array<mixed> */
    private static array $config = [];

    /** @var array<mixed> */
    private static array $connections = [];

    /**
     * Constructor.
     *
     * @param object $db the database connection object
     */
    public function __construct(protected $db)
    {
    }

    /**
     * Call database object method.
     *
     * @param string       $name      the method name
     * @param array $arguments the arguments to be used
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        if (in_array($name, ['GetInsertSQL', 'GetUpdateSQL'])) {
            $arguments[0] = &$arguments[0];
        }

        /** @var callable $callback */
        $callback = [$this->db, $name];

        $result = call_user_func_array($callback, $arguments);
        if ($result === false) {
            debug_print_backtrace();
            $message = $this->db->errorMsg();
            trigger_error(empty($message) ? "{$name}: query error." : $message, E_USER_ERROR);
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
     */
    public function getDatabaseType(): string
    {
        return $this->db->databaseType;
    }

    /**
     * Check if method exists.
     *
     * @return bool
     */
    public function methodExists(string $method): bool
    {
        return method_exists($this->db, $method);
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

            debug_print_backtrace();
            trigger_error($this->db->errorMsg(), E_USER_ERROR);
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

        if ($this->db->hasFailedTrans()) {
            debug_print_backtrace();
            trigger_error($this->db->errorMsg(), E_USER_ERROR);
        }

        $this->db->completeTrans();

        return true;
    }

    /**
     * Initialize.
     *
     * @param array<mixed> $config The db
     */
    public static function init(array $config): void
    {
        self::$config = $config;
        foreach (self::$config as $name => $config) {
            self::connectTo($name);
        }
    }

    /**
     * Connect to a database connection.
     *
     * @param string $name the database connection name
     */
    public static function connectTo(string $name): void
    {
        try {

            if (!array_key_exists($name, self::$config)) {
                throw new \Exception("DB connection '{$name}' missing config data.");
            }

            extract(self::$config[$name]);

            /** @var string $driver */
            $db = ADONewConnection($driver);
            if ($db === false) {
                throw new \Exception("DB failed to create connection '{$name}'");
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
                self::$connections[$name] = $db;
            } else {
                throw new \Exception("DB connection '{$name}' failed to connect.");
            }
        } catch (\Exception $e) {
            debug_print_backtrace();
            trigger_error($e->getMessage(), E_USER_ERROR);
        }
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
        if (!array_key_exists($name, self::$connections)) {
            self::connectTo($name);
        }

        return new DB(self::$connections[$name]);
    }

    /**
     * Get escaped string
     *
     * @param string $value The value to be escaped.
     * @param bool $stringQuotes Whether return value with open & end single quote. Default: false
     * @return string
     */
    public static function qStr(string $value, bool $stringQuotes = false): string
    {
        if (self::$connections) {
            return $stringQuotes
                    ? current(self::$connections)->qStr($value)
                    : trim(current(self::$connections)->qStr($value),"'");
        }
        return $value;
    }
}
