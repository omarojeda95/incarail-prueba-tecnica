<?php

namespace App\Application\Access;

use App\Domain\Access\Exceptions\AccountDisabled;
use App\Domain\Access\Exceptions\InvalidCredentials;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\PersonalAccessTokenResult;

/** Login: verifies the credentials and issues a Passport personal access token. */
final class IssueAccessToken
{
    public function handle(string $email, string $password): PersonalAccessTokenResult
    {
        $user = User::query()->where('email', strtolower($email))->first();

        // Hash::check runs even when the user does not exist to keep timing similar.
        if (! Hash::check($password, $user->password ?? Hash::make(str()->random())) || ! $user) {
            throw new InvalidCredentials;
        }

        if (! $user->is_active) {
            throw new AccountDisabled;
        }

        return $user->createToken('spa');
    }
}
