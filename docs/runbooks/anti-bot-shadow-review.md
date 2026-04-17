# Anti-Bot Shadow Review

## Purpose

This runbook defines how to collect review evidence from the shadow-only anti-bot surfaces without turning that evidence into enforcement by accident.

Current rollout boundary:
- `install` is the only enforcing anti-bot surface
- `login` remains shadow-only
- `two_factor` remains shadow-only
- this review flow is evidence-only

## Command

Runtime prerequisite:
- run this command in an application runtime that can reach the configured database backing `audit_logs`
- on local Docker-based setups, prefer the compose-backed Laravel container when the host shell cannot resolve the default PostgreSQL host

JSON export:

```bash
php artisan anti-bot:shadow-review --hours=24 --json
```

Compose-backed example:

```bash
docker compose exec -T laravel.test php artisan anti-bot:shadow-review --hours=24 --json
```

Human-readable summary:

```bash
php artisan anti-bot:shadow-review --hours=24
```

## Input Contract

- `--hours` defines a rolling lookback window over `anti_bot.risk_scored` audit events
- only shadow-mode anti-bot assessments are included
- unrelated auth failures, maintenance denials, or installer enforcement events must not be treated as shadow review evidence

## Output Contract

The JSON report contains:
- `status_summary`
- `sections.login`
- `sections.two_factor`
- `sections.pending_login_flow`
- `sections.pending_state_quality`
- `go_no_go`
- `final_conclusion`

Required interpretation rules:
- `pending_login_expected_but_missing` is an observation signal only
- `malformed` and `expired` pending states remain auth-flow state categories
- report completion does not authorize enforcement rollout

## Review Questions

The output is intended to answer only these questions:
- is login enforcement design justified for further exploration
- does two-factor need a separate policy review
- is pending-state noise too high for credible enforcement design

It must not be used to decide:
- final thresholds
- final cadence
- final limiter design
- final deny logic
- immediate enforcement rollout

## Suggested Review Flow

1. Run the command with a defined lookback window.
2. Save the JSON artifact with the review date and window.
3. Check `status_summary.requests_observed` before drawing conclusions from sparse data.
4. Review `sections.login` and `sections.two_factor` separately.
5. Review `sections.pending_login_flow` and `sections.pending_state_quality` before interpreting suspicious-looking pending signals.
6. Answer the three `go_no_go` questions with the exported evidence.
7. Treat `final_conclusion` only as input to a later enforcement-design discussion.

Review template:
- [Anti-Bot Shadow Review Template](anti-bot-shadow-review-template.md)

Latest recorded artifact:
- [2026-04-11 Shadow Review Artifact](../notes/anti-bot-shadow-review-2026-04-11.md)

## Verification Notes

- For sqlite-safe local regression coverage, run:

```bash
php artisan test tests/Feature/AntiBotShadowModeTest.php tests/Feature/AntiBotShadowMetricsAggregationTest.php tests/Feature/AntiBotShadowMetricsReviewReportTest.php tests/Feature/AntiBotShadowMetricsReviewCommandTest.php
```

- Full default and compose-backed PostgreSQL verification remain separate from this review workflow. Use the normal test verification paths when a change affects runtime contracts beyond the shadow review export path.
