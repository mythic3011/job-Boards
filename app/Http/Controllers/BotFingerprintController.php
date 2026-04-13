<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class BotFingerprintController extends Controller
{
    private const MAX_CLIENT_TIMESTAMP_SKEW_MS = 300000;

    public function store(Request $request, AuditLogger $auditLogger): Response
    {
        $probeContext = Validator::make([
            'probe' => $request->query('probe'),
            'signal' => $request->query('signal'),
        ], [
            'probe' => ['required', 'string', 'in:banned_page'],
            'signal' => ['required', 'string', 'in:page_load,mousemove'],
        ])->validate();

        $data = $request->validate([
            'fp' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/i'],
            'headless' => ['sometimes', 'boolean'],
            'canvas_ok' => ['sometimes', 'boolean'],
            'webgl_vendor' => ['nullable', 'string', 'max:255'],
            'ts' => [
                'nullable',
                'integer',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $serverTimestampMs = now()->getTimestampMs();

                    if (abs($serverTimestampMs - (int) $value) > self::MAX_CLIENT_TIMESTAMP_SKEW_MS) {
                        $fail('The '.$attribute.' field must be within the allowed time window.');
                    }
                },
            ],
        ]);

        $auditLogger->logRequestEvent(
            eventType: 'bot_fingerprint_probe',
            request: $request,
            statusCode: 204,
            targetType: 'security',
            meta: [
                'probe' => $probeContext['probe'],
                'signal' => $probeContext['signal'],
                'fp_sha256' => hash('sha256', strtolower($data['fp'])),
                'headless' => (bool) ($data['headless'] ?? false),
                'canvas_ok' => (bool) ($data['canvas_ok'] ?? false),
                'webgl_vendor' => $data['webgl_vendor'] ?? null,
                'client_ts' => $data['ts'] ?? null,
                'server_ts' => now()->getTimestampMs(),
            ],
        );

        return response()->noContent();
    }
}
