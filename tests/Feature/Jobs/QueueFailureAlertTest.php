<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ImportSanctionsJob;
use App\Jobs\SendNotificationJob;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class QueueFailureAlertTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function compliance_job_failure_raises_an_operator_alert(): void
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('getName')->andReturn(ImportSanctionsJob::class);
        $job->shouldReceive('getJobId')->andReturn('job-1');
        $job->shouldReceive('getQueue')->andReturn('default');

        event(new JobFailed('redis', $job, new RuntimeException('sanctions feed down')));

        $this->assertDatabaseHas('system_alerts', [
            'source' => 'queue',
            'message' => 'Queue job failed: '.ImportSanctionsJob::class,
        ]);
    }

    #[Test]
    public function non_critical_job_failure_only_logs(): void
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('getName')->andReturn(SendNotificationJob::class);
        $job->shouldReceive('getJobId')->andReturn('job-2');
        $job->shouldReceive('getQueue')->andReturn('default');

        event(new JobFailed('redis', $job, new RuntimeException('mail transport down')));

        $this->assertDatabaseMissing('system_alerts', ['source' => 'queue']);
    }
}
