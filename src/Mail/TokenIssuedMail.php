<?php

declare(strict_types=1);

namespace SanctumManager\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TokenIssuedMail extends Mailable
{
    use Queueable;

    /**
     * @param  array<int, string>  $scopes
     * @param  array<int, string>  $allowedIps
     * @param  array<int, string>  $allowedOrigins
     */
    public function __construct(
        public string $tokenName,
        public array $scopes = [],
        public ?string $ownerName = null,
        public ?string $ownerEmail = null,
        public ?string $expiresAt = null,
        public array $allowedIps = [],
        public array $allowedOrigins = [],
        public ?int $maxUses = null,
        public string $occurredAt = '',
    ) {
        if ($this->occurredAt === '') {
            $this->occurredAt = now()->toIso8601String();
        }
    }

    public function envelope(): Envelope
    {
        $translated = __('sanctum-manager::sanctum-manager.mail.issued_subject', ['name' => $this->tokenName]);
        $title = is_string($translated) ? $translated : "New API Token Issued: {$this->tokenName}";

        return new Envelope(
            from: $this->resolveFromAddress(),
            subject: $this->formatSubject($title),
        );
    }

    public function content(): Content
    {
        /** @var view-string $view */
        $view = 'sanctum-manager::mail.token-issued';

        return new Content(
            view: $view,
        );
    }

    private function formatSubject(string $title): string
    {
        $prefix = config('sanctum-manager.mail.subject_prefix', '[Sanctum Manager]');
        $prefixString = is_string($prefix) ? trim($prefix) : '[Sanctum Manager]';

        return $prefixString !== '' ? "{$prefixString} {$title}" : $title;
    }

    private function resolveFromAddress(): ?Address
    {
        $address = config('sanctum-manager.mail.from.address');
        if (! is_string($address) || trim($address) === '') {
            return null;
        }

        $name = config('sanctum-manager.mail.from.name');
        $resolvedName = is_string($name) && trim($name) !== '' ? trim($name) : null;

        return new Address(trim($address), $resolvedName);
    }
}
