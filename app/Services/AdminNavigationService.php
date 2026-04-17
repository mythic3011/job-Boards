<?php

namespace App\Services;

use App\Models\User;

class AdminNavigationService
{
    /**
     * @return list<array{
     *     permission:string,
     *     route_name:string,
     *     href:string,
     *     label:string,
     *     nav_label:string,
     *     summary:string,
     *     description:string
     * }>
     */
    public function destinationsFor(User $user): array
    {
        if (! $user->isAdmin()) {
            return [];
        }

        $destinations = [];

        foreach ($this->definitions() as $definition) {
            if (! $user->can($definition['permission'])) {
                continue;
            }

            $destinations[] = [
                'permission' => $definition['permission'],
                'route_name' => $definition['route_name'],
                'href' => route($definition['route_name']),
                'label' => $definition['label'],
                'nav_label' => $definition['nav_label'],
                'summary' => $definition['summary'],
                'description' => $definition['description'],
            ];
        }

        return $destinations;
    }

    /**
     * @return array{
     *     permission:string,
     *     route_name:string,
     *     href:string,
     *     label:string,
     *     nav_label:string,
     *     summary:string,
     *     description:string
     * }|null
     */
    public function primaryDestinationFor(User $user): ?array
    {
        return $this->destinationsFor($user)[0] ?? null;
    }

    /**
     * @return list<array{
     *     permission:string,
     *     route_name:string,
     *     label:string,
     *     nav_label:string,
     *     summary:string,
     *     description:string
     * }>
     */
    private function definitions(): array
    {
        return [
            [
                'permission' => 'admin.system.view',
                'route_name' => 'admin.dashboard',
                'label' => 'Open Admin Dashboard',
                'nav_label' => 'Dashboard',
                'summary' => 'Dashboard',
                'description' => 'Jump into the operational snapshot with platform-wide signals.',
            ],
            [
                'permission' => 'admin.system.view',
                'route_name' => 'admin.audit-logs.index',
                'label' => 'Audit Logs',
                'nav_label' => 'Audit Logs',
                'summary' => 'Audit',
                'description' => 'Review canonical audit evidence, probes, and security events.',
            ],
            [
                'permission' => 'admin.users.view',
                'route_name' => 'admin.users.index',
                'label' => 'Review Users',
                'nav_label' => 'Users',
                'summary' => 'Users',
                'description' => 'Inspect account hygiene, lockouts, and moderation tasks.',
            ],
            [
                'permission' => 'admin.jobs.view',
                'route_name' => 'admin.jobs.index',
                'label' => 'Check Jobs',
                'nav_label' => 'Jobs',
                'summary' => 'Jobs',
                'description' => 'Review live listings and marketplace quality.',
            ],
            [
                'permission' => 'admin.applications.view',
                'route_name' => 'admin.applications.index',
                'label' => 'Inspect Applications',
                'nav_label' => 'Applications',
                'summary' => 'Applications',
                'description' => 'Watch the response queue and dispute surfaces.',
            ],
            [
                'permission' => 'admin.settings.view',
                'route_name' => 'admin.settings.index',
                'label' => 'System Settings',
                'nav_label' => 'Settings',
                'summary' => 'Settings',
                'description' => 'Adjust protected platform configuration and operational defaults.',
            ],
        ];
    }
}
