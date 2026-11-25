<?php

declare(strict_types=1);

namespace SanctumManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use SanctumManager\Contracts\TokenManagerContract;
use SanctumManager\Models\PersonalAccessToken;
use Throwable;

/**
 * Revokes a single personal access token or all/scoped tokens belonging to a user.
 */
class RevokeTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sanctum-manager:revoke
                            {token? : Token ID to revoke}
                            {--user= : Revoke all tokens for this user ID or email}
                            {--model= : User model class}
                            {--scope= : Only revoke tokens having this scope (with --user)}
                            {--reason= : Revocation reason}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revoke a personal access token or tokens belonging to a user';

    /**
     * Execute the console command.
     */
    public function handle(TokenManagerContract $tokenManager): int
    {
        $tokenArgument = $this->argument('token');
        $userOption = $this->option('user');
        $scopeOption = $this->option('scope');
        $reasonOption = $this->option('reason');

        $tokenId = is_string($tokenArgument) && $tokenArgument !== '' ? $tokenArgument : null;
        $userIdentifier = is_string($userOption) && $userOption !== '' ? $userOption : null;
        $scope = is_string($scopeOption) && $scopeOption !== '' ? $scopeOption : null;
        $reason = is_string($reasonOption) && $reasonOption !== '' ? $reasonOption : null;

        if ($tokenId === null && $userIdentifier === null) {
            $this->components->error('You must provide either a token ID argument or the --user option.');

            return self::FAILURE;
        }

        if ($tokenId !== null) {
            /** @var PersonalAccessToken|null $token */
            $token = PersonalAccessToken::query()->find($tokenId);

            if ($token === null) {
                $this->components->error("Token [{$tokenId}] was not found.");

                return self::FAILURE;
            }

            try {
                $revoked = $tokenManager->revoke($token, $reason);
            } catch (Throwable $exception) {
                $this->components->error($exception->getMessage());

                return self::FAILURE;
            }

            if ($revoked) {
                $this->components->info("Token [{$tokenId}] has been revoked.");
            } else {
                $this->components->warn("Token [{$tokenId}] could not be revoked or was already revoked.");
            }

            return self::SUCCESS;
        }

        $user = $this->resolveUser((string) $userIdentifier);

        if ($user === null) {
            return self::FAILURE;
        }

        try {
            if ($scope !== null) {
                $count = $tokenManager->revokeWithScope($user, $scope, $reason);
                $this->components->info("Revoked {$count} token(s) with scope [{$scope}] for user [{$userIdentifier}].");
            } else {
                $count = $tokenManager->revokeAll($user, $reason);
                $this->components->info("Revoked {$count} token(s) for user [{$userIdentifier}].");
            }
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Resolve the target user model by numeric primary key or email address.
     */
    protected function resolveUser(string $identifier): ?Model
    {
        $modelOption = $this->option('model');
        $modelClass = is_string($modelOption) && $modelOption !== ''
            ? $modelOption
            : config('auth.providers.users.model');

        if (! is_string($modelClass) || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            $this->components->error(
                sprintf('Invalid user model class [%s]. Specify a valid Eloquent model via --model.', is_string($modelClass) ? $modelClass : 'null')
            );

            return null;
        }

        /** @var Model|null $user */
        $user = is_numeric($identifier)
            ? $modelClass::query()->find((int) $identifier)
            : $modelClass::query()->where('email', $identifier)->first();

        if ($user === null) {
            $this->components->error("User [{$identifier}] was not found in [{$modelClass}].");

            return null;
        }

        return $user;
    }
}
