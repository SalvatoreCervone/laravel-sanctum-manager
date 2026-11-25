<?php

declare(strict_types=1);

namespace SanctumManager\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TestNotificationMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $recipientEmail,
        public string $sentAt = '',
    ) {
        if ($this->sentAt === '') {
            $this->sentAt = now()->toIso8601String();
        }
    }

    public function envelope(): Envelope
    {
        $translated = __('sanctum-manager::sanctum-manager.mail.test_subject');
        $title = is_string($translated) ? $translated : 'Test Notification';

        return new Envelope(
            from: $this->resolveFromAddress(),
            subject: $this->formatSubject($title),
        );
    }

    public function content(): Content
    {
        /** @var view-string $view */
        $view = 'sanctum-manager::mail.test-notification';

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
