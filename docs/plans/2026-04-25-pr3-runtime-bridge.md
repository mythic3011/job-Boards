# PR3 Runtime Bridge Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Turn the new `./install.sh lab|demo|production` contract into a real runtime path by wiring PR2A bootstrap outputs into the existing deployment flow without rewriting the whole deployment stack.

**Architecture:** PR3 keeps host-side bootstrap as the source of truth for generated runtime artifacts, then routes runtime apply through the split app-plane and obs-plane bootstrap entrypoints instead of the convenience `compose.yaml` path. The bridge remains compatibility-first: bootstrap emits canonical values, legacy consumers keep working through generated env/runtime files, and destructive behavior stays isolated behind `reset-demo`.

**Tech Stack:** Bash, Python 3, Docker Compose, Laravel shell contract tests, PHPUnit feature tests

---

## PR3 Scope

PR3 implements:
- real runtime behavior for `./install.sh lab|demo|production`
- bootstrap output consumption for runtime scripts and Compose preload
- split runtime apply via `ops/bootstrap/bootstrap-app.sh` and `ops/bootstrap/bootstrap-obs.sh`
- explicit non-destructive `demo` semantics
- tests proving the new commands use the bridge instead of `compose.yaml`

PR3 does **not** implement:
- `.env.example` shrink
- broad template cleanup across every service
- a new generic Compose init service
- proof from a clean machine
- removal of all legacy env consumers in one shot

## Fixed PR3 Decisions

- Host-side bootstrap remains responsible for generating runtime files before any Compose apply.
- `compat.shell.env` becomes the shell/bootstrap compatibility handoff for PR3 runtime actions.
- `pr2a.generated.json` remains bootstrap-internal persisted state; runtime scripts do not parse JSON directly.
- `install.sh lab|demo|production` stop using PR2A prepare-only behavior and start a real runtime path in PR3.
- `demo` is non-destructive. It may seed demo-oriented state only when the install flow explicitly supports that without `migrate:fresh`.
- `reset-demo` remains the only destructive demo reset path.
- Runtime apply moves off `compose.yaml` and onto split app/obs bootstrap entrypoints.
- Existing Compose readiness conditions in `compose.app.yml` and `compose.obs.yml` are reused; PR3 does not redesign service gating.

## Multi-Agent Split

### Controller

Own:
- overall sequencing
- final contract decisions
- merge/review across all workers
- Task 3 review gate if demo semantics need application-level install changes

Immediate controller task:
- land a small first commit that documents the PR3 contract update in this plan and freezes the runtime bridge decisions before implementation starts

### Agent 1: Bootstrap bridge loader

Own:
- `bootstrap-env.sh`
- `ops/lib/common.sh`
- any new bootstrap env loader helper under `ops/lib/`

Responsibilities:
- load `compat.shell.env` safely for runtime consumers
- keep precedence rules explicit
- preserve no-rotation secret behavior
- ensure generated paths derive from `STATE_DIR`

### Agent 2: Runtime orchestration

Own:
- `install.sh`
- `ops/bootstrap/bootstrap-app.sh`
- `ops/app/05-compose-up.sh`

Responsibilities:
- replace PR2A prepare-only runtime stop for `lab|demo|production`
- route runtime apply to split bootstrap entrypoints
- keep `reset-demo` destructive and explicit only
- keep legacy command behavior explicit and non-ambiguous

### Agent 3: Obs/runtime integration + tests/docs

Own:
- `ops/bootstrap/bootstrap-obs.sh`
- new/updated shell contract tests
- concise operator docs for PR3 behavior

Responsibilities:
- ensure obs apply path consumes bridge outputs correctly
- verify install commands no longer depend on `compose.yaml`
- document that PR3 still uses compatibility outputs and does not yet remove old env consumers

## Execution Mode

Use Subagent-Driven sequential execution.

Rules:
- no parallel runtime edits
- Controller reviews after each task
- a later task may not start until the previous task's targeted tests pass
- Agent 2 owns demo semantics if application install code must change
- Agent 3 owns obs integration, regression tests, and docs only

