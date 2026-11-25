<?php

declare(strict_types=1);

namespace SanctumManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use SanctumManager\Contracts\TokenManagerContract;
use SanctumManager\DataTransferObjects\TokenOptions;
use Throwable;

/**
 * Issues a new Sanctum personal access token for a given user via CLI.
 */
class IssueTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sanctum-manager:issue
                            {user : User ID or email}
                            {name : Token name}
                            {--model= : User model class (defaults to config auth.providers.users.model)}
                            {--scopes=* : Scopes or @groups to assign}
                            {--expires= : Expiration in minutes}
                            {--ips=* : Allowed IPs/CIDRs}
                            {--origins=* : Allowed origins}
                            {--max-uses= : Maximum allowed uses}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Issue a new personal access token for a user';

    /**
     * Execute the console command.
     */
    public function handle(TokenManagerContract $tokenManager): int
    {
        $userIdentifier = $this->argument('user');
        $name = $this->argument('name');

        if (! is_string($userIdentifier) || $userIdentifier === '') {
            $this->components->error('A valid user ID or email is required.');

            return self::FAILURE;
        }

        if (! is_string($name) || $name === '') {
            $this->components->error('A valid token name is required.');

            return self::FAILURE;
        }

        $user = $this->resolveUser($userIdentifier);

        if ($user === null) {
            return self::FAILURE;
        }

        $options = TokenOptions::make();

        $expiresOption = $this->option('expires');
        if ($expiresOption !== null && $expiresOption !== '') {
            if (! is_numeric($expiresOption) || (int) $expiresOption <= 0) {
                $this->components->error('The --expires option must be a positive integer (minutes).');

                return self::FAILURE;
            }

            $options->expiresInMinutes((int) $expiresOption);
        }

        $maxUsesOption = $this->option('max-uses');
        if ($maxUsesOption !== null && $maxUsesOption !== '') {
            if (! is_numeric($maxUsesOption) || (int) $maxUsesOption <= 0) {
                $this->components->error('The --max-uses option must be a positive integer.');

                return self::FAILURE;
            }

            $options->maxUses((int) $maxUsesOption);
        }

        $ips = $this->normalizeStringList($this->option('ips'));
        if ($ips !== []) {
            $options->allowIps($ips);
        }

        $origins = $this->normalizeStringList($this->option('origins'));
        if ($origins !== []) {
            $options->allowOrigins($origins);
        }

        $scopes = $this->normalizeStringList($this->option('scopes'));

        try {
            $newAccessToken = $tokenManager->issue($user, $name, $scopes, $options);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $tokenName = $newAccessToken->accessToken->getAttribute('name');

        $this->components->info('Personal access token issued successfully.');
        $this->components->twoColumnDetail('Token ID', (string) $newAccessToken->accessToken->getKey());
        $this->components->twoColumnDetail('Name', is_string($tokenName) ? $tokenName : $name);
        $this->components->twoColumnDetail('Plain-Text Token', $newAccessToken->plainTextToken);
        $this->newLine();
        $this->components->warn('Store this plain-text token securely — it is shown only once and cannot be retrieved again.');

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

    /**
     * Normalize a multi-value CLI option into a clean list of non-empty strings.
     *
     * @return array<int, string>
     */
    protected function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var array<int, string> $items */
        $items = [];

        foreach ($value as $entry) {
            if (is_string($entry)) {
                $trimmed = trim($entry);
                if ($trimmed !== '') {
                    $items[] = $trimmed;
                }
            }
        }

        return $items;
    }
}
