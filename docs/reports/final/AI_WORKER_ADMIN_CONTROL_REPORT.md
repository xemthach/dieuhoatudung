# AI Worker Admin Control Report

Date: 2026-08-23
Verdict: **PASS**

## Desired-state source

The existing canonical source remains `storage/framework/cache/ai-worker-desired-state.json`. `AIWorkerDesiredStateService` is now the single reader/writer used by the CLI command, queue monitor, system health and Filament control page. No second database/config flag was introduced.

The path is registered once in `config/ai.php`; security tests override that path with a unique file under `storage/framework/testing`. A hash-before/after proof confirmed test transitions do not alter the live desired-state file.

Canonical desired states are `ENABLED` and `DISABLED`.

## Actual-state source

Actual process state is derived independently from the persisted `queue_worker_heartbeats` row for worker `queue-worker` on queue `ai_governed`:

- recent `running` heartbeat: `ONLINE`, accepts work only when desired state is enabled;
- recent `paused` heartbeat: process `ONLINE`, AI processing disabled, no new claims;
- heartbeat older than five minutes: `STALE`;
- absent or older than one hour: `OFFLINE`/unknown runtime evidence.

Supervisor heartbeat now uses `queue-worker-supervisor`, preventing it from overwriting the child worker's truthful `paused` state.

## Enable behavior

`Bật AI Worker` requires `ai_worker.manage` and only changes desired state to `ENABLED`. It records `WORKER_ENABLED` with actor, timestamp and before/after desired state. It does not invoke Artisan, a shell command, a provider, or queue mutation.

When actual state remains offline/stale, the UI reports that the desired state is enabled but the process manager/watchdog has not made the worker ready.

## Disable behavior

`Tắt AI Worker` requires confirmation and `ai_worker.manage`. It writes `DISABLED` and records `WORKER_DISABLED`.

The child worker checks desired state before every `queue:work --once` claim. An operation already running may complete; subsequent claims stop and heartbeat changes to `paused`. No job is purged, no lease is force-released and no process is killed by HTTP.

## Process manager contract

The OS owns process lifecycle. On the current Windows deployment the verified Scheduled Tasks are:

- `DieuHoaTuDung-AIGovernedWorker`
- `DieuHoaTuDung-AIGovernedWatchdog`

The exact managed-worker command from the current registration script is:

```text
php artisan ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900
```

The watchdog runs:

```text
php artisan ai:worker-watchdog
```

The watchdog only requests the verified OS task when desired state is enabled and bounded recovery policy permits it. Duplicate supervisors are rejected through the database/queue-scoped ownership file and live PID check.

## RBAC and audit

- `ai_worker.view`: read operational state.
- `ai_worker.manage`: enable/disable processing.
- Super Admin retains the existing Gate bypass semantics.
- Content/AI viewers may inspect status through existing view permissions but do not receive toggle actions.
- Deployment must run the existing permission synchronization workflow and explicitly assign `ai_worker.manage` only to infrastructure operators.
- Audit events use `ai_technical_logs`; secrets, provider payloads and credentials are absent.

## UI and polling

`Trạng thái vận hành AI` separately displays:

- desired processing state;
- actual process state;
- whether new jobs are accepted;
- heartbeat age;
- active and pending counts;
- last desired-state change.

The page polls every 10 seconds. `Làm mới trạng thái` performs bounded read-only queries and neither dispatches work nor calls a provider.

The valid mismatch presentations are:

- enabled + online/running: ready;
- enabled + offline/stale: warning, cannot process yet;
- disabled + online/paused: normal disabled processing with live process;
- disabled + offline: normal disabled state.

## Generate guard

Post and Product single/bulk generation entry points now use `AIWorkerReadinessService`. A governed operation may still be persisted safely, but notifications truthfully distinguish ready, disabled and offline/stale states. Operators with `ai_worker.manage` receive a direct `Bật AI Worker` link; ordinary editors only receive the status explanation.

## Runtime proof

After loading the new command code through the verified Scheduled Task:

- desired state: `DISABLED`;
- process: `ONLINE`;
- child heartbeat state: `PAUSED`;
- accepts new jobs: `false`;
- pending: `0`;
- processing: `0`;
- stuck: `0`;
- active runtime leases: `0`;
- active runtime slots: `0`;
- reserved tokens: `0`.

The reload was performed only after proving zero active work. No queue/history purge occurred.

## Tests

Focused coverage proves:

- disabled to enabled and enabled to disabled transitions;
- persisted audit events;
- unauthorized controls hidden and server-side guards present;
- enabled + online equals ready;
- enabled + stale equals warning/not ready;
- disabled + paused process remains online but accepts no work;
- refresh is read-only;
- the HTTP page has no process/provider/purge call;
- permission registry includes `ai_worker.manage`;
- Product polling remains within its bounded query budget.

Focused worker-control coverage: **8 tests, 51 assertions, 0 failures/errors**.
Full suite: **350 tests, 1,198 assertions, 0 failures/errors, 1 existing skipped test**.

PHP lint, config cache, route cache, Blade cache and `git diff --check` passed. Permission sync dry-run confirmed `ai_worker.view` and `ai_worker.manage` are the two new worker permissions; no role reset or orphan cleanup was executed. Super Admin can operate the control through the existing Gate bypass. Production role assignment remains an explicit deployment step.

Data integrity remained **81 / 212 / 36,453 / 656,507**. Canonical JSON-row BTU hash remained `3e981c60fcadd3461746fd8f3b94855dc5205bad6c446c55c17066d40c47e3ba`.

The enable/disable actions, refresh polling and automated validation made **0 provider calls**. Runtime forensics also observed three provider requests belonging to a separate, persisted operator-created article operation (`AiContentJob #12`) after the operator enabled processing; these were not emitted by toggle, polling, readiness or connection-check code. No further provider request log was created during the final full-suite run (`ai_request_logs` maximum ID remained 235).

Product/catalog technical writes: **0**.
