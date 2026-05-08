<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataExportJob;
use App\Services\DataTransfer\DataExportService;
use Illuminate\Http\Request;

class DataExportController extends Controller
{
    public function download(DataExportJob $dataExportJob)
    {
        // Check user is authenticated and has permission
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Unauthorized');
        }

        $perm = "{$dataExportJob->module}.export";
        if (!$user->isSuperAdmin() && !$user->can($perm)) {
            abort(403, 'Bạn không có quyền tải file này.');
        }

        if (!$dataExportJob->isDownloadable()) {
            abort(404, 'File không tồn tại hoặc đã hết hạn.');
        }

        $service = app(DataExportService::class);
        $path = $service->getDownloadPath($dataExportJob);

        if (!$path || !file_exists($path)) {
            abort(404, 'File không tồn tại.');
        }

        $mimeType = match ($dataExportJob->file_type) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv'  => 'text/csv; charset=UTF-8',
            'xml'  => 'application/xml',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };

        return response()->download($path, $dataExportJob->file_name, [
            'Content-Type' => $mimeType,
        ]);
    }
}
