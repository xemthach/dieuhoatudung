#!/usr/bin/env bash
# Upgrade an existing installation. Run --check first; never installs or seeds data.
set -Eeuo pipefail
umask 077

fail() { printf 'DEPLOYMENT_STATUS=FAIL\nREASON=%s\n' "$*" >&2; exit 1; }
usage() {
    cat <<'USAGE'
Usage: bash deploy.sh --app-dir /absolute/app --tag vX.Y.Z --sha <40-hex-SHA>
  --db-backup /private/database.sql.gz --storage-backup /private/storage.tar.gz
  --proof /private/verified-release.json [--check|--apply]

Default: --check (validates the release and backup evidence; no deployment).
--apply requires a clean installation, verified restore drill, CI/staging PASS,
and workers already drained/stopped by the operator. See DEPLOY.md for proof.
Failure during --apply leaves maintenance mode enabled for operator recovery.
USAGE
}

mode=check
app_dir= release_tag= release_sha= db_backup= storage_backup= proof=
while (($#)); do
    case "$1" in
        --app-dir|--tag|--sha|--db-backup|--storage-backup|--proof)
            (($# >= 2)) || fail "Missing value for $1"
            case "$1" in
                --app-dir) app_dir=$2;; --tag) release_tag=$2;; --sha) release_sha=$2;;
                --db-backup) db_backup=$2;; --storage-backup) storage_backup=$2;; --proof) proof=$2;;
            esac
            shift 2;;
        --check) mode=check; shift;;
        --apply) mode=apply; shift;;
        --help|-h) usage; exit 0;;
        *) usage >&2; fail "Unknown option: $1";;
    esac
done

