<?php

namespace App\Services\DataTransfer;

use App\Services\Settings\SettingService;
use App\Models\ImportGovernanceAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** One source for business-level import modes. Integrity validation is not configurable. */
final class ImportGovernanceService
{
    public const DETACH_CATALOG_LINEAGE = 'product_transfer.detach_catalog_lineage';

    public static function definitions(): array
    {
        return [
            'product_transfer.enabled' => ['module'=>'product_transfer','default'=>'ON','modes'=>['ON','OFF'],'risk'=>'HIGH','description'=>'Enable signed Product Transfer imports and exports.'],
            self::DETACH_CATALOG_LINEAGE => ['module'=>'product_transfer','default'=>'OFF','modes'=>['ON','OFF'],'risk'=>'HIGH','description'=>'Detach non-portable catalog lineage during transfer.'],
            'product_transfer.allow_create' => ['module'=>'product_transfer','default'=>'ON','modes'=>['ON','OFF'],'risk'=>'MEDIUM','description'=>'Allow transfer to create missing Products.'],
            'product_transfer.allow_update' => ['module'=>'product_transfer','default'=>'OFF','modes'=>['ON','OFF'],'risk'=>'HIGH','description'=>'Allow transfer to update matched Products.'],
            'product_transfer.allow_upsert' => ['module'=>'product_transfer','default'=>'OFF','modes'=>['ON','OFF'],'risk'=>'HIGH','description'=>'Allow mixed create/update transfer packages.'],
            'manual_product_import.technical_values' => ['module'=>'manual_product_import','default'=>'ENFORCE','modes'=>['ENFORCE','WARN','OFF'],'risk'=>'HIGH','description'=>'Govern unverified technical values in manual imports.'],
            'catalog.require_technical_appendix' => ['module'=>'catalog','default'=>'ENFORCE','modes'=>['ENFORCE'],'risk'=>'CRITICAL','description'=>'Catalog technical facts require source provenance.','system_locked'=>true],
            'catalog.reject_unknown_schema_specs' => ['module'=>'catalog','default'=>'ENFORCE','modes'=>['ENFORCE'],'risk'=>'CRITICAL','description'=>'Reject technical fields outside active category schema.','system_locked'=>true],
            'import.continue_on_row_error' => ['module'=>'import','default'=>'ON','modes'=>['ON','OFF'],'risk'=>'MEDIUM','description'=>'Continue independent rows after a row failure.'],
            'bulk.import.enabled' => ['module'=>'bulk','default'=>'ON','modes'=>['ON','OFF'],'risk'=>'MEDIUM','description'=>'Allow bulk import.'],
            'bulk.update.enabled' => ['module'=>'bulk','default'=>'OFF','modes'=>['ON','OFF'],'risk'=>'HIGH','description'=>'Allow bulk updates.'],
            'bulk.retry.enabled' => ['module'=>'bulk','default'=>'OFF','modes'=>['ON','OFF'],'risk'=>'HIGH','description'=>'Allow retry of failed import rows.'],
            'integrity.manifest' => ['module'=>'integrity','default'=>'ENFORCE','modes'=>['ENFORCE'],'risk'=>'CRITICAL','description'=>'Signed manifest and checksum validation.','system_locked'=>true],
            'integrity.checksum' => ['module'=>'integrity','default'=>'ENFORCE','modes'=>['ENFORCE'],'risk'=>'CRITICAL','description'=>'Canonical columns and payload checksums.','system_locked'=>true],
            'integrity.authorization' => ['module'=>'integrity','default'=>'ENFORCE','modes'=>['ENFORCE'],'risk'=>'CRITICAL','description'=>'Server-side authorization.','system_locked'=>true],
            'integrity.fk' => ['module'=>'integrity','default'=>'ENFORCE','modes'=>['ENFORCE'],'risk'=>'CRITICAL','description'=>'Foreign-key and identity integrity.','system_locked'=>true],
            'integrity.malformed_workbook' => ['module'=>'integrity','default'=>'ENFORCE','modes'=>['ENFORCE'],'risk'=>'CRITICAL','description'=>'Malformed or contract-incompatible workbooks are rejected.','system_locked'=>true],
            'integrity.security_boundary' => ['module'=>'integrity','default'=>'ENFORCE','modes'=>['ENFORCE'],'risk'=>'CRITICAL','description'=>'Tenant and application security boundaries.','system_locked'=>true],
            'integrity.duplicate_identity' => ['module'=>'integrity','default'=>'ENFORCE','modes'=>['ENFORCE'],'risk'=>'CRITICAL','description'=>'Conflicting SKU, slug, and immutable identities are rejected.','system_locked'=>true],
        ];
    }

    public function __construct(private readonly SettingService $settings) {}

    public function catalogDetachEnabled(): bool
    {
        return $this->mode(self::DETACH_CATALOG_LINEAGE) === 'ON';
    }

    public function mode(string $key): string
    {
        $definition = self::definitions()[$key] ?? throw new InvalidArgumentException("Unknown import governance policy: {$key}");
        if ($definition['system_locked'] ?? false) return $definition['default'];
        $value = strtoupper((string) $this->settings->get($key, $definition['default']));
        return in_array($value, $definition['modes'], true) ? $value : $definition['default'];
    }

    public function enabled(string $key): bool { return $this->mode($key) === 'ON'; }

    public function change(string $key, string $mode, string $reason, User $actor): void
    {
        $definition = self::definitions()[$key] ?? throw new InvalidArgumentException('Unknown policy.');
        abort_unless($actor->isSuperAdmin() || $actor->can('import_governance.change'), 403);
        if ($definition['system_locked'] ?? false) throw new InvalidArgumentException('LOCKED - SYSTEM INTEGRITY.');
        $mode = strtoupper($mode);
        if (! in_array($mode, $definition['modes'], true)) throw new InvalidArgumentException('Invalid policy mode.');
        if (trim($reason) === '') throw new InvalidArgumentException('A change reason is required.');
        $old = $this->mode($key);
        DB::transaction(function () use ($key, $mode, $reason, $actor, $old): void {
            $this->settings->set($key, $mode);
            ImportGovernanceAudit::create(['policy_key'=>$key,'old_mode'=>$old,'new_mode'=>$mode,'reason'=>trim($reason),'changed_by'=>$actor->id,'changed_at'=>now(),'context_json'=>['ip'=>request()?->ip()]]);
        });
    }

    public function snapshot(): array
    {
        return collect(self::definitions())->map(fn (array $definition, string $key) => [
            'mode'=>$this->mode($key), 'source'=>($definition['system_locked'] ?? false) ? 'system' : 'site_settings',
            'risk'=>$definition['risk'], 'system_locked'=>(bool)($definition['system_locked'] ?? false),
        ])->all();
    }
}
