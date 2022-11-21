<?php

namespace Simsoft\ADOdb;

use Simsoft\ADOdb\Builder\ActiveQuery;
use Simsoft\ADOdb\Builder\MysqliQuery;

/**
 * Class Query.
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
        protected mixed $db = null, 
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
     * @param null|DB|string $db The connection string
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
            'mysqli' => new MysqliQuery($this->db, $this->class),
            default => new MysqliQuery($this->db, $this->class),
        };
    }
}
