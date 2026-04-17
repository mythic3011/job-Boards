<?php

namespace App\Services\AntiBot;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DecisionRecorder
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function record(
        Request $request,
        RiskContext $context,
        RiskAssessment $assessment,
        ?string $eventType = null,
        int $statusCode = 200,
        array $meta = [],
    ): bool
    {
        return $this->recordWithEventType(
            request: $request,
            context: $context,
            assessment: $assessment,
            eventType: $eventType ?? (string) config('anti_bot.audit.event_type', 'anti_bot.risk_scored'),
            statusCode: $statusCode,
            meta: $meta,
        );
    }

    public function recordWithEventType(
        Request $request,
        RiskContext $context,
        RiskAssessment $assessment,
        string $eventType,
        int $statusCode = 200,
        array $meta = [],
    ): bool
    {
        try {
            $this->auditLogger->logSecurityEvent(
                eventType: $eventType,
                request: $request,
                userId: $request->user()?->idcode,
                meta: array_merge($context->toAuditMeta(), $assessment->toAuditMeta(), $meta),
                statusCode: $statusCode,
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('anti_bot.audit_recording_failed', [
                'event_type' => $eventType,
                'surface' => $context->surface,
                'request_id' => (string) $request->attributes->get('request_id', 'unknown'),
                'exception_class' => $e::class,
            ]);

            return false;
        }
    }
}
