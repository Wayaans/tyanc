<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('apps')) {
            return;
        }

        DB::transaction(function (): void {
            $this->upsertApp(
                key: 'tyanc',
                label: 'Tyanc',
                routePrefix: mb_trim((string) config('tyanc.admin_path', 'tyanc'), '/'),
                icon: 'app-logo',
                permissionNamespace: 'tyanc',
                enabled: true,
                sortOrder: 0,
                isSystem: true,
            );

            $this->upsertApp(
                key: 'demo',
                label: 'Demo',
                routePrefix: mb_trim((string) config('tyanc.demo_path', 'demo'), '/'),
                icon: 'flask-conical',
                permissionNamespace: 'demo',
                enabled: true,
                sortOrder: 10,
                isSystem: true,
            );
        });
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('apps')) {
            return;
        }

        DB::table('apps')
            ->where('key', 'demo')
            ->update([
                'is_system' => false,
                'updated_at' => now(),
            ]);
    }

    private function upsertApp(
        string $key,
        string $label,
        string $routePrefix,
        string $icon,
        string $permissionNamespace,
        bool $enabled,
        int $sortOrder,
        bool $isSystem,
    ): void {
        $existing = DB::table('apps')->where('key', $key)->first();

        if ($existing === null) {
            DB::table('apps')->insert([
                'id' => (string) Str::uuid(),
                'key' => $key,
                'label' => $label,
                'route_prefix' => $routePrefix,
                'icon' => $icon,
                'permission_namespace' => $permissionNamespace,
                'enabled' => $enabled,
                'sort_order' => $sortOrder,
                'is_system' => $isSystem,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('apps')
            ->where('key', $key)
            ->update([
                'label' => $label,
                'route_prefix' => $routePrefix,
                'icon' => $icon,
                'permission_namespace' => $permissionNamespace,
                'enabled' => $enabled,
                'sort_order' => $sortOrder,
                'is_system' => $isSystem,
                'updated_at' => now(),
            ]);
    }
};
