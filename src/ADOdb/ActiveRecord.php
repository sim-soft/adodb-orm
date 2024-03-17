<?php

namespace Simsoft\ADOdb;

use Simsoft\ADOdb\Builder\ActiveQuery;
use Simsoft\ADOdb\Traits\Error;
use Throwable;

/**
 * class ActiveRecord.
 *
 * @method find(string $whereOrderBy, mixed $bindarr=false, mixed $pkeysArr=false, array $extra=[])
 */
class ActiveRecord extends \ADODB_Active_Record
{
    use Error;

    /** @var string|array Primary key fields */
    protected mixed $primaryKey = 'id';

    /** @var null|ActiveQuery The query object */
    protected ?ActiveQuery $query = null;

    /** @var array Attributes */
    protected array $attributes = [];

    /** @var array Create alias for attributes */
    protected array $aliasAttributes = [];

    /** @var array Relations */
    protected array $relations = [];

    /** @var array Attributes that cannot be mass assigned. */
    protected array $guarded = [];

    /** @var array Attributes that are mass assignable */
    protected array $fillable = [];

    /** @var array Dirty attributes */
    protected array $dirtyAttributes = [];

    /**
     * @var array Attributes casts. Supported casts int, bool, float, string, array
     *
     * protected array $casts = [
     *  'attribute1' => 'int',
     *  'attribute2' => 'bool',
     *   ...
     * ];
     */
    protected array $casts = [];

    /** @var array All table fields */
    public array $tableFields = [];

    /** @var bool Enable validation. Default: true. */
    public bool $validation = true;

    /** @var bool avoid initiate primary key */
    protected bool $protectPK = true;

    /** @var mixed Previous primary key value. Will be used when $protectPK is false. */
    protected mixed $previousPK = null;

    /**
     * Constructor
     *
     * @param string|bool $table The table name.
     * @param array|bool $pkeyarr The primary key.
     * @param mixed $db The connection obj.
     */
    public function __construct($table = false, $pkeyarr=false, $db=false)
    {
        $this->protectKey();
    }

    /**
     * Implement magic settle.
     */
    public function __set($name, $value)
    {
        if ($this->isNewRecord() && $value !== null) {
            $this->dirtyAttributes[$name] = 1;
        } elseif (!$this->isNewRecord()) {
            if ($this->protectPK === false) {
                if (is_string($this->primaryKey) && $this->primaryKey == $name) {
                    $this->previousPK = $this->{$this->primaryKey};
                } elseif (is_array($this->primaryKey) && in_array($name, $this->primaryKey)) {
                    $this->previousPK[$name] = $this->attributes[$name] ?? null;
                }
            }

            if (empty($this->tableFields[$name])) {
                $this->tableFields[$name] = gettype($value);
            } elseif ($this->{$name} != $value) {
                $this->dirtyAttributes[$name] = 1;
            }
        }

        if (array_key_exists($name, $this->casts)) {
            match($this->casts[$name]) {
                'int', 'integer' => $this->attributes[$name] = (int) $value,
                'bool', 'boolean' => $this->attributes[$name] = (bool) $value,
                'float', 'double', 'real' => $this->attributes[$name] = (float) $value,
                'string', 'binary' => $this->attributes[$name] = (string) $value,
                'array' => $this->attributes[$name] = (array) $value,
            };
        } else {
            $this->attributes[$name] = $value;
        }
    }

