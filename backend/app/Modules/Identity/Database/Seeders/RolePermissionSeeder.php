<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class RolePermissionSeeder extends Seeder
{
    /**
     * Reference RBAC data. Roles and permissions are system-managed; the API
     * never creates or deletes them.
     */
    public function run(): void
    {
        $permissions = [
            'participant.vote' => 'Cast a final vote on a published article',
            'participant.comment' => 'Comment on published articles',
            'participant.view_results' => 'View live voting results',
            'article.create' => 'Create article drafts',
            'article.update_own' => 'Update own articles within the workflow',
            'article.publish_own' => 'Directly publish own articles',
            'article.publish_any' => 'Publish any article',
            'article.unpublish' => 'Unpublish / remove any published article',
            'article.feature' => 'Feature articles',
            'comment.moderate' => 'Moderate and remove comments',
            'content.manage' => 'Manage public CMS content',
            'user.manage' => 'Manage users, roles and invitations',
            'publisher.manage' => 'Grant and revoke publisher permissions',
            'analytics.view_own' => 'View analytics for own articles',
            'analytics.view_all' => 'View platform-wide analytics',
            'settings.manage' => 'Manage platform settings',
            'audit.view' => 'View audit logs',
        ];

        $roles = [
            'participant' => 'Registered reader who can vote and comment',
            'publisher' => 'Authorized article publisher',
            'administrator' => 'Platform administrator',
        ];

        DB::transaction(function () use ($permissions, $roles): void {
            foreach ($permissions as $name => $description) {
                Permission::query()->firstOrCreate(
                    ['name' => $name],
                    ['description' => $description],
                );
            }

            foreach ($roles as $name => $description) {
                Role::query()->firstOrCreate(
                    ['name' => $name],
                    ['description' => $description, 'is_system' => true],
                );
            }

            $matrix = [
                'participant' => [
                    'participant.vote',
                    'participant.comment',
                    'participant.view_results',
                ],
                'publisher' => [
                    'article.create',
                    'article.update_own',
                    'article.publish_own',
                    'analytics.view_own',
                ],
                'administrator' => array_keys($permissions),
            ];

            foreach ($matrix as $roleName => $permissionNames) {
                $role = Role::query()->where('name', $roleName)->firstOrFail();

                $ids = Permission::query()
                    ->whereIn('name', $permissionNames)
                    ->pluck('id')
                    ->all();

                $role->permissions()->sync($ids);
            }
        });
    }
}
