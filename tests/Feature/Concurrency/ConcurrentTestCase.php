<?php

namespace Tests\Feature\Concurrency;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Base for race-condition regression tests.
 *
 * Tests run on in-memory SQLite, where `lockForUpdate()` is a no-op and real
 * parallel connections cannot interleave inside one process. Races are
 * therefore simulated with **stale model instances**: take an unloaded copy
 * of a row (path B's view), run path A to commit, then execute path B with
 * the stale copy. On the broken code path B writes its stale state over A's
 * committed state; on the fixed code path B re-loads under a lock and either
 * sees A's commit or rejects cleanly.
 *
 * This proves the *code* re-reads state inside its transaction — the same
 * shape that fails under a real (MySQL) concurrent interleaving.
 */
abstract class ConcurrentTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Return an independent (stale-able) copy of the row, detached from any
     * instance the services may already hold.
     *
     * @template T of Model
     *
     * @param  T  $model
     * @return T
     */
    protected function staleCopy(Model $model): Model
    {
        $class = $model::class;

        /** @var T $copy */
        $copy = $class::query()->findOrFail($model->getKey());

        return $copy;
    }

    /**
     * Count queries executed by a callback — guards against N+1 regressions
     * introduced while fixing races.
     *
     * @return array{0: mixed, 1: int} [callback result, query count]
     */
    protected function countedQueries(callable $callback): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $result = $callback();
        } finally {
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        return [$result, $count];
    }
}