    /**
     * Implement magic isset
     */
    public function __isset(string $name)
    {
        return isset($this->attributes[$name])
            || isset($this->relations[$name])
            || isset($this->aliasAttributes[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function __get($name): mixed
    {
        if (array_key_exists($name, $this->aliasAttributes)) {
            $name = $this->aliasAttributes[$name];
        }

        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name];
        } elseif (method_exists($this, $name)) {
            return $this->relations[$name] = $this->$name();
        }

        if (array_key_exists($name, $this->attributes) && array_key_exists($name, $this->casts)) {
            return match($this->casts[$name]) {
                'int', 'integer' => (int) $this->attributes[$name] ?? 0,
                'bool', 'boolean' => (bool) $this->attributes[$name] ?? false,
                'float', 'double', 'real' => (float) $this->attributes[$name] ?? 0.00,
                'string', 'binary' => (string) $this->attributes[$name] ?? '',
                'array' => (array) $this->attributes[$name] ?? [],
            };
        }

        return $this->attributes[$name] ?? null;
    }

    /**
     * Determine is the current model is existed. not new.
     *
     * @return bool
     */
    public function exist(): bool
    {
        return $this->_saved;
    }

    /**
     * Determine is the current model is new record.
     *
     * @return bool
     */
    public function isNewRecord(): bool
    {
        return !$this->exist();
    }

    /**
     * Determine is primary key protected.
     *
     * @return bool
     */
    public function isKeyProtected(): bool
    {
        return $this->protectPK;
    }

    /**
     * Get primary key attribute names.
     *
     * @return array
     */
    public function getPrimaryKeyAttributes(): array
    {
        if (is_array($this->primaryKey)) {
            return $this->primaryKey;
        }

        return (array) $this->primaryKey;
    }

    /**
     * Protected primary key from modification during mass assignment.
     *
     * @param bool $enable Enable primary key protection. Default: true.
     * @return $this
     */
    public function protectKey(bool $enable = true): static
    {
        $this->protectPK = $enable;

        if (empty($this->primaryKey)) {
            return $this;
        }

        if ($this->protectPK) {
            $this->guarded = array_merge($this->guarded, $this->getPrimaryKeyAttributes());
        } else {
            $pks = $this->getPrimaryKeyAttributes();
            foreach($this->guarded as $key => $attribute) {
                if (in_array($attribute, $pks)) {
                    unset($this->guarded[$key]);
                }
            }
        }

        return $this;
    }

    /**
     * Get model primary key value.
     *
     * @return mixed
     */
    public function getKey(): mixed
    {
        if ($this->isNewRecord()) {
            return null;
        }

        if (is_array($this->primaryKey)){
            $keys = new \stdClass();
            foreach($this->primaryKey as $attribute) {
                $keys->{$attribute} = $this->{$attribute};
            }
            return $keys;
        }

        return $this->{$this->primaryKey};
    }

    /**
     * Check if an attribute is dirty/ its valued changed.
     *
     * @param string $attribute The attribute name to be checked
     * @return bool
     */
    public function isDirty(string $attribute): bool
    {
        return array_key_exists($attribute, $this->dirtyAttributes);
    }

    /**
     * Mass assign attributes
     *
     * @param array $attributes The array of attribute => value pairs.
     * @return self
     */
    public function fill(array $attributes): self
    {
        foreach($this->aliasAttributes as $alias => $attribute)
        {
            if (array_key_exists($alias, $attributes)) {
                $attributes[$attribute] = $attributes[$alias];
                unset($attributes[$alias]);
            }
        }

        foreach(array_unique($this->guarded) as $attribute) {
            unset($attributes[$attribute]);
        }

        if ($this->fillable) {
            $attributes = array_intersect_key($attributes, array_flip($this->fillable));
        }

        foreach($attributes as $attribute => $value) {
            $this->{$attribute} = $value;
        }
        return $this;
    }

    /**
     * Performs update operation
     *
     * @return bool
     */
    public function update(): bool
    {
        if ($this->isNewRecord()) {
            return $this->insert();
        }

        $attributes = array_intersect_key($this->attributes, $this->dirtyAttributes);
        if ($attributes) {
            $query = Query::db();
            if (is_array($this->primaryKey)) {
                if ($this->protectPK === false) {
                    foreach($this->primaryKey as $key) {
                        $pk = $this->previousPK[$key] ?? $this->attributes[$key] ?? null;
                        if ($pk !== null) {
                            $query->where($key, $pk);
                        }
                    }
                } else {
                    foreach($this->primaryKey as $key) {
                        $query->where($key, $this->{$key});
                    }
                }
            } else {
                if ($this->protectPK === false && $this->isDirty($this->primaryKey)) {
                    $pk = $this->previousPK;
                } else {
                    $pk = $this->{$this->primaryKey};
                }
                $query->where($this->primaryKey, $pk);
            }

            $status = DB::use($this->_dbat)->update($this->_table, $attributes, $query);
            if ($status) {
                $this->refresh();
            }
            return $status;
        }
        return true;
    }

    /**
     * Update attributes
     *
     * @param array $attributes Attribute => new value pairs.
     * @return bool
     */
    public function updateAttributes(array $attributes): bool
    {
        if ($this->isNewRecord()) {
            return false;
        }

        $status = DB::use($this->_dbat)->update(
            $this->_table,
            $attributes,
            Query::db()->where($this->primaryKey, $this->getKey()),
        );

        if ($status) {
            $this->refresh();
        }

        return $status;
    }

    /**
     * Performs insert operation
     *
     * @return bool
     */
    public function insert(): bool
    {
        $attributes = array_intersect_key($this->attributes, $this->dirtyAttributes);
        if ($attributes) {
            $status = DB::use($this->_dbat)->insert($this->_table, $attributes);
            if ($status) {
                $this->_saved = true;
                $this->{$this->primaryKey} = DB::use($this->_dbat)->insert_Id($this->_table);
                $this->refresh();
            }
            return $status;
        }
        return true;
    }

    /**
     * Refresh the current model.
     *
     * @return void
     */
    public function refresh(): void
    {
        try {
            if (!$this->isNewRecord()) {
                $query = self::query();
                if (is_array($this->primaryKey)) {
                    foreach($this->primaryKey as $attribute) {
                        $query->where($attribute, $this->{$attribute});
                    }
                } else {
                    $query->where($this->primaryKey, $this->{$this->primaryKey});
                }
                $model = $query->findOne();
                if ($model) {
                    $this->attributes = $model->getAttributes();
                }
            }
        } catch (Throwable $exception) {
            debug_print_backtrace();
            error_log($exception->getMessage(), 0);
        }
    }

    /**
     * Performs delete operation.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if ($this->isNewRecord()) {
            return false;
        }

        $query = self::query();
        if (is_array($this->primaryKey)) {
            foreach($this->primaryKey as $attribute) {
                $query->where($attribute, $this->$attribute);
            }
        } else {
            $query->where($this->primaryKey, $this->{$this->primaryKey});
        }
        return DB::use($this->_dbat)->delete($this->_table, $query);
    }

    /**
     * Enable or disable validation.
     *
     * @param bool $enable Enable validation. Default: true.
     * @return $this
     */
    public function validation(bool $enable = true): static
    {
        $this->validation = $enable;
        return $this;
    }

    /**
     * Implement validation.
     *
     * This method should return a bool value, TRUE indicate validation success.
     *
     * @return bool
     */
    public function validate(): bool
    {
        return true;
    }

    /**
     * Utility method to check is an attribute's value is unique within the table.
     *
     * @param string $attribute The attribute's name to be checked.
     * @param mixed $value The attribute's value to be checked.
     * @return bool
     */
    protected function unique(string $attribute, mixed $value): bool
    {
        try {
            $query = self::query()->where($attribute, $value);
            if ($this->exist()) {
                $query->not($this->primaryKey, $this->protectPK ? $this->getKey() : $this->previousPK);
            }
            return empty($query->findOne());

        } catch (Throwable $throwable) {
            $this->addError($throwable->getMessage());
        }
        return false;
    }

    /**
     * Called before a model is saved
     *
     * @return void
     */
    protected function beforeSave(): void
    {

    }

    /**
     * Called after a model is saved
     *
     * @return void
     */
    protected function afterSave(): void
    {

    }

    /**
     * Implement save.
     *
     * @return bool|int
     */
    public function save(): bool|int
    {
        if ($this->validation && !$this->validate()) {
            return false;
        }

        $this->beforeSave();
        if (parent::save()) {
            $this->afterSave();
            return !$this->hasError();
        }

        $this->addError($this->ErrorMsg());
        return false;
    }

    /**
     * Perform transaction.
     *
     * @param callable $callback Callback to perform the insert/ update operation.
     * @return bool
     */
    public function transaction(callable $callback): bool
    {
        $db = DB::use($this->_dbat);
        $status = $db->transaction($callback);
        if ($status === false) {
            $this->addError($db->getErrorMessage());
        }
        return $status;
    }

    /**
     * Perform smart transaction.
     *
     * @param callable $callback Callback to perform the insert/ update operation.
     * @return bool
     */
    public function smartTransaction(callable $callback): bool
    {
        $db = DB::use($this->_dbat);
        $status = $db->smartTransaction($callback);
        if ($status === false) {
            $this->addError($db->getErrorMessage());
        }
        return $status;
    }

    /**
     * Get last insert ID.
     *
     * @return mixed
     */
    public function getLastInsertID(): mixed
    {
        $db = $this->DB();
        return $this->LastInsertID($db, $this->primaryKey);
    }

    /**
     * Get query object.
     *
     * @return ActiveQuery
     */
    public static function query(): ActiveQuery
    {
        return Query::class(get_called_class());
    }

    /**
     * Find by primary key
     *
     * @param mixed $key Record primary key.
     * @return $this|null
     * @throws \Exception
     */
    public function findByPk(mixed $key): ?static
    {
        return self::query()->where($this->primaryKey, $key)->findOne();
    }

    /**
     * Find one
     *
     * @param string|int|null $key Record primary key.
     * @return static|null
     */
    public static function findOne(string|int|null $key = null): ?static
    {
        try {
            return $key === null ? self::query()->findOne() : (new static())->findByPk($key);
        } catch (Throwable $throwable) {
            error_log($throwable->getMessage());
        }
        return null;
    }

    /**
     * Get all attribute values.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Define has one association.
     *
     * @param ActiveQuery $query The associated model.
     * @return ActiveRecord|null
     */
    protected function hasOne(ActiveQuery $query): ?ActiveRecord
    {
        try {
            return $query->first();
        } catch (Throwable $throwable) {
            return null;
        }
    }

    /**
     * Define has multiple/ has many associations.
     *
     * @param ActiveQuery $query The association model.
     * @return array
     */
    protected function hasMultiple(ActiveQuery $query): array
    {
        try {
            //$this->hasMany();
            return $query->findAll();
        } catch (Throwable $throwable) {
            return [];
        }
    }
}
