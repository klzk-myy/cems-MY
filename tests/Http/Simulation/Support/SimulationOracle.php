<?php

namespace Tests\Http\Simulation\Support;

use Illuminate\Database\Connection;

/**
 * SimulationOracle — read-only inspection surface for the black-box HTTP
 * harness.
 *
 * The harness drives the app strictly over HTTP and never touches a model,
 * service, or DB:: directly. Assertions inspect derived DB state through
 * this oracle: a sequence of read-only SELECTs that mirror what the HTTP
 * responses imply. It shares the app's DB connection so it always reads the
 * exact rows the application wrote — no risk of inspecting a stale or
 * parallel database.
 *
 * All methods are SELECT-only; nothing here writes.
 */
class SimulationOracle
{
    public function __construct(private Connection $connection) {}

    /**
     * Run a SELECT and return all rows as associative arrays.
     *
     * @param  list<mixed>  $params
     * @return list<array<string, mixed>>
     */
    public function query(string $sql, array $params = []): array
    {
        $rows = $this->connection->select($sql, $params);
        $out = [];
        foreach ($rows as $row) {
            $out[] = is_object($row) ? (array) $row : $row;
        }

        return $out;
    }

    /**
     * Run a query and return the first scalar column value.
     *
     * @param  list<mixed>  $params
     */
    public function scalar(string $sql, array $params = []): mixed
    {
        return $this->connection->scalar($sql, $params);
    }

    /**
     * Check whether a table exists in the connected database.
     */
    public function tableExists(string $name): bool
    {
        $row = $this->scalar(
            "SELECT name FROM sqlite_master WHERE type='table' AND name = ?",
            [$name]
        );

        return $row !== null && $row !== false;
    }
}
