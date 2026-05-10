<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Excel export for product comparison.
 * Each row is either a group header or a spec row.
 */
class ProductComparisonExport implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    private array $matrix;
    private array $productNames;

    public function __construct(array $matrix, array $productNames)
    {
        $this->matrix = $matrix;
        $this->productNames = $productNames;
    }

    public function array(): array
    {
        $rows = [];

        // Header row
        $rows[] = array_merge(['Thông số'], $this->productNames);

        // Data rows
        foreach ($this->matrix as $item) {
            if ($item['type'] === 'group_header') {
                $rows[] = array_merge([$item['label']], array_fill(0, count($this->productNames), ''));
            } else {
                $rows[] = array_merge([$item['label']], $item['values']);
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return 'So sánh sản phẩm';
    }

    public function styles(Worksheet $sheet): array
    {
        $totalCols = count($this->productNames) + 1;
        $lastCol = chr(64 + $totalCols); // A=65
        if ($totalCols > 26) {
            $lastCol = 'A' . chr(64 + $totalCols - 26);
        }

        // Freeze header row + first column
        $sheet->freezePane('B2');

        // Style header row
        $headerRange = 'A1:' . $lastCol . '1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E3A5F'],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        // Style group header rows and spec rows
        $rowIndex = 2; // Start from row 2 (row 1 is header)
        foreach ($this->matrix as $item) {
            $range = 'A' . $rowIndex . ':' . $lastCol . $rowIndex;

            if ($item['type'] === 'group_header') {
                $sheet->getStyle($range)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10,
                        'color' => ['rgb' => '3730A3'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0E7FF'],
                    ],
                ]);
            } else {
                // Label column styling
                $sheet->getStyle('A' . $rowIndex)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F9FAFB'],
                    ],
                ]);

                // Highlight differing values
                if ($item['differs'] ?? false) {
                    $nonEmpty = array_filter($item['values'], fn($v) => $v !== '—');
                    if (count(array_unique($nonEmpty)) > 1) {
                        for ($c = 1; $c < $totalCols; $c++) {
                            $colLetter = chr(65 + $c);
                            $sheet->getStyle($colLetter . $rowIndex)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'FEF3C7'],
                                ],
                            ]);
                        }
                    }
                }
            }

            $rowIndex++;
        }

        // All cells borders + wrap text
        $lastRow = $rowIndex - 1;
        $fullRange = 'A1:' . $lastCol . $lastRow;
        $sheet->getStyle($fullRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
        ]);

        // Set column A minimum width
        $sheet->getColumnDimension('A')->setWidth(30);

        // Set product columns minimum width
        for ($c = 1; $c < $totalCols; $c++) {
            $colLetter = chr(65 + $c);
            $sheet->getColumnDimension($colLetter)->setWidth(25);
        }

        return [];
    }
}
