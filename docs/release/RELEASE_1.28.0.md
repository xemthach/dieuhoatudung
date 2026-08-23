# Release v1.28.0

Date: 2026-08-23

## Highlights

- Consolidates AI content assistance into the Post and Product workflows.
- Adds truthful persisted live status, review/apply navigation, worker readiness and provider diagnostics.
- Adds safe admin desired-state controls for the governed AI worker.
- Adds cross-process worker self-test, runtime binding proof and release/version drift detection.

## AI content workflow

- Post and Product are the canonical content-generation entry points.
- AI job resources are history and operations surfaces rather than duplicate creation workflows.
- Generate/regenerate creates governed drafts and never overwrites published content before review/apply.
- Live status distinguishes queued, processing, validating, review required, completed, blocked, failed and worker-unavailable states.

## Worker operations

- Exact entrypoint: `php artisan ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900`.
- Admin enable/disable actions persist desired state only and require `ai_worker.manage`.
- Graceful disable stops new claims without force-killing the managed process.
- Runtime diagnostics compare web and worker version, build, worker-code hash, environment, queue and sanitized database identity.
- Non-provider self-test proves a separate worker process can claim and complete a diagnostic job without Product/catalog writes.

## Deployment requirements

- Updating web code alone is not a complete deployment.
- Capture and preserve the pre-deploy desired state.
- Drain active work safely, deploy code/caches, and restart the OS-managed worker.
- Require fresh heartbeat, `ai_governed`, correct DB/runtime, and matching web/worker release.
- Verify scheduler/watchdog health and run the safe worker self-test.
- Restore the original desired state intentionally; never auto-enable AI after deployment.
- Follow `docs/operations/AI_WORKER_DEPLOYMENT_RUNBOOK.md`.

## Security and safety

- No HTTP action spawns or kills worker processes.
- Polling and worker self-tests do not call AI providers.
- Provider secrets, raw responses and stack traces remain hidden from ordinary operators.
- Legacy queue rows and operational history are not purged.

## Validation

- Laravel suite: 355 tests, 1,230 assertions, zero failures/errors, one existing skipped test.
- Composer validation/audit, npm high-severity audit, Vite production build, config/route/view caches, PHP lint and `git diff --check`: PASS.
- Data remained 81 Products / 212 catalog sources / 36,453 catalog models / 656,507 catalog fields with 90 migrations and the canonical BTU hash unchanged.
- Local post-deploy probe `release-1.28.0-local-postdeploy-20260823` was dispatched by PID 20840 and completed by worker PID 7640 on `ai_governed`; provider calls and Product/catalog writes were zero.
- Local worker desired state was restored to `DISABLED`; worker process remained online/paused and accepted no new jobs.
- Production runtime certification still requires execution on the actual deployment host and a healthy scheduler.
