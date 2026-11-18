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

        // Factory products belong to an isolated database. An operator's PDF
        // coverage ledger is keyed by that operator's own database IDs.
        config()->set('catalog_evidence.coverage_path', base_path('tests/Fixtures/catalog/empty_coverage.json'));
        config()->set('catalog_evidence.gree_sections_path', base_path('tests/Fixtures/catalog/empty_sections.json'));

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
