<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class BotFingerprintController extends Controller
{
    private const MAX_CLIENT_TIMESTAMP_SKEW_MS = 300000;
    private const MAX_WEBGL_VENDOR_LENGTH = 120;

    public function store(Request $request, AuditLogger $auditLogger): Response
    {
        if (! $request->isJson()) {
            return response()->json(['message' => 'Unsupported media type'], 415);
        }

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
            'webgl_vendor' => ['nullable', 'string', 'max:'.self::MAX_WEBGL_VENDOR_LENGTH],
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
                'headless' => array_key_exists('headless', $data) ? (bool) $data['headless'] : null,
                'canvas_ok' => array_key_exists('canvas_ok', $data) ? (bool) $data['canvas_ok'] : null,
                'webgl_vendor' => $this->normalizeWebglVendor($data['webgl_vendor'] ?? null),
                'client_ts' => $data['ts'] ?? null,
                'server_ts' => now()->getTimestampMs(),
            ],
        );

        return response()->noContent();
    }

    private function normalizeWebglVendor(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($value));
        $normalized = is_string($normalized) ? $normalized : null;

        if ($normalized === null || $normalized === '') {
            return null;
        }

        if (in_array(strtolower($normalized), ['none', 'unavailable'], true)) {
            return null;
        }

        return $normalized;
    }
}
