<?php

namespace App\Filament\Resources\BtuCalculations\Pages;

use App\Filament\Resources\BtuCalculations\BtuCalculationResource;
use App\Filament\Traits\HasDataTransferActions;
use App\Services\Calculator\CalculatorRuleSetResolver;
use App\Services\Calculator\EquipmentTypeRecommendationService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListBtuCalculations extends ListRecords
{
    use HasDataTransferActions;

    protected static string $resource = BtuCalculationResource::class;
    protected string $transferModule = 'btu_calculation';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calculator_governance')
                ->label('Quy tắc đang áp dụng')
                ->icon('heroicon-o-shield-check')
                ->color('gray')
                ->modalHeading('Quản trị quy tắc tính BTU')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Đóng')
                ->modalWidth('5xl')
                ->modalContent(fn () => view('filament.btu-calculator-governance', [
                    'rules' => app(CalculatorRuleSetResolver::class)->governance(),
                    'equipmentRules' => app(EquipmentTypeRecommendationService::class)->governance(),
                ])),
            $this->getExportHeaderAction(),
            $this->getImportHeaderAction(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