## Task 1: Add a shared runtime bridge loader

**Files:**
- Modify: `bootstrap-env.sh`
- Modify: `ops/lib/common.sh`
- Create: `ops/lib/bootstrap-runtime.sh` (only if a dedicated helper reduces duplication)
- Test: `tests/Feature/BootstrapRuntimeBridgeShellContractTest.php`

**Step 1: Write failing tests for runtime bridge loading**

Cover:
- `compat.shell.env` is loaded for runtime shell consumers
- `STATE_DIR` relative override resolves from repo root
- arbitrary host shell variables do not override protected canonical values unless explicitly allowed
- `pr2a.generated.json` stays bootstrap-internal

**Step 2: Run targeted tests and confirm failure**

Run:

```bash
php artisan test tests/Feature/BootstrapRuntimeBridgeShellContractTest.php -v
```

Expected:
- missing bridge loader behavior

**Step 3: Implement minimal loader**

Implementation target:
- add a single runtime bridge loader function that sources `runtime/compat.shell.env` before compose/runtime actions
- call it from `bt_preload_compose_env()` or a new wrapper immediately before existing root `.env` / `obs.generated.env` preload logic
- keep precedence explicit:
  1. accepted injected bootstrap values already materialized by bootstrap
  2. `compat.shell.env`
  3. `.env`
  4. `obs.generated.env`

**Step 4: Verify targeted tests pass**

Run:

```bash
php artisan test tests/Feature/BootstrapRuntimeBridgeShellContractTest.php -v
```

**Step 5: Commit**

```bash
git add bootstrap-env.sh ops/lib/common.sh ops/lib/bootstrap-runtime.sh tests/Feature/BootstrapRuntimeBridgeShellContractTest.php
git commit -m "feat(bootstrap): load runtime bridge env for deploy flows"
```

## Task 2: Route install runtime actions through split bootstrap entrypoints

**Files:**
- Modify: `install.sh`
- Modify: `ops/bootstrap/bootstrap-app.sh`
- Modify: `ops/app/05-compose-up.sh`
- Test: `tests/Feature/InstallPr3RuntimeShellContractsTest.php`

**Step 1: Write failing install/runtime contract tests**

Cover:
- `./install.sh lab` runs real runtime flow and no longer stops after PR2A guidance
- runtime path calls split app bootstrap instead of `compose.yaml` convenience path
- `./install.sh production` uses the same bridge but preserves production validation behavior
- `./install.sh reset-demo` remains the only destructive intent

**Step 2: Run targeted tests and confirm failure**

Run:

```bash
php artisan test tests/Feature/InstallPr3RuntimeShellContractsTest.php -v
```

Expected:
- current PR2A stop-after-prepare behavior still present

**Step 3: Implement runtime delegation**

Implementation target:
- replace `run_pr2a_prepare_only()` usage for `lab|demo|production` with:
  1. `./bootstrap-env.sh prepare <mode>`
  2. load bridge outputs
  3. call `ops/bootstrap/bootstrap-app.sh apply`
  4. call `ops/bootstrap/bootstrap-obs.sh apply`
  5. run verification or summary as appropriate
- remove direct runtime dependence on `compose.yaml` for those new commands
- keep legacy commands explicit:
  - old commands may remain on existing legacy flow for now
  - new commands must use the new bridge path

**Step 4: Add `bootstrap-app.sh` prepare/apply separation only where needed**

Implementation target:
- if `bootstrap-app.sh` still embeds prep inside `apply`, extract the minimum helper needed so install/runtime can call a predictable app apply path after bridge preload
- do not redesign nginx SSL logic in this task

**Step 5: Verify targeted tests pass**

Run:

```bash
php artisan test tests/Feature/InstallPr3RuntimeShellContractsTest.php -v
```

**Step 6: Commit**

