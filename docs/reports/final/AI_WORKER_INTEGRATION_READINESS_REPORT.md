# AI Worker Integration and Environment Readiness

Date: 2026-08-23
Localhost verdict: **PASS**
Production deployment verdict: **CONTRACT READY / TARGET SELECTION REQUIRED**

## Proven worker entrypoint

- Parent: `App\Console\Commands\AIManagedWorker`.
- Child: `App\Console\Commands\AIManagedChildWorker`.
- Command: `php artisan ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900`.
- Child queue connection: resolved from `queue.default` and explicitly passed as `database`.
- Queue: only `ai_governed`; legacy `ai` and `default` are not passed to the worker.
- Desired state: canonical configured path resolving to `storage/framework/cache/ai-worker-desired-state.json`.
- Actual state: fresh `queue_worker_heartbeats` row for child identity `queue-worker` and queue `ai_governed`.
- Online threshold: heartbeat within 5 minutes; stale between 5 and 60 minutes; offline after 60 minutes or non-running/paused process state rules.

## Web/worker binding proof

Independent runtime diagnostics were revalidated for release candidate 1.28.0 with `APP_ENV=local`, PHP 8.3.16, queue connection `database`, queue `ai_governed`, DB connection `mysql`, host `127.0.0.1`, port `3306`, and database `dieuhoa-tudung`.

No credential or secret is exposed. Worker deployment status is `UP_TO_DATE`.

## Cross-process job claim proof

Probe `local-disabled-proof-20260823-0917` was dispatched by PID 18852 while desired state was disabled. After eight seconds it remained queued, with no claim event and one row on `ai_governed`.

After desired state was enabled, child PID 20128 independently emitted:

`WORKER_SELF_TEST_QUEUED → WORKER_SELF_TEST_CLAIMED → WORKER_SELF_TEST_COMPLETED`.

The queue returned to zero. `ai_request_logs` maximum ID remained 235. The probe records `provider_call=false` and `product_mutation=false`.

The final post-deploy probe `post-deploy-final-proof-20260823-0927` was dispatched by web/CLI PID 5284 and independently claimed and completed by worker PID 7796. Its persisted events were queued at 09:27:13 and claimed/completed at 09:27:22. It used queue `ai_governed`, connection `database`, and database `dieuhoa-tudung`; provider log maximum ID remained 235. This is cross-process evidence, not an in-process job handler test.

Release 1.28.0 repeated the gate with probe `release-1.28.0-local-postdeploy-20260823`: it remained queued while desired state was disabled, then dispatcher PID 20840 and worker PID 7640 produced the independent claim/completion transition after controlled enable. Provider log maximum ID remained 235, queue depth returned to zero, and desired state was restored to `DISABLED`.

## Restart and deploy proof

After config/route/view cache rebuild, the reviewed Windows task was restarted. Old supervisor/child PIDs 4124/7292 exited; replacement PIDs 8956/20128 loaded the current version/build and preserved desired `DISABLED` as process state `PAUSED`.

An enabled-state restart then replaced supervisor PID 8956 with 1824 and child PID 20128 with 18156. The desired state remained enabled, deployment status returned `UP_TO_DATE`, and probe `local-enabled-restart-proof-20260823-0918` completed on the new child without a provider call. Desired state was returned to `DISABLED` afterward.

The first manual enabled restart showed the old child still alive at the three-second observation boundary, although it exited shortly afterward. This evidence led to the reusable restart script, which waits up to 30 seconds for both old PIDs and refuses to start a replacement if either remains. Duplicate overlap is therefore prevented in the release procedure rather than assumed away.

## Configuration and duplicate-delivery hardening

- Worker identity now stores release, build, environment, queue and DB binding at process startup.
- Admin compares live web and worker identities and warns on mismatch. A deterministic hash of the worker command/config/service manifest also detects local uncommitted worker-code drift when Git HEAD alone is unchanged.
- `DB_QUEUE_RETRY_AFTER` default is now 960 seconds, above the worker timeout of 900 seconds.
- Task Scheduler ignores duplicate task starts and application ownership rejects a second live supervisor.
- Test desired/managed-state paths are isolated from live runtime paths.

## Scheduler dependency

The AI watchdog is proven through its dedicated one-minute Windows Task and now publishes its own heartbeat. Full Laravel scheduler heartbeat remains stale/unknown locally.

Scheduler-dependent functions include stale-job recovery, periodic queue health recording and technical-log cleanup. The schedule also contains non-AI external integration work; it was not enabled automatically during this audit. Production must install the Laravel scheduler after reviewing all scheduled commands.

## Admin operations UX

The AI operations page now shows:

- application version/environment/build;
- desired and actual worker states;
- heartbeat, PID, queue and accepting-new-work state;
- worker version/build and sanitized DB identity;
- scheduler and AI-watchdog status;
- deployment status (`UP_TO_DATE`, `VERSION_MISMATCH`, `UNKNOWN`);
- latest non-provider worker self-test and cross-process proof.

`Kiểm tra Worker` is separate from provider connection testing and requires `ai_worker.manage`.

## Production boundary

No live production host or OS/process manager was supplied. Production cannot truthfully be runtime-certified from this localhost. A concrete systemd/Supervisor contract and Windows service-grade alternative are documented; selecting and installing one is required before deployment.

An actual PC/server reboot was not performed. Local auto-start is proven by the installed `AtLogOn` task definition and controlled stop/start tests, not by a reboot claim. Full Laravel scheduler is also not installed locally. These are the exact remaining environment-certification blockers.

## Artifacts

- `docs/operations/ai_worker_environment_matrix.md`
- `docs/operations/AI_WORKER_DEPLOYMENT_RUNBOOK.md`
- `scripts/restart_ai_worker_task.ps1`

## Safety

- Provider calls from worker integration probes: 0.
- Product/catalog technical writes: 0.
- Queue/history purge: none.
- Final governed queue depth: 0. Five historical rows on legacy queue `ai` were preserved and are not consumed by this worker.
- Final active AI processing, leases and reservations: 0.
- Final desired state: `DISABLED`.

## Validation

- Focused worker/admin/integration regression: 29 tests, 198 assertions, 0 failures/errors.
- Full suite: 355 tests, 1,230 assertions, 0 failures/errors, 1 existing skipped test.
- PHP lint, config cache, route cache, Blade cache and `git diff --check`: PASS.
- Data: 81 Products / 212 sources / 36,453 models / 656,507 fields; migrations 90.
- BTU hash: `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`.

## Final verdict

- Localhost/Laragon worker integration: **PASS**.
- Code/runtime implementation: **PASS**.
- Production/live environment certification: **PARTIAL / BLOCKED** until the target OS process manager and scheduler are installed and the release/reboot smoke checklist is executed on that host.

Final restart-script proof replaced supervisor/child PIDs 20840/4768 with 2468/7796 only after the old processes exited. Before restart, the newly introduced worker-code hash correctly reported `VERSION_MISMATCH`; afterward web and worker matched at `b169d41292e3b1db4604c094e996dfda14d8ee64d29ca630a833d8b9f1cab873` and deployment status became `UP_TO_DATE` while desired state remained disabled. Final heartbeat reported process `PAUSED`, actual process `ONLINE`, and `accepting_new_jobs=false`.
