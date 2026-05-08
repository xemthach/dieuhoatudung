<?php

namespace App\Services\Media;

use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use Exception;

class R2ConnectionService
{
    public function __construct(private SettingService $settingService)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) $this->settingService->get('r2_storage.r2_enabled', false);
    }

    public function testConnection(): array
    {
        $key = $this->settingService->get('r2_storage.r2_access_key_id');
        $secret = $this->settingService->get('r2_storage.r2_secret_access_key');
        $bucket = $this->settingService->get('r2_storage.r2_bucket');
        $endpoint = $this->settingService->get('r2_storage.r2_endpoint');
        $publicUrl = $this->settingService->get('r2_storage.r2_public_url');

        if (!$key || !$secret || !$bucket || !$endpoint) {
            return [
                'success' => false,
                'message' => 'Thiếu cấu hình R2 (Key, Secret, Bucket, hoặc Endpoint).',
            ];
        }

        try {
            $client = new S3Client([
                'credentials' => [
                    'key'    => $key,
                    'secret' => $secret,
                ],
                'region' => 'auto',
                'endpoint' => $endpoint,
                'version' => 'latest',
            ]);

            $adapter = new AwsS3V3Adapter($client, $bucket, '');
            $filesystem = new Filesystem($adapter);

            $testFileName = '_healthcheck/test-' . time() . '.txt';
            
            // 3. Put file
            $filesystem->write($testFileName, 'test connection');
            
            // 4. Exists file
            if (!$filesystem->fileExists($testFileName)) {
                return [
                    'success' => false,
                    'message' => 'Lỗi kết nối: Không thể ghi file lên bucket.',
                ];
            }

            // 5. Delete file
            $filesystem->delete($testFileName);

            return [
                'success' => true,
                'message' => 'Kết nối R2 thành công (đã test ghi/xóa file thật).',
                'bucket' => $bucket,
                'endpoint' => $endpoint,
                'public_url' => $publicUrl,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Lỗi kết nối: Thông tin xác thực, Endpoint hoặc Bucket không chính xác.',
            ];
        }
    }

    public function getDisk(): ?FilesystemAdapter
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $key = $this->settingService->get('r2_storage.r2_access_key_id');
        $secret = $this->settingService->get('r2_storage.r2_secret_access_key');
        $bucket = $this->settingService->get('r2_storage.r2_bucket');
        $endpoint = $this->settingService->get('r2_storage.r2_endpoint');
        $publicUrl = $this->settingService->get('r2_storage.r2_public_url');

        if (!$key || !$secret || !$bucket || !$endpoint) {
            return null;
        }

        $client = new S3Client([
            'credentials' => [
                'key'    => $key,
                'secret' => $secret,
            ],
            'region' => 'auto',
            'endpoint' => $endpoint,
            'version' => 'latest',
        ]);

        $adapter = new AwsS3V3Adapter($client, $bucket, '', null, null, [
            'visibility' => 'public',
        ]);

        $driver = new Filesystem($adapter);

        return new \Illuminate\Filesystem\AwsS3V3Adapter($driver, $adapter, [
            'bucket' => $bucket,
            'endpoint' => $endpoint,
            'url' => $publicUrl,
        ], $client);
    }
}
