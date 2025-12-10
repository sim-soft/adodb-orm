<?php

namespace Simsoft\ADOdb;

use Exception;
use Iterator;
use Simsoft\ADOdb\Builder\ActiveQuery;

/**
 * Collection class
 */
class Collection implements Iterator
{
    /** @var int|null Total count of current query. */
    protected ?int $totalCount = null;

    /** @var bool Determine is fetch record for specific page only. */
    protected bool $fetchPage = false;

    /** @var int Current page. */
    protected int $page = 0;

    /** @var int Maximum fetched records per page. */
    protected int $size = 100;

    /** @var int Current record pointer position. */
    protected int $position = 0;

    /** @var bool Each strategy. */
    protected bool $each = true;

    /** @var array|null Record batch. */
    protected ?array $batch = null;

    /** @var mixed|null Current record value. */
    private mixed $value = null;

    /** @var mixed|null Current record key. */
    private mixed $key = null;


    /**
     * Constructor
     *
     * @param ActiveQuery $query
     */
    public function __construct(protected ActiveQuery $query)
    {

    }

    /**
     * Set size of records to be fetched in each page.
     *
     * @param int $size Max size of records to be fetched in each page.
     * @return $this
     */
    public function size(int $size): static
    {
        $this->size = $size;
        return $this;
    }

    /**
     * Set current page.
     *
     * @param int $page
     * @param int|null $size
     * @return $this
     */
    public function page(int $page = 1, ?int $size = null): static
    {
        $this->page = $page - 1;
        $this->fetchPage = true;
        $size && $this->size($size);
        return $this;
    }

    /**
     * Get total record count.
     *
     * @param string $field Field to be used for count.
     * @return int
     * @throws Exception
     */
    public function getTotalCount(string $field = '*'): int
    {
        if ($this->totalCount === null) {
            $this->totalCount = $this->query->countSelect($field);
        }
        return $this->totalCount;
    }

    /**
     * Get record count of each page.
     *
     * @throws Exception
     */
    public function getCount(): int
    {
        if ($this->batch === null) {
            $this->next();
        }
        return count($this->batch);
    }

    /**
     * Size of each iteration.
     *
     * @param int $size
     * @return $this
     */
    public function each(int $size = 100): self
    {
        $this->size = $size;
        return $this;
    }

    /**
     * Use batch strategy.
     *
     * @param int $size Size of each batch.
     * @return $this
     */
    public function batch(int $size = 100): self
    {
        $this->size = $size;
        $this->each = false;
        return $this;
    }

    /**
     * Reset
     *
     * @return void
     */
    protected function reset(): void
    {
        $this->page = 0;
        $this->position = 0;
    }

    /**
     * Perform rewind.
     *
     * @throws Exception
     */
    public function rewind(): void
    {
        $this->next();
    }

    /**
     * Get next batch
     *
     * @throws Exception
     */
    public function next(): void
    {
        if ($this->fetchPage && $this->position === $this->size) {
            $this->batch = null;
            return;
        }

        if ($this->batch === null || !$this->each || next($this->batch) === false) {
            $this->batch = $this->query->limitPerPage($this->size, ++$this->page)->findAll();
            $this->position = 0;
            reset($this->batch);
        }

        if ($this->each) {
            $this->value = current($this->batch);
            /*if ($this->query->indexBy !== null) {
                $this->key = key($this->batch);
            } else*/
            if (key($this->batch) !== null) {
                $this->key = $this->key === null ? 0 : $this->key + 1;
            } else {
                $this->key = null;
            }
        } else {
            $this->value = $this->batch;
            $this->key = $this->key === null ? 0 : $this->key + 1;
        }

        ++$this->position;
    }

    /**
     * Determine next record is valid.
     *
     * @return bool
     */
    public function valid(): bool
    {
        return !empty($this->batch);
    }

    /**
     * Get the key of current record.
     *
     * @return mixed
     */
    public function key(): mixed
    {
        return $this->key;
    }

    /**
     * Get current record value.
     *
     * @return mixed
     */
    public function current(): mixed
    {
        return $this->value;
    }
}
