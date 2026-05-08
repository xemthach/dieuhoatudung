<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync
        {--dry-run : Show diff without applying changes}
        {--apply : Apply changes (create new permissions)}
        {--reset-default-roles : Reset default role→permission assignments}
        {--cleanup : Remove permissions not in registry (DANGEROUS)}';

    protected $description = 'Sync permissions from config/permissions.php registry to database';

    /**
     * Default role→permission mapping.
     * Uses module prefixes — automatically expanded to full permission names.
     */
    private function getDefaultRolePermissions(): array
    {
        return [
            'admin' => [
                // Dashboard
                'dashboard.view',
                // Settings
                'settings.view', 'settings.edit',
                // R2
                'r2.view', 'r2.test', 'r2.scan', 'r2.sync', 'r2.view_logs',
                // Mail Template
                'mail_template.*',
                // Mail Log
                'mail_log.*',
                // User (limited)
                'user.view', 'user.create', 'user.edit', 'user.reset_password',
                // Role (view only)
                'role.view',
                // AI
                'ai_provider.*', 'ai_content_job.*',
                // SEO
                'seo_audit.*', 'internal_link.*', 'redirect.*',
                // Content
                'author.*', 'post_category.*', 'post.*', 'tag.*',
                // E-commerce
                'brand.*', 'product_category.*', 'product.*', 'promotion.*',
                // Leads
                'lead.*', 'quote_request.*', 'btu_calculator.*',
                // Pages
                'faq.*', 'landing_section.*', 'policy_page.*',
                'case_study.*', 'testimonial.*',
                // Reviews
                'product_review.*', 'product_question.*',
            ],

            'editor' => [
                'dashboard.view',
                // Content — full
                'author.*', 'post_category.*', 'post.*', 'tag.*',
                // Product — create/edit only
                'product.view', 'product.create', 'product.edit',
                'product_category.view',
                'brand.view',
                'promotion.view',
                // Reviews
                'product_review.view', 'product_review.edit', 'product_review.approve', 'product_review.reply',
                'product_question.view', 'product_question.edit', 'product_question.answer',
                // Pages
                'faq.*', 'case_study.*', 'testimonial.*',
                'landing_section.view', 'landing_section.edit',
                // Leads (view only)
                'lead.view',
                // Mail log (view only)
                'mail_log.view',
            ],

            'staff' => [
                'dashboard.view',
                // Leads & Quotes
                'lead.view', 'lead.edit',
                'quote_request.view', 'quote_request.edit',
                // Reviews — reply only
                'product_review.view', 'product_review.reply',
                'product_question.view', 'product_question.answer',
                // Product — view only
                'product.view',
                // BTU
                'btu_calculator.view', 'btu_calculator.edit',
                // Mail log
                'mail_log.view',
            ],

            'viewer' => [
                'dashboard.view',
                'product.view',
                'brand.view',
                'product_category.view',
                'post.view',
                'lead.view',
                'quote_request.view',
                'product_review.view',
                'product_question.view',
                'faq.view',
                'case_study.view',
                'mail_log.view',
            ],
        ];
    }

    public function handle(): int
    {
        $registry = config('permissions');
        if (empty($registry)) {
            $this->error('config/permissions.php is empty or not found.');
            return self::FAILURE;
        }

        // Build flat list of all permissions from registry
        $registryPerms = [];
        foreach ($registry as $module => $config) {
            foreach ($config['permissions'] as $action => $label) {
                $registryPerms[] = "{$module}.{$action}";
            }
        }
        sort($registryPerms);

        // Get current DB permissions
        $dbPerms = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->toArray();

        // Calculate diff
        $toCreate = array_diff($registryPerms, $dbPerms);
        $orphaned = array_diff($dbPerms, $registryPerms);
        $existing = array_intersect($registryPerms, $dbPerms);

        // Display summary
        $this->newLine();
        $this->info(" Permission Registry: " . count($registryPerms) . " permissions across " . count($registry) . " modules");
        $this->info(" Database: " . count($dbPerms) . " permissions");
        $this->newLine();

        if (count($toCreate) > 0) {
            $this->warn("🆕 To CREATE (" . count($toCreate) . "):");
            foreach ($toCreate as $p) {
                $this->line(" + {$p}");
            }
        } else {
            $this->info(" No new permissions to create.");
        }

        $this->newLine();

        if (count($orphaned) > 0) {
            $this->warn(" ORPHANED in DB — not in registry (" . count($orphaned) . "):");
            foreach ($orphaned as $p) {
                // Check if any roles use it
                $roleCount = Permission::where('name', $p)->first()?->roles()->count() ?? 0;
                $this->line(" - {$p}" . ($roleCount > 0 ? " (used by {$roleCount} roles)" : ""));
            }
        } else {
            $this->info(" No orphaned permissions.");
        }

        $this->newLine();
        $this->info(" Already synced: " . count($existing));

        // Dry run stops here
        if ($this->option('dry-run')) {
            $this->newLine();
            $this->comment('Dry run complete. Use --apply to create new permissions.');
            return self::SUCCESS;
        }

        // Apply: create new permissions
        if ($this->option('apply') || $this->option('reset-default-roles')) {
            if (count($toCreate) > 0) {
                $this->newLine();
                $bar = $this->output->createProgressBar(count($toCreate));
                $bar->start();
                foreach ($toCreate as $p) {
                    Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
                    $bar->advance();
                }
                $bar->finish();
                $this->newLine();
                $this->info(" Created " . count($toCreate) . " new permissions.");
            }
        }

        // Cleanup orphaned (only with --cleanup flag)
        if ($this->option('cleanup') && count($orphaned) > 0) {
            if ($this->confirm(" Delete " . count($orphaned) . " orphaned permissions? This removes them from ALL roles.")) {
                foreach ($orphaned as $p) {
                    Permission::where('name', $p)->where('guard_name', 'web')->delete();
                    $this->line(" Deleted: {$p}");
                }
                $this->info(" Cleaned up " . count($orphaned) . " orphaned permissions.");
            }
        }

        // Reset default roles
        if ($this->option('reset-default-roles')) {
            $this->newLine();
            $this->info(" Resetting default role permissions...");

            $allPerms = Permission::where('guard_name', 'web')->pluck('name', 'id');
            $roleDefaults = $this->getDefaultRolePermissions();

            // super_admin gets ALL
            $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
            $superAdmin->syncPermissions(Permission::where('guard_name', 'web')->get());
            $this->line(" super_admin → ALL (" . Permission::count() . " permissions)");

            foreach ($roleDefaults as $roleName => $patterns) {
                $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

                // Expand wildcard patterns
                $resolved = [];
                foreach ($patterns as $pattern) {
                    if (str_ends_with($pattern, '.*')) {
                        $prefix = substr($pattern, 0, -2);
                        foreach ($allPerms as $name) {
                            if (str_starts_with($name, $prefix . '.')) {
                                $resolved[] = $name;
                            }
                        }
                    } else {
                        if ($allPerms->contains($pattern)) {
                            $resolved[] = $pattern;
                        }
                    }
                }

                $resolved = array_unique($resolved);
                sort($resolved);
                $role->syncPermissions($resolved);
                $this->line(" {$roleName} → " . count($resolved) . " permissions");
            }

            $this->info(" Default roles reset.");
        }

        // Clear cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->newLine();
        $this->info(" Permission cache cleared.");

        return self::SUCCESS;
    }
}