```bash
git add install.sh ops/bootstrap/bootstrap-app.sh ops/app/05-compose-up.sh tests/Feature/InstallPr3RuntimeShellContractsTest.php
git commit -m "feat(install): route new deploy modes through bootstrap runtime bridge"
```

## Task 3: Make `demo` a real non-destructive deploy mode

**Files:**
- Modify: `install.sh`
- Inspect/modify as needed: `app/Console/Commands/HeadlessInstall.php`
- Inspect/modify as needed: `app/Services/InstallService.php`
- Test: `tests/Feature/InstallDemoModeContractTest.php`

**Step 1: Write failing tests for demo semantics**

Cover:
- `./install.sh demo` does not call `migrate:fresh`
- `./install.sh demo` does not alias destructive reset behavior
- first-run demo install may seed demo state only through explicit non-destructive install support
- `./install.sh reset-demo` remains the only path allowed to wipe data

**Step 2: Run targeted tests and confirm failure**

Run:

```bash
php artisan test tests/Feature/InstallDemoModeContractTest.php -v
```

**Step 3: Implement minimal demo behavior**

Implementation target:
- define demo mode as:
  - bootstrap prepare using demo profile defaults
  - non-destructive app deploy path
  - optional demo seeding only if the application install state supports it without reset
- if the current application cannot seed demo data safely without destructive reset, PR3 must fail clearly with guidance instead of silently resetting

**Step 4: Verify targeted tests pass**

Run:

```bash
php artisan test tests/Feature/InstallDemoModeContractTest.php -v
```

**Step 5: Commit**

```bash
git add install.sh app/Console/Commands/HeadlessInstall.php app/Services/InstallService.php tests/Feature/InstallDemoModeContractTest.php
git commit -m "feat(install): make demo deployment non-destructive"
```

## Task 4: Align obs/app runtime consumption on the bridge

**Files:**
- Modify: `ops/bootstrap/bootstrap-obs.sh`
- Modify: `ops/bootstrap/bootstrap-app.sh`
- Modify: `ops/lib/common.sh`
- Test: `tests/Feature/BootstrapApplyBridgeContractTest.php`

**Step 1: Write failing tests for split apply consumption**

Cover:
- app apply and obs apply both see the same canonical `STATE_DIR`
- generated runtime files are consumed from derived paths, not ad hoc overrides
- `bt_compose()` preload uses bridge outputs and still loads `obs.generated.env`
- no unresolved `${VAR}` placeholders remain in generated bind-mounted paths

**Step 2: Run targeted tests and confirm failure**

Run:

```bash
php artisan test tests/Feature/BootstrapApplyBridgeContractTest.php -v
```

**Step 3: Implement minimal obs/app bridge alignment**

Implementation target:
- make app apply and obs apply consume the same bridge-loaded runtime environment
- preserve current rendered path differences where they are already a live contract
- do not normalize path layout in PR3 unless a single small migration is unavoidable

**Step 4: Verify targeted tests pass**

Run:

```bash
php artisan test tests/Feature/BootstrapApplyBridgeContractTest.php -v
```

**Step 5: Commit**

```bash
git add ops/bootstrap/bootstrap-obs.sh ops/bootstrap/bootstrap-app.sh ops/lib/common.sh tests/Feature/BootstrapApplyBridgeContractTest.php
git commit -m "feat(bootstrap): align split apply paths with runtime bridge"
```

## Task 5: Regression tests and concise docs

**Files:**
- Modify: `README.md`
- Modify: `docs/runbooks/application-env-config-cleanup.md` (if this remains the active contract doc)
- Modify: `tests/Feature/BootstrapPr2aContractTest.php`
- Modify: `tests/Feature/InstallPr2aShellContractsTest.php`
- Modify: `tests/Feature/InstallShellContractsTest.php`
- Modify: `tests/Feature/BootstrapEnvShellContractsTest.php`

**Step 1: Update tests from PR2A stop-only to PR3 runtime expectations**

