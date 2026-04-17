<?php

namespace Tests\Unit\Services;

use App\Services\CanonicalAuditContract;
use Tests\TestCase;

class CanonicalAuditContractTest extends TestCase
{
    public function test_it_loads_the_shared_versioned_contract_artifact(): void
    {
        $contract = app(CanonicalAuditContract::class);

        $this->assertFileExists(base_path('config/contracts/canonical-audit.v1.json'));
        $this->assertSame('1.4.0', $contract->version());
        $this->assertSame(
            ['source', 'request_id', 'event_type', 'outcome', 'target_identifier'],
            $contract->dedupeIdentityFields(),
        );
        $this->assertTrue($contract->isAdmissibleEvent('audit.auth.verify.success'));
        $this->assertTrue($contract->isAdmissibleEvent('audit.auth.verify.denied'));
        $this->assertTrue($contract->isAdmissibleEvent('audit.auth.locked'));
        $this->assertTrue($contract->isAdmissibleEvent('audit.application.download_cv.denied'));
        $this->assertTrue($contract->isAdmissibleEvent('audit.application.approve.denied'));
        $this->assertTrue($contract->isAdmissibleEvent('audit.application.reject.denied'));
        $this->assertTrue($contract->isAdmissibleEvent('audit.admin.permission.denied'));
        $this->assertTrue($contract->isAdmissibleEvent('setup.completed'));
        $this->assertFalse($contract->isAdmissibleEvent('audit.auth.check.success'));
        $this->assertSame(8, $contract->metadataKeyLimit());
        $this->assertSame(255, $contract->metadataValueLengthLimit());
        $this->assertSame('event_time', $contract->timeFieldMeaning('occurred_at'));
        $this->assertSame('ingestion_time', $contract->timeFieldMeaning('admitted_at'));
        $this->assertSame(
            [
                'event_type',
                'request_id',
                'source',
                'outcome',
                'actor_type',
                'target_type',
                'target_identifier',
                'occurred_at',
                'metadata',
            ],
            $contract->allowedPayloadFields(),
        );
        $this->assertSame(
            [
                'event_type',
                'request_id',
                'source',
                'outcome',
                'actor_type',
                'target_type',
                'target_identifier',
                'occurred_at',
            ],
            $contract->requiredPayloadFields(),
        );
        $this->assertSame(['laravel', 'auth-service'], $contract->normalizedEnum('source'));
        $this->assertSame(['success', 'denied', 'rate_limited', 'logout'], $contract->normalizedEnum('outcome'));
        $this->assertSame('denied', $contract->eventOutcome('audit.auth.verify.denied'));
        $this->assertSame('denied', $contract->eventOutcome('audit.auth.locked'));
        $this->assertSame('denied', $contract->eventOutcome('audit.application.download_cv.denied'));
        $this->assertSame('denied', $contract->eventOutcome('audit.admin.permission.denied'));
        $this->assertSame('success', $contract->eventOutcome('setup.completed'));
    }
}
