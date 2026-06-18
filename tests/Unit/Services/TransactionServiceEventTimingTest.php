<?php

namespace Tests\Unit\Services;

use App\Events\TransactionCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TransactionServiceEventTimingTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_created_event_is_dispatched_after_commit(): void
    {
        Event::fake([TransactionCreated::class]);

        $committedWhenFired = null;
        Event::listen(TransactionCreated::class, function () use (&$committedWhenFired) {
            $committedWhenFired = ! DB::transactionLevel();
        });

        $fileContent = file_get_contents(app_path('Services/TransactionService.php'));

        $this->assertStringContainsString(
            'afterCommit',
            $fileContent,
            'TransactionService should use DB::afterCommit() to dispatch events outside the transaction'
        );

        $this->assertStringContainsString(
            'Event::dispatch',
            $fileContent,
            'TransactionService should dispatch events via Event::dispatch inside afterCommit'
        );

        Event::assertNothingDispatched();
    }
}
