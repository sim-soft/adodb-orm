<?php

namespace Simsoft\ADOdb;

use Simsoft\ADOdb\Builder\ActiveQuery;
use Simsoft\ADOdb\Traits\Error;

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
        parent::__construct($table, $pkeyarr, $db);

        if ($this->protectPK && $this->primaryKey) {
            if (is_array($this->primaryKey)) {
                $this->guarded = array_merge($this->guarded, $this->primaryKey);
            } else {
                $this->guarded[] = $this->primaryKey;
            }
        }
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc }
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

        if (array_key_exists($name, $this->casts)) {
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
     * Determine is the current model is new record.
     *
     * @return bool
     */
    public function isNewRecord(): bool
    {
        return !$this->_saved;
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
                $this->refresh();
                $this->_saved = true;
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
        } catch (\Throwable $exception) {
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
            return true;
        }
        return false;
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
     * @param mixed $key
     * @return $this|null
     * @throws \Exception
     */
    public function findByPk(mixed $key): ?static
    {
        return self::query()->where($this->primaryKey, $key)->findOne();
    }

    /**
     * Get all attribute values.
     */
    public function getAttributes(): array
    {
        return iterator_to_array(call_user_func(function(){
            foreach ($this->GetAttributeNames() as $attribute) {
                yield $attribute => $this->{$attribute};
            }
        }));
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
        } catch (\Throwable $exception) {
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
            return $query->findAll();
        } catch (\Throwable $exception) {
            return [];
        }
    }
}
