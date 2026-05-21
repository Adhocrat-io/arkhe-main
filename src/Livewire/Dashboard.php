<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire;

use Arkhe\Main\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Dashboard extends Component
{
    public function render(UserRepositoryInterface $repository): View
    {
        $totalUsers = $repository->newModel()::query()->count();

        $byRole = Role::query()
            ->withCount('users')
            ->orderBy('name')
            ->get(['name'])
            ->map(fn (Role $role): array => [
                'name'  => $role->name,
                'count' => (int) $role->users_count,
            ])
            ->all();

        return view('arkhe::livewire.dashboard', [
            'totalUsers' => $totalUsers,
            'byRole'     => $byRole,
        ])->layout((string) config('arkhe.admin.layout', config('arkhe.layout', 'arkhe::layouts.app')));
    }
}
