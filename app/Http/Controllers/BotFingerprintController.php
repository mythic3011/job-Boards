<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BotFingerprintController extends Controller
{
    public function store(Request $request, AuditLogger $auditLogger): Response
    {
        $data = $request->validate([
            'fp'           => 'required|string|max:128',
            'headless'     => 'boolean',
            'canvas_ok'    => 'boolean',
            'webgl_vendor' => 'nullable|string|max:255',
            'ts'           => 'nullable|integer',
        ]);

        $auditLogger->logRequestEvent(
            eventType: 'bot_fingerprint_probe',
            request: $request,
            statusCode: 204,
            targetType: 'security',
            meta: $data,
        );

        return response()->noContent();
    }
}