Cover:
- PR2A prepare contract still validates
- PR3 commands now continue into runtime flow
- legacy allowlist behavior stays explicit
- `compose.yaml` is no longer the primary runtime apply path for new commands

**Step 2: Run focused regression suite**

Run:

```bash
php artisan test \
  tests/Feature/BootstrapPr2aContractTest.php \
  tests/Feature/InstallPr2aShellContractsTest.php \
  tests/Feature/BootstrapEnvShellContractsTest.php \
  tests/Feature/InstallShellContractsTest.php \
  tests/Feature/BootstrapRuntimeBridgeShellContractTest.php \
  tests/Feature/InstallPr3RuntimeShellContractsTest.php \
  tests/Feature/InstallDemoModeContractTest.php \
  tests/Feature/BootstrapApplyBridgeContractTest.php -v
```

**Step 3: Update concise docs**

Document only:
- new commands now run real runtime flow
- `demo` is non-destructive
- `reset-demo` is still explicit destructive reset
- runtime bridge uses generated compatibility outputs; full env-surface shrink is still a later PR

**Step 4: Commit**

```bash
git add README.md docs/runbooks/application-env-config-cleanup.md tests/Feature/BootstrapPr2aContractTest.php tests/Feature/InstallPr2aShellContractsTest.php tests/Feature/BootstrapEnvShellContractsTest.php tests/Feature/InstallShellContractsTest.php tests/Feature/BootstrapRuntimeBridgeShellContractTest.php tests/Feature/InstallPr3RuntimeShellContractsTest.php tests/Feature/InstallDemoModeContractTest.php tests/Feature/BootstrapApplyBridgeContractTest.php
git commit -m "docs(test): update runtime bridge contract for PR3"
```

## Risks To Watch

- `bootstrap-nginx-ssl.sh prepare` is operationally mutating today and may reload nginx if already running. PR3 should avoid broad changes there unless a targeted no-reload staging bug appears.
- `compose.yaml` is still present and may keep attracting accidental runtime calls. Tests must assert new commands use split bootstrap/apply paths instead.
- `demo` semantics are only safe if the app can seed demo state without destructive reset. If that assumption fails, PR3 must stop with guidance instead of silently resetting.
- `obs.generated.env` is already live today. PR3 should layer `compat.shell.env` around it, not replace it blindly.
- Rendered path layout is inconsistent today:
  - nginx SSL under `${STATE_DIR}/runtime/rendered`
  - Prometheus/Grafana rendered files under `${STATE_DIR}/rendered`
  PR3 should bridge around that inconsistency, not expand it.

## Suggested Execution Order

1. Controller first commit: update the PR3 contract doc only and freeze:
   - real runtime path
   - split app/obs apply
   - `demo` non-destructive
   - `compose.yaml` is no longer the primary runtime path for new commands
2. Task 1 only: Agent 1 implements the bridge loader and gets the preload tests green. Do not touch `install.sh` runtime delegation yet.
3. Task 2 only: Agent 2 routes `install.sh lab|demo|production` from prepare-only to the real runtime path with the smallest possible app/obs apply wiring.
4. Task 3 only: Agent 2 or Controller handles demo semantics. If non-destructive demo seeding is unsupported, fail with guidance instead of resetting.
5. Task 4 only: Agent 3 aligns obs/app apply expectations and integration tests after runtime delegation is already in place.
6. Task 5: Agent 3 updates regression coverage and concise docs, then Controller runs the regression suite and resolves overlaps.

## Handoff Notes

- Do not delete old env consumers in PR3.
- Do not rewrite Compose startup semantics in PR3.
- Do not treat `demo` as shorthand for reset.
- Prefer narrow compatibility shims over new abstraction layers.

Plan complete and saved to `docs/plans/2026-04-25-pr3-runtime-bridge.md`. Two execution options:

1. Subagent-Driven (this session) - I dispatch fresh subagent per task, review between tasks, fast iteration
2. Parallel Session (separate) - Open new session with executing-plans, batch execution with checkpoints

Which approach?
