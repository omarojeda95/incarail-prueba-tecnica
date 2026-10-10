<?php

namespace App\Application\Access;

use App\Domain\Access\Exceptions\AccessRuleViolation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UserManagement
{
    /** @param  array{name: string, email: string, password: string, roles?: list<string>}  $data */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'is_active' => true,
            ]);
            $user->syncRoles($data['roles'] ?? []);

            return $user;
        });
    }

    /** @param  array{name?: string, email?: string, password?: string|null, roles?: list<string>}  $data */
    public function update(User $user, array $data): User
    {
        if (isset($data['roles']) && $user->hasRole('admin') && ! in_array('admin', $data['roles'], true)) {
            $this->ensureAnotherActiveAdminExists($user, 'No se puede quitar el rol admin al último administrador activo.');
        }

        return DB::transaction(function () use ($user, $data) {
            $user->fill(array_filter([
                'name' => $data['name'] ?? null,
                'email' => isset($data['email']) ? strtolower($data['email']) : null,
                'password' => $data['password'] ?? null,
            ]))->save();

            if (isset($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            return $user;
        });
    }

    public function deactivate(User $user, User $actor): User
    {
        if ($user->is($actor)) {
            throw new AccessRuleViolation('No puedes desactivar tu propia cuenta.');
        }

        if ($user->hasRole('admin')) {
            $this->ensureAnotherActiveAdminExists($user, 'No se puede desactivar al último administrador activo.');
        }

        $user->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();

        // Kill every open session of that user right away.
        $user->tokens()->update(['revoked' => true]);

        return $user;
    }

    public function activate(User $user): User
    {
        $user->forceFill(['is_active' => true, 'deactivated_at' => null])->save();

        return $user;
    }

    private function ensureAnotherActiveAdminExists(User $user, string $message): void
    {
        $others = User::role('admin')->where('is_active', true)->whereKeyNot($user->getKey())->exists();

        if (! $others) {
            throw new AccessRuleViolation($message);
        }
    }
}
