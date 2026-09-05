#!/usr/bin/env bash
set -Eeuo pipefail

readonly APP_DIR='/home/dieuhoatudungcom/dieuhoatudung.com/public_html'
readonly APP_USER='dieuhoatudungcom'
readonly RELEASE_TAG='v1.33.8'
readonly RELEASE_VERSION='1.33.8'
readonly ROLLBACK_TAG='v1.33.7'
readonly ROLLBACK_SHA='43c620e878542ee63f34d2075b6e350f2b251c67'
readonly BACKUP_DIR='/home/dieuhoatudungcom/backups'
readonly REPORT_DIR="$APP_DIR/storage/logs/deployments"

stamp="$(date -u +%Y%m%d_%H%M%S)"
report="$REPORT_DIR/LIVE_PRODUCT_TRANSFER_GOVERNANCE_BTU_1.33.8_${stamp}.md"
log="$REPORT_DIR/LIVE_PRODUCT_TRANSFER_GOVERNANCE_BTU_1.33.8_${stamp}.log"
mysql_cnf="$BACKUP_DIR/.deploy-v1.33.8-${stamp}.cnf"
maintenance=0

mkdir -p "$REPORT_DIR" "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
exec > >(tee -a "$log") 2>&1

cleanup() {
    rm -f -- "$mysql_cnf"
    if [[ "$maintenance" == '1' ]]; then
        runuser -u "$APP_USER" -- /usr/bin/php "$APP_DIR/artisan" up >/dev/null 2>&1 || true
    fi
}
trap cleanup EXIT

fail() {
    printf '\nDEPLOYMENT_STATUS=FAIL\nREASON=%s\nREPORT=%s\n' "$1" "$report"
    exit 1
}

json_value() {
    local file="$1" path="$2"
    /usr/bin/php -r '$d=json_decode(file_get_contents($argv[1]),true); foreach(explode(".",$argv[2]) as $p){$d=is_array($d)&&array_key_exists($p,$d)?$d[$p]:null;} echo is_bool($d)?($d?"true":"false"):(is_scalar($d)?$d:"");' "$file" "$path"
}

cd "$APP_DIR"
[[ -f artisan ]] || fail 'artisan missing from exact project path'
[[ -z "$(git status --porcelain)" ]] || fail 'production Git worktree is dirty'

old_sha="$(git rev-parse HEAD)"
old_version="$(tr -d '[:space:]' < VERSION)"
old_tag="$(git describe --tags --always)"
pre_health="$REPORT_DIR/ai-health-before-${stamp}.json"
runuser -u "$APP_USER" -- /usr/bin/php artisan ai:queue-health --json > "$pre_health"
desired_before="$(json_value "$pre_health" worker_desired_state)"
processing_before="$(/usr/bin/php -r '$d=json_decode(file_get_contents($argv[1]),true); echo (int)($d["ai_content_processing_count"]??0)+(int)($d["ai_product_processing_count"]??0)+(int)($d["legacy_ai_processing_count"]??0);' "$pre_health")"
[[ "$desired_before" == 'ENABLED' || "$desired_before" == 'DISABLED' ]] || fail 'AI desired state could not be captured'
[[ "$processing_before" == '0' ]] || fail 'AI work is processing; drain safely before deployment'

supervisor_before="$REPORT_DIR/supervisor-before-${stamp}.txt"
supervisorctl status > "$supervisor_before"
ps_before="$REPORT_DIR/workers-before-${stamp}.txt"
ps -eo pid,ppid,user,args | grep -E 'ai:managed-worker|ai:managed-child-worker|queue:work' | grep -v grep > "$ps_before" || true

runuser -u "$APP_USER" -- env DEPLOY_MYSQL_CNF="$mysql_cnf" /usr/bin/php <<'PHP'
<?php
$root = getcwd();
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = config('database.connections.'.config('database.default'));
if (($connection['driver'] ?? null) !== 'mysql') { fwrite(STDERR, "Only mysql backup is supported.\n"); exit(2); }
$quote = static fn ($value) => '"'.addcslashes((string) $value, "\\\"").'"';
$config = "[client]\n"
    .'host='.$quote($connection['host'] ?? '127.0.0.1')."\n"
    .'port='.$quote($connection['port'] ?? '3306')."\n"
    .'user='.$quote($connection['username'] ?? '')."\n"
    .'password='.$quote($connection['password'] ?? '')."\n";
file_put_contents(getenv('DEPLOY_MYSQL_CNF'), $config, LOCK_EX);
chmod(getenv('DEPLOY_MYSQL_CNF'), 0600);
echo $connection['database'] ?? '';
PHP
db_name="$(runuser -u "$APP_USER" -- /usr/bin/php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo config("database.connections.".config("database.default").".database");')"
[[ -n "$db_name" ]] || fail 'database name unavailable'
backup="$BACKUP_DIR/dieuhoatudung-pre-v1.33.8-${stamp}.sql.gz"
runuser -u "$APP_USER" -- mysqldump --defaults-extra-file="$mysql_cnf" --single-transaction --quick --routines --triggers "$db_name" | gzip -9 > "$backup"
rm -f -- "$mysql_cnf"
[[ -s "$backup" ]] || fail 'database backup is empty'
backup_size="$(stat -c '%s' "$backup")"
backup_sha="$(sha256sum "$backup" | awk '{print $1}')"

git fetch --prune --tags origin
tag_sha="$(git rev-parse "${RELEASE_TAG}^{commit}")"
remote_tag_sha="$(git ls-remote origin "refs/tags/${RELEASE_TAG}^{}" | awk '{print $1}')"
[[ -n "$remote_tag_sha" && "$remote_tag_sha" == "$tag_sha" ]] || fail 'local and remote release tag SHA mismatch'
[[ "$(git rev-parse "${ROLLBACK_TAG}^{commit}")" == "$ROLLBACK_SHA" ]] || fail 'immutable rollback tag mismatch'

