<?php

namespace App\Filament\Pages;

use App\Models\ImportGovernanceAudit;
use App\Services\DataTransfer\ImportGovernanceService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportGovernancePage extends Page
{
    protected string $view = 'filament.pages.import-governance';
    protected static ?string $slug = 'import-export-governance';
    protected static ?int $navigationSort = 2;
    public array $modes = [];
    public array $reasons = [];

    public static function getNavigationIcon(): ?string { return 'heroicon-o-shield-check'; }
    public static function getNavigationLabel(): string { return 'Import / Export & Data Governance'; }
    public static function getNavigationGroup(): ?string { return 'Hệ thống'; }
    public function getTitle(): string { return 'Import / Export & Data Governance'; }
    public static function canAccess(): bool { $u=auth()->user(); return (bool) ($u && ($u->isSuperAdmin() || $u->can('import_governance.view'))); }

    public function mount(): void
    {
        $service = app(ImportGovernanceService::class);
        foreach (ImportGovernanceService::definitions() as $key => $definition) $this->modes[$this->stateKey($key)] = $service->mode($key);
    }

    public function policies(): array
    {
        $service = app(ImportGovernanceService::class);
        $latest = ImportGovernanceAudit::query()->with('operator')->latest('changed_at')->get()->unique('policy_key')->keyBy('policy_key');
        return collect(ImportGovernanceService::definitions())->map(function ($definition, $key) use ($service, $latest) {
            $audit = $latest->get($key);
            return $definition + [
                'key'=>$key,
                'current'=>$service->mode($key),
                'last_changed_by'=>$audit?->operator?->name,
                'last_changed_at'=>$audit?->changed_at,
                'last_reason'=>$audit?->reason,
            ];
        })->values()->all();
    }

    public function recentAudits()
    {
        return ImportGovernanceAudit::query()->with('operator')->latest('changed_at')->limit(20)->get();
    }

    public function savePolicy(string $key): void
    {
        $stateKey = $this->stateKey($key);
        app(ImportGovernanceService::class)->change($key, (string) ($this->modes[$stateKey] ?? ''), (string) ($this->reasons[$stateKey] ?? ''), auth()->user());
        $this->reasons[$stateKey] = '';
        Notification::make()->success()->title('Governance policy updated')->send();
    }

    public function stateKey(string $key): string
    {
        return str_replace('.', '__', $key);
    }
}
