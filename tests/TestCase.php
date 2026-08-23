<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    private ?string $baseWorkerDesiredStatePath = null;

    private ?string $baseManagedWorkerStateDirectory = null;

    protected function setUp(): void
    {
        parent::setUp();

        $scope = 'phpunit-'.Str::uuid();
        $this->baseWorkerDesiredStatePath = storage_path("framework/testing/{$scope}-desired.json");
        $this->baseManagedWorkerStateDirectory = storage_path("framework/testing/{$scope}-managed");

        config()->set('ai.worker_desired_state_path', $this->baseWorkerDesiredStatePath);
        config()->set('ai.managed_state_directory', $this->baseManagedWorkerStateDirectory);
        File::ensureDirectoryExists(dirname($this->baseWorkerDesiredStatePath));
        File::put($this->baseWorkerDesiredStatePath, json_encode([
            'desired_state' => 'DISABLED',
            'changed_by' => 'phpunit-isolated-fixture',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function tearDown(): void
    {
        if ($this->baseWorkerDesiredStatePath) {
            File::delete($this->baseWorkerDesiredStatePath);
        }
        if ($this->baseManagedWorkerStateDirectory) {
            File::deleteDirectory($this->baseManagedWorkerStateDirectory);
        }

        parent::tearDown();
    }
}
