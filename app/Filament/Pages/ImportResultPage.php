<?php

namespace App\Filament\Pages;

use App\Models\DataImportJob;
use App\Services\DataTransfer\ModuleRegistry;
use Filament\Actions\Action;
use Filament\Pages\Page;

class ImportResultPage extends Page
{
    protected string $view = 'filament.pages.import-result';
    protected static bool $shouldRegisterNavigation = false;

    public ?int $jobId = null;
    public ?DataImportJob $job = null;

    public function mount(): void
    {
        $this->jobId = request()->query('job');
        $this->job = DataImportJob::find($this->jobId);

        if (!$this->job) {
            $this->redirect(DataTransferPage::getUrl());
        }
    }

    public function getTitle(): string
    {
        $moduleName = ModuleRegistry::modules()[$this->job?->module ?? ''] ?? '';
        return "Ket qua Import: {$moduleName}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            DataTransferPage::getUrl() => 'Import / Export',
            '#' => 'Ket qua Import',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Quay lai Import/Export')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(DataTransferPage::getUrl()),
        ];
    }
}
