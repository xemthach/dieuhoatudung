<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataExportJob;
use App\Services\DataTransfer\DataExportService;

class DataExportController extends Controller
{
    public function download(DataExportJob $dataExportJob)
    {
        // Check user is authenticated and has permission
        $user = auth()->user();
        if (! $user?->is_active) {
            abort(403, 'Unauthorized');
        }

        $perm = "{$dataExportJob->module}.export";
        if (!$user->isSuperAdmin() && !$user->can($perm)) {
            abort(403, 'Bạn không có quyền tải file này.');
        }

        abort_unless($user->isSuperAdmin() || (int) $dataExportJob->created_by === (int) $user->id, 403);
        $permission = match (data_get($dataExportJob->format_context_json, 'intent')) {
            'system_backup' => 'system_restore.run',
            'product_transfer' => 'product_transfer.run',
            default => null,
        };
        abort_if($permission && ! $user->isSuperAdmin() && ! $user->can($permission), 403);

        if (!$dataExportJob->isDownloadable()) {
            abort(404, 'File không tồn tại hoặc đã hết hạn.');
        }


        $service = app(DataExportService::class);
        $path = $service->getDownloadPath($dataExportJob);

        if (!$path || !file_exists($path)) {
            abort(404, 'File không tồn tại.');
        }
        $checksum = data_get($dataExportJob->format_context_json, 'sha256');
        abort_if($checksum && ! hash_equals($checksum, hash_file('sha256', $path) ?: ''), 409, 'File export đã thay đổi. Hãy xuất lại.');

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