runuser -u "$APP_USER" -- /usr/bin/php artisan down --retry=60
maintenance=1
git checkout "$RELEASE_TAG"
[[ "$(git rev-parse HEAD)" == "$tag_sha" ]] || fail 'checked-out HEAD differs from release tag'
[[ "$(tr -d '[:space:]' < VERSION)" == "$RELEASE_VERSION" ]] || fail 'VERSION does not match release'
[[ -f public/build/manifest.json ]] || fail 'certified Vite manifest missing'

runuser -u "$APP_USER" -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u "$APP_USER" -- /usr/bin/php artisan migrate --force
migrations="$REPORT_DIR/migrations-after-${stamp}.txt"
runuser -u "$APP_USER" -- /usr/bin/php artisan migrate:status --no-ansi > "$migrations"
grep -q 'Pending' "$migrations" && fail 'pending migrations remain'
runuser -u "$APP_USER" -- /usr/bin/php artisan optimize:clear
runuser -u "$APP_USER" -- /usr/bin/php artisan config:cache
runuser -u "$APP_USER" -- /usr/bin/php artisan route:cache
runuser -u "$APP_USER" -- /usr/bin/php artisan view:cache

supervisorctl reread
supervisorctl update
supervisorctl restart dieuhoa-worker_00
supervisorctl restart dieuhoa-worker_01
supervisorctl restart dieuhoa-ai-governed
sleep 5
supervisor_after="$REPORT_DIR/supervisor-after-${stamp}.txt"
supervisorctl status > "$supervisor_after"
grep -E '^dieuhoa-worker_00[[:space:]]+RUNNING' "$supervisor_after" >/dev/null || fail 'generic worker 00 is not running'
grep -E '^dieuhoa-worker_01[[:space:]]+RUNNING' "$supervisor_after" >/dev/null || fail 'generic worker 01 is not running'
grep -E '^dieuhoa-ai-governed[[:space:]]+RUNNING' "$supervisor_after" >/dev/null || fail 'managed worker is not running'

desired_now="$(runuser -u "$APP_USER" -- /usr/bin/php artisan ai:queue-health --json | /usr/bin/php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["worker_desired_state"]??"";')"
if [[ "$desired_now" != "$desired_before" ]]; then
    runuser -u "$APP_USER" -- /usr/bin/php artisan ai:worker-state "$desired_before" --no-interaction
fi
post_health="$REPORT_DIR/ai-health-after-${stamp}.json"
runuser -u "$APP_USER" -- /usr/bin/php artisan ai:queue-health --json > "$post_health"
desired_after="$(json_value "$post_health" worker_desired_state)"
app_version="$(json_value "$post_health" application_runtime.app_version)"
app_build="$(json_value "$post_health" application_runtime.build_id)"
worker_version="$(json_value "$post_health" worker_runtime.app_version)"
worker_build="$(json_value "$post_health" worker_runtime.build_id)"
deployment_status="$(json_value "$post_health" worker_deployment_status)"
[[ "$desired_after" == "$desired_before" ]] || fail 'AI desired state was not restored'
[[ "$app_version" == "$RELEASE_VERSION" && "$worker_version" == "$RELEASE_VERSION" ]] || fail 'application/worker version mismatch'
[[ "$app_build" == "$tag_sha" && "$worker_build" == "$tag_sha" ]] || fail 'application/worker build SHA mismatch'
[[ "$deployment_status" == 'UP_TO_DATE' ]] || fail 'managed worker is not up to date'

schedule_list="$REPORT_DIR/schedule-list-${stamp}.txt"
runuser -u "$APP_USER" -- /usr/bin/php artisan schedule:list --no-ansi > "$schedule_list"
cron="$REPORT_DIR/cron-${stamp}.txt"
crontab -l -u "$APP_USER" > "$cron" 2>&1 || fail 'production cron unavailable'
grep -F "$APP_DIR" "$cron" | grep -F 'artisan schedule:run' >/dev/null || fail 'expected scheduler cron missing'

runuser -u "$APP_USER" -- /usr/bin/php artisan up
maintenance=0
about="$REPORT_DIR/about-after-${stamp}.txt"
runuser -u "$APP_USER" -- /usr/bin/php artisan about --no-ansi > "$about"

cat > "$report" <<EOF
# Live deployment v1.33.8

- Status: CODE_DEPLOY_PASS
- Timestamp UTC: $stamp
- Previous: $old_version / $old_tag / $old_sha
- Release: $RELEASE_VERSION / $RELEASE_TAG / $tag_sha
- Rollback: $ROLLBACK_TAG / $ROLLBACK_SHA
- Backup: $backup
- Backup size: $backup_size
- Backup SHA-256: $backup_sha
- AI desired state before/after: $desired_before / $desired_after
- Application version/build: $app_version / $app_build
- Managed worker version/build: $worker_version / $worker_build
- Worker deployment status: $deployment_status
- Migrations: PASS
- Laravel cache lifecycle: PASS
- Generic and managed Supervisor workers: RUNNING
- Scheduler cron and schedule list: PASS
- Product transfer/import confirmation: NOT RUN
- Product/marketing-capacity backfill: NOT RUN

Detailed machine output is stored beside this report. Code deployment does not
certify Admin browser UX, Product Transfer preview, Product data transfer, BTU
population/filter acceptance, SkyAir/wall-mounted edits or log review.
EOF

printf '\nDEPLOYMENT_STATUS=PASS\nRELEASE_SHA=%s\nBACKUP_PATH=%s\nBACKUP_SHA256=%s\nREPORT=%s\n' "$tag_sha" "$backup" "$backup_sha" "$report"
