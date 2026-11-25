<?php

declare(strict_types=1);

namespace SanctumManager\Console\Commands;

use Illuminate\Console\Command;
use SanctumManager\Contracts\TokenManagerContract;
use SanctumManager\Models\PersonalAccessToken;
use Throwable;

/**
 * Rotates an existing personal access token with an optional grace period.
 */
class RotateTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sanctum-manager:rotate
                            {token : Token ID to rotate}
                            {--grace= : Grace period in minutes}
                            {--name= : Optional new token name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rotate an existing personal access token with an optional grace period';

    /**
     * Execute the console command.
     */
    public function handle(TokenManagerContract $tokenManager): int
    {
        $tokenArgument = $this->argument('token');

        if (! is_string($tokenArgument) || $tokenArgument === '') {
            $this->components->error('A valid token ID is required.');

            return self::FAILURE;
        }

        /** @var PersonalAccessToken|null $token */
        $token = PersonalAccessToken::query()->find($tokenArgument);

        if ($token === null) {
            $this->components->error("Token [{$tokenArgument}] was not found.");

            return self::FAILURE;
        }

        $graceOption = $this->option('grace');
        $graceMinutes = null;

        if ($graceOption !== null && $graceOption !== '') {
            if (! is_numeric($graceOption) || (int) $graceOption < 0) {
                $this->components->error('The --grace option must be a non-negative integer (minutes).');

                return self::FAILURE;
            }

            $graceMinutes = (int) $graceOption;
        }

        $nameOption = $this->option('name');
        $newName = is_string($nameOption) && $nameOption !== '' ? $nameOption : null;

        try {
            $result = $tokenManager->rotate($token, $graceMinutes, $newName);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $rotatedTokenName = $result->newToken->accessToken->getAttribute('name');

        $this->components->info('Personal access token rotated successfully.');
        $this->components->twoColumnDetail('Old Token ID', (string) $result->oldToken->getKey());
        $this->components->twoColumnDetail('New Token ID', (string) $result->newToken->accessToken->getKey());
        $this->components->twoColumnDetail('New Token Name', is_string($rotatedTokenName) ? $rotatedTokenName : $token->name);
        $this->components->twoColumnDetail('New Plain-Text Token', $result->newToken->plainTextToken);
        $this->components->twoColumnDetail('Grace Expires At', $result->graceExpiresAt->toIso8601String());
        $this->newLine();
        $this->components->warn('Store this plain-text token securely — it is shown only once and cannot be retrieved again.');

        return self::SUCCESS;
    }
}
