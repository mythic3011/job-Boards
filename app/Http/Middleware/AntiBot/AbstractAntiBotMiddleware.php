<?php

namespace App\Http\Middleware\AntiBot;

use App\Services\AntiBot\DecisionRecorder;
use App\Services\AntiBot\RiskAssessment;
use App\Services\AntiBot\RiskContext;
use App\Services\AntiBot\RiskEngine;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractAntiBotMiddleware
{
    public function __construct(
        private readonly RiskEngine $riskEngine,
        private readonly DecisionRecorder $decisionRecorder,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('anti_bot.enabled', true)) {
            return $next($request);
        }

        if (! $this->shouldHandle($request)) {
            return $next($request);
        }

        $surface = $this->surface($request);
        $mode = (string) config("anti_bot.surfaces.{$this->modeKey($request)}.mode", 'shadow');

        if ($mode === 'off') {
            return $next($request);
        }

        $context = RiskContext::fromRequest($request, $surface);
        $assessment = $this->riskEngine->assess($context, $mode);

        if ($mode === 'shadow') {
            $this->decisionRecorder->record($request, $context, $assessment);

            return $next($request);
        }

        return $this->handleEnforced($request, $next, $context, $assessment);
    }

    protected function shouldHandle(Request $request): bool
    {
        return true;
    }

    protected function modeKey(Request $request): string
    {
        return $this->surface($request);
    }

    protected function handleEnforced(Request $request, Closure $next, RiskContext $context, RiskAssessment $assessment): Response
    {
        throw new LogicException(sprintf('Enforced anti-bot mode is not implemented for [%s].', $context->surface));
    }

    abstract protected function surface(Request $request): string;
}
