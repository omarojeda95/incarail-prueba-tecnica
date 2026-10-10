<?php

namespace App\Application\Access;

use App\Models\User;

/** Translates the user's permissions into CASL rules: [{action, subject}]. */
final class AbilityRules
{
    /** @return list<array{action: string, subject: string}> */
    public function for(User $user): array
    {
        $catalog = config('acl.permissions');

        return $user->getAllPermissions()
            ->pluck('name')
            ->filter(fn (string $name) => isset($catalog[$name]))
            ->sort()
            ->map(fn (string $name) => [
                'action' => $catalog[$name]['action'],
                'subject' => $catalog[$name]['subject'],
            ])
            ->values()
            ->all();
    }
}
