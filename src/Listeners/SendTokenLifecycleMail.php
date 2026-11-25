<?php

declare(strict_types=1);

namespace SanctumManager\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use SanctumManager\Events\TokenIssued;
use SanctumManager\Events\TokenRevoked;
use SanctumManager\Events\TokenRotated;
use SanctumManager\Mail\TokenIssuedMail;
use SanctumManager\Mail\TokenRevokedMail;
use SanctumManager\Mail\TokenRotatedMail;

class SendTokenLifecycleMail
{
    public function handle(TokenIssued|TokenRevoked|TokenRotated $event): void
    {
        if (! (bool) config('sanctum-manager.mail.enabled', false)) {
            return;
        }

        if ($event instanceof TokenIssued) {
            $this->handleIssued($event);

            return;
        }

        if ($event instanceof TokenRevoked) {
            $this->handleRevoked($event);

            return;
        }

        $this->handleRotated($event);
    }

    public function handleIssued(TokenIssued $event): void
    {
        if (! $this->isEventEnabled('issued')) {
            return;
        }

        $recipients = $this->resolveRecipients($event->tokenable);
        if ($recipients === []) {
            return;
        }

        $token = $event->accessToken;
        $scopes = $event->scopes !== [] ? $event->scopes : $token->getEffectiveScopes();

        $mailable = new TokenIssuedMail(
            tokenName: $token->name,
            scopes: array_values($scopes),
            ownerName: $this->extractOwnerName($event->tokenable),
            ownerEmail: $this->extractOwnerEmail($event->tokenable),
            expiresAt: $token->expires_at?->toIso8601String(),
            allowedIps: $token->getAllowedIps(),
            allowedOrigins: $token->getAllowedOrigins(),
            maxUses: $token->getMaxUses(),
            occurredAt: now()->toIso8601String(),
        );

        $this->deliver($mailable, $recipients);
    }

    public function handleRevoked(TokenRevoked $event): void
    {
        if (! $this->isEventEnabled('revoked')) {
            return;
        }

        $recipients = $this->resolveRecipients($event->tokenable);
        if ($recipients === []) {
            return;
        }

        $mailable = new TokenRevokedMail(
            tokenName: $event->token->name,
            reason: $event->reason,
            ownerName: $this->extractOwnerName($event->tokenable),
            ownerEmail: $this->extractOwnerEmail($event->tokenable),
            occurredAt: now()->toIso8601String(),
        );

        $this->deliver($mailable, $recipients);
    }

    public function handleRotated(TokenRotated $event): void
    {
        if (! $this->isEventEnabled('rotated')) {
            return;
        }

        $recipients = $this->resolveRecipients($event->tokenable);
        if ($recipients === []) {
            return;
        }

        $mailable = new TokenRotatedMail(
            oldTokenName: $event->oldToken->name,
            newTokenName: $event->newToken->name,
            graceExpiresAt: $event->graceExpiresAt->toIso8601String(),
            ownerName: $this->extractOwnerName($event->tokenable),
            ownerEmail: $this->extractOwnerEmail($event->tokenable),
            occurredAt: now()->toIso8601String(),
        );

        $this->deliver($mailable, $recipients);
    }

    /**
     * Resolve and validate configured static mail recipients.
     * Supports both an array of email addresses and a comma-separated string.
     *
     * @return array<int, string>
     */
    public static function resolveConfiguredRecipients(): array
    {
        $raw = config('sanctum-manager.mail.recipients', []);
        $candidates = [];

        if (is_string($raw)) {
            $candidates = explode(',', $raw);
        } elseif (is_array($raw)) {
            foreach ($raw as $item) {
                if (is_string($item)) {
                    foreach (explode(',', $item) as $part) {
                        $candidates[] = $part;
                    }
                }
            }
        }

        return self::normalizeEmails($candidates);
    }

    /**
     * @return array<int, string>
     */
    protected function resolveRecipients(?Model $tokenable): array
    {
        $candidates = [];

        if ((bool) config('sanctum-manager.mail.notify_token_owner', true)) {
            $ownerEmail = $this->extractOwnerEmail($tokenable);
            if ($ownerEmail !== null) {
                $candidates[] = $ownerEmail;
            }
        }

        foreach (static::resolveConfiguredRecipients() as $recipient) {
            $candidates[] = $recipient;
        }

        return self::normalizeEmails($candidates);
    }

    protected function isEventEnabled(string $eventKey): bool
    {
        if (! (bool) config('sanctum-manager.mail.enabled', false)) {
            return false;
        }

        return (bool) config("sanctum-manager.mail.events.{$eventKey}", true);
    }

    /**
     * @param  array<int, string>  $recipients
     */
    protected function deliver(TokenIssuedMail|TokenRevokedMail|TokenRotatedMail $mailable, array $recipients): void
    {
        if ((bool) config('sanctum-manager.mail.queue', false)) {
            $connection = config('sanctum-manager.mail.queue_connection');
            if (is_string($connection) && trim($connection) !== '') {
                $mailable->onConnection(trim($connection));
            }

            $queue = config('sanctum-manager.mail.queue_name');
            if (is_string($queue) && trim($queue) !== '') {
                $mailable->onQueue(trim($queue));
            }

            Mail::to($recipients)->queue($mailable);

            return;
        }

        Mail::to($recipients)->send($mailable);
    }

    protected function extractOwnerName(?Model $tokenable): ?string
    {
        if ($tokenable === null) {
            return null;
        }

        $name = $tokenable->getAttribute('name');
        if (! is_string($name)) {
            return null;
        }

        $trimmed = trim($name);

        return $trimmed !== '' ? $trimmed : null;
    }

    protected function extractOwnerEmail(?Model $tokenable): ?string
    {
        if ($tokenable === null) {
            return null;
        }

        $email = $tokenable->getAttribute('email');
        if (! is_string($email)) {
            return null;
        }

        $trimmed = trim($email);

        return filter_var($trimmed, FILTER_VALIDATE_EMAIL) !== false ? $trimmed : null;
    }

    /**
     * Filter valid emails and deduplicate case-insensitively.
     *
     * @param  array<int, string>  $emails
     * @return array<int, string>
     */
    private static function normalizeEmails(array $emails): array
    {
        $seen = [];
        $result = [];

        foreach ($emails as $email) {
            $trimmed = trim($email);
            if ($trimmed === '' || filter_var($trimmed, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $key = strtolower($trimmed);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $trimmed;
        }

        return $result;
    }
}
