<?php

namespace Simsoft\ADOdb\Traits;

/**
 * Scenario trait.
 */
trait Scenario
{
    /** @var int|string|null Scenario value. */
    protected static int|string|null $scenario = null;

    /**
     * Set scenario.
     *
     * @param int|string $scenario
     * @return $this
     */
    public function scenario(int|string $scenario): static
    {
        static::$scenario = $scenario;
        return $this;
    }

    /**
     * Get current scenario.
     *
     * @return int|string|null
     */
    public function getScenario(): int|string|null
    {
        return static::$scenario;
    }

    /**
     * Determines is scenario.
     *
     * @param ...$scenarios
     * @return bool
     */
    public function isScenario(...$scenarios): bool
    {
        return in_array(static::$scenario, $scenarios);
    }
}
