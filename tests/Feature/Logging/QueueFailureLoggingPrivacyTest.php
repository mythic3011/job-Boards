<?php

namespace Tests\Feature\Logging;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use Psr\Log\AbstractLogger;
use Tests\TestCase;

class QueueFailureLoggingPrivacyTest extends TestCase
{
    public function test_queue_failure_log_does_not_dump_raw_job_payload_or_identifiers(): void
    {
        $logger = new class extends AbstractLogger
        {
            /** @var array<int, array{level: string, message: string, context: array}> */
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => (string) $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };

        Log::swap($logger);

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\SendWelcomeEmail');
        $job->shouldReceive('payload')->andReturn([
            'uuid' => 'job-uuid-123',
            'displayName' => 'App\\Jobs\\SendWelcomeEmail',
            'maxTries' => 3,
            'data' => [
                'commandName' => 'App\\Jobs\\SendWelcomeEmail',
                'email' => 'queue-privacy@example.test',
                'login_id' => 'queue-user',
                'password' => 'Sup3rS3cret!',
            ],
        ]);

        Event::dispatch(new JobFailed(
            'sync',
            $job,
            new \RuntimeException('queue boom'),
        ));

        $record = collect($logger->records)
            ->firstWhere('message', 'Queue job failed');

        $this->assertNotNull($record);
        $this->assertArrayNotHasKey('payload', $record['context']);
        $this->assertSame('job-uuid-123', $record['context']['job_uuid'] ?? null);
        $this->assertSame('App\\Jobs\\SendWelcomeEmail', $record['context']['job_display_name'] ?? null);

        $encodedLogs = json_encode($logger->records);

        $this->assertIsString($encodedLogs);
        $this->assertStringNotContainsString('queue-privacy@example.test', $encodedLogs);
        $this->assertStringNotContainsString('queue-user', $encodedLogs);
        $this->assertStringNotContainsString('Sup3rS3cret!', $encodedLogs);
    }
}