[[ -n "$app_dir" && -n "$release_tag" && -n "$release_sha" && -n "$db_backup" && -n "$storage_backup" && -n "$proof" ]] || { usage >&2; exit 2; }
[[ "$release_sha" =~ ^[0-9a-f]{40}$ ]] || fail 'Release SHA must be the full immutable commit SHA.'
[[ "$release_tag" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail 'Use a published version tag.'
for executable in git php composer sha256sum realpath cmp; do command -v "$executable" >/dev/null || fail "$executable is unavailable"; done
script_path=$(realpath -- "${BASH_SOURCE[0]}")
[[ "$app_dir" = /* && -d "$app_dir" ]] || fail 'Application directory must be an existing absolute path.'
app_dir=$(realpath -- "$app_dir")
cd "$app_dir"
[[ -f artisan && -f .env && -f vendor/autoload.php && -f VERSION ]] || fail 'Existing Laravel installation is incomplete.'
[[ "$(git rev-parse --show-toplevel)" == "$app_dir" ]] || fail 'Application directory must be the repository root.'
[[ -z "$(git status --porcelain)" ]] || fail 'Worktree is dirty; preserve and review local changes first.'
[[ ! -e storage/framework/down ]] || fail 'Installation is already in maintenance mode; resolve its existing deployment first.'

for backup_path in "$db_backup" "$storage_backup" "$proof"; do
    [[ "$backup_path" = /* && -s "$backup_path" ]] || fail 'Backup/proof paths must be absolute, nonempty private files.'
    backup_real=$(realpath -- "$backup_path")
    [[ "$backup_real" != "$app_dir" && "$backup_real" != "$app_dir/"* ]] || fail 'Store backups and proof outside the application/public tree.'
done

previous_sha=$(git rev-parse HEAD)
tag_sha=$(git rev-parse --verify "refs/tags/${release_tag}^{commit}") || fail 'Release tag is missing locally; fetch the reviewed tag before continuing.'
[[ "$tag_sha" == "$release_sha" ]] || fail 'Local tag does not match the approved release SHA.'
remote_refs=$(git ls-remote origin "refs/tags/$release_tag" "refs/tags/$release_tag^{}")
remote_sha=$(printf '%s\n' "$remote_refs" | awk '$2 ~ /\^\{\}$/ {print $1}')
if [[ -z "$remote_sha" ]]; then remote_sha=$(printf '%s\n' "$remote_refs" | awk 'NF == 2 {print $1}'); fi
[[ "$remote_sha" == "$release_sha" ]] || fail 'Remote tag does not match the approved release SHA.'
git merge-base --is-ancestor "$previous_sha" "$release_sha" || fail 'Release diverges from installed history; reconcile before upgrading.'
[[ "$(git show "$release_sha:VERSION" | tr -d '\r\n')" == "${release_tag#v}" ]] || fail 'Target VERSION and tag disagree.'
git cat-file -e "$release_sha:public/build/manifest.json" || fail 'Target release has no committed Vite manifest.'
db_hash=$(sha256sum -- "$db_backup" | awk '{print $1}')
storage_hash=$(sha256sum -- "$storage_backup" | awk '{print $1}')
php -r '
$p=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
$required=["source_sha"=>$argv[2],"release_sha"=>$argv[3],"database_sha256"=>$argv[4],"storage_sha256"=>$argv[5],"database_restore"=>"PASS","storage_restore"=>"PASS","migration_upgrade"=>"PASS","ci"=>"PASS","staging"=>"PASS","workers_drained"=>true,"workers_stopped"=>true];
foreach($required as $key=>$expected) if(($p[$key]??null)!==$expected){fwrite(STDERR,"Missing/mismatched evidence: ".$key."\n");exit(1);}
if(empty($p["verified_at"])||empty($p["verified_by"]))exit(1);
' "$proof" "$previous_sha" "$release_sha" "$db_hash" "$storage_hash" || fail 'Backup/release proof is incomplete or does not match these files.'
php artisan migrate:status --no-ansi
printf 'PREFLIGHT=PASS\nSOURCE_SHA=%s\nRELEASE_SHA=%s\nMODE=%s\n' "$previous_sha" "$release_sha" "$mode"
[[ "$mode" == apply ]] || exit 0

command -v flock >/dev/null || fail 'flock is required to serialize deployments.'
exec 9> storage/framework/deployment.lock
flock -n 9 || fail 'Another deployment holds the installation lock.'
[[ "$(git rev-parse HEAD)" == "$previous_sha" && -z "$(git status --porcelain)" ]] || fail 'Installation changed after preflight.'
[[ ! -e storage/framework/down ]] || fail 'Another operation enabled maintenance after preflight.'
report_dir="$(dirname -- "$proof")/deploy-$(date -u +%Y%m%dT%H%M%SZ)-${release_sha:0:12}"
mkdir -m 700 -- "$report_dir"
# Keep the executing script outside Git before checking out another release.
cp -- "$script_path" "$report_dir/deploy-script.sh"
printf '%s\n' "$previous_sha" > "$report_dir/previous-sha.txt"
printf '%s\n' "$release_sha" > "$report_dir/release-sha.txt"
cp -- "$proof" "$report_dir/verified-release.json"
maintenance=0
on_error() {
    local code=$?
    trap - ERR
    printf 'DEPLOYMENT_STATUS=FAIL\nEXIT_CODE=%s\nREPORT=%s\n' "$code" "$report_dir" >&2
    if [[ "$maintenance" == 1 ]]; then
        printf 'Maintenance remains enabled. Workers must remain stopped; use the manual rollback procedure in DEPLOY.md.\n' >&2
    fi
    exit "$code"
}
trap on_error ERR

fingerprint() {
    php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::beforeExecuting(static function(string $sql): void {
    if(!preg_match('/^\s*(select|show|pragma|with)\b/i',$sql)) throw new RuntimeException('Read-only fingerprint rejected SQL.');
});
$tables=['products','brands','product_categories','catalog_sources','catalog_models','catalog_model_fields','dealers','dealer_reviews','product_reviews','product_questions','site_settings','media_files','posts','leads','quote_requests','btu_calculations','users'];
$result=[];
foreach($tables as $table){
    if(!Illuminate\Support\Facades\Schema::hasTable($table))continue;
    $hash=hash_init('sha256');$count=0;
    foreach(Illuminate\Support\Facades\DB::table($table)->orderBy('id')->cursor() as $row){
        hash_update($hash,json_encode((array)$row,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");$count++;
    }
    $result[$table]=['rows'=>$count,'sha256'=>hash_final($hash)];
}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
PHP
}

php artisan down --retry=60
maintenance=1
fingerprint > "$report_dir/business-before.json"
git checkout --detach "$release_sha"
[[ "$(git rev-parse HEAD)" == "$release_sha" ]] || fail 'Checkout did not reach the approved release.'
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php artisan migrate --force
php artisan migrate:status --no-ansi > "$report_dir/migrations-after.txt"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
fingerprint > "$report_dir/business-after.json"
cmp -- "$report_dir/business-before.json" "$report_dir/business-after.json"
php artisan products:governance-health --json > "$report_dir/product-governance-health.json"
php artisan ai:queue-health --json > "$report_dir/ai-queue-health.json"
php artisan about --no-ansi > "$report_dir/about.txt"
printf 'DEPLOYMENT_STATUS=CODE_READY_FOR_OPERATOR_CHECK\nREPORT=%s\n' "$report_dir"
printf 'Maintenance remains enabled. Complete live read-only checks, start the verified worker programs, check their SHA/heartbeat, then run php artisan up.\n'
