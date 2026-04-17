# Anti-Bot Shadow Review Template

Use this template after exporting a shadow review report with:

```bash
php artisan anti-bot:shadow-review --hours=24 --json
```

Or, on compose-backed local setups:

```bash
docker compose exec -T laravel.test php artisan anti-bot:shadow-review --hours=24 --json
```

Do not use this template to approve enforcement rollout. It is evidence-only.

## Review Metadata

- Review date:
- Reviewer:
- Runtime path:
  - host shell
  - compose-backed container
  - other:
- Lookback window:
- JSON artifact path:

## Status Summary

- Requests observed:
- Review scope:
- Shadow-mode only confirmed:
- Final conclusion from report:

## Login Surface

- Requests observed:
- `allow` count/rate:
- `step_up_required` count/rate:
- `deny` count/rate:
- Top triggered signals:
- Threshold-hit summary:
- Notes:

## Two-Factor Surface

- Requests observed:
- `allow` count/rate:
- `step_up_required` count/rate:
- `deny` count/rate:
- Top triggered signals:
- Threshold-hit summary:
- Notes:

## Pending Login Flow

- Requests observed:
- Pending-login keyed limiter hits:
- `pending_login_expected_but_missing` count/rate:
- Decision distribution by pending state:
  - `valid`:
  - `missing`:
  - `malformed`:
  - `expired`:
- Notes:

## Pending State Quality

- `valid` count/rate:
- `missing` count/rate:
- `malformed` count/rate:
- `expired` count/rate:
- Are malformed/expired states mostly standalone auth-flow noise:
- Are malformed/expired states correlated with other suspicious signals:

## Go / No-Go 1

Question:
- Is login enforcement design justified for further exploration?

Report answer:
- 

Evidence:
- 

Reviewer interpretation:
- 

## Go / No-Go 2

Question:
- Does `two_factor` require a separate policy review?

Report answer:
- 

Evidence:
- 

Reviewer interpretation:
- 

## Go / No-Go 3

Question:
- Are malformed or expired pending states primarily auth-flow noise or meaningful abuse indicators?

Report answer:
- 

Evidence:
- 

Reviewer interpretation:
- 

## Decision Boundary Check

Confirm all of the following before closing the review:
- no enforcement thresholds were proposed here
- no cadence values were proposed here
- no limiter design was finalized here
- no deny logic was finalized here
- no rollout approval was inferred from report completion

## Follow-Up Recommendation

Choose one only:
- no further enforcement design work yet
- login enforcement design may be explored next
- two-factor requires a separate review/design slice first
- auth-flow reliability work is needed before enforcement design is credible

## Reviewer Notes

- 
