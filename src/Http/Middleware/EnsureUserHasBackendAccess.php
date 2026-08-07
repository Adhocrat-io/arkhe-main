<?php

declare(strict_types=1);

namespace Arkhe\Main\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the entire Arkhe backend area. Grants access when the user has
 * the configured permission (V3 path, `arkhe.backend_permission`) OR holds
 * one of the role keys listed under `arkhe.admin.roles` (V2 path). The dual
 * check lets V2-shaped configs (role-list only, no permission seeded) keep
 * working while V3 permission-based RBAC remains the default.
 */
class EnsureUserHasBackendAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! method_exists($user, 'can')) {
            abort(403);
        }

        $permission = (string) config('arkhe.backend_permission', 'access-backend');
        if ($user->can($permission)) {
            return $next($request);
        }

        $adminRoles = $this->resolveAdminRoles();
        if ($adminRoles !== [] && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($adminRoles)) {
            return $next($request);
        }

        abort(403);
    }

    /**
     * Resolve the role keys allowed in the admin area from `arkhe.admin.roles`,
     * mapping each key to its concrete role name via `arkhe.roles` when the
     * host app keeps the V3-style `key => label` map.
     *
     * @return array<int,string>
     */
    private function resolveAdminRoles(): array
    {
        $configured = (array) config('arkhe.admin.roles', []);
        $rolesMap = config('arkhe.roles');

        $resolved = [];
        foreach ($configured as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (is_array($rolesMap) && array_key_exists($key, $rolesMap) && is_string($rolesMap[$key])) {
                $resolved[] = $rolesMap[$key];

                continue;
            }

            // Clé absente de `arkhe.roles` : on la garde telle quelle, c'est
            // le chemin de compatibilité V2 où `admin.roles` liste des noms de
            // rôles bruts. Le retirer priverait ces apps d'accès au back-office
            // à la simple montée de version, sans qu'elles aient rien demandé.
            //
            // Le risque théorique — créer un rôle homonyme d'une clé orpheline
            // pour entrer sans `access-backend` — suppose de pouvoir créer un
            // rôle, ce que l'interface ne permet plus et ce que
            // `RoleService::create()` refuse à qui ne détient pas déjà les
            // permissions en jeu.
            $resolved[] = $key;
        }

        return $resolved;
    }
}
