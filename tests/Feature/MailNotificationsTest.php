<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use SanctumManager\DataTransferObjects\TokenOptions;
use SanctumManager\Facades\SanctumManager;
use SanctumManager\Listeners\SendTokenLifecycleMail;
use SanctumManager\Mail\TestNotificationMail;
use SanctumManager\Mail\TokenIssuedMail;
use SanctumManager\Mail\TokenRevokedMail;
use SanctumManager\Mail\TokenRotatedMail;
use SanctumManager\Models\PersonalAccessToken;
use SanctumManager\Tests\TestCase;

class MailNotificationsTest extends TestCase
{
    #[Test]
    public function it_does_not_send_any_mail_when_disabled_by_default(): void
    {
        Mail::fake();

        $this->assertFalse((bool) config('sanctum-manager.mail.enabled'));

        $user = $this->createUser('owner@example.com', 'Owner User');

        $issued = $user->issueToken('Default Disabled Token', ['orders:read']);
        /** @var PersonalAccessToken $token */
        $token = $issued->accessToken;

        $rotated = SanctumManager::rotate($token, gracePeriodMinutes: 15);
        /** @var PersonalAccessToken $newToken */
        $newToken = $rotated->newToken->accessToken;

        SanctumManager::revoke($newToken, 'cleanup');

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    #[Test]
    public function it_sends_token_issued_mail_to_owner_and_configured_recipients_without_leaking_secret(): void
    {
        Mail::fake();

        config()->set('sanctum-manager.mail.enabled', true);
        config()->set('sanctum-manager.mail.notify_token_owner', true);
        config()->set('sanctum-manager.mail.recipients', [
            'security@example.com',
            'OWNER@example.com', // duplicate of owner email (case-insensitive)
            'invalid-email',
            '',
        ]);
        config()->set('sanctum-manager.mail.subject_prefix', '[MyApp Security]');
        config()->set('sanctum-manager.mail.from.address', 'noreply@example.com');
        config()->set('sanctum-manager.mail.from.name', 'MyApp Bot');

        $user = $this->createUser('owner@example.com', 'Alice Owner');

        $options = TokenOptions::make()
            ->expiresInDays(7)
            ->allowIps(['192.168.1.10', '10.0.0.0/8'])
            ->allowOrigins(['https://dashboard.example.com'])
            ->maxUses(25);

        $issued = $user->issueToken('CI Deploy Token', ['deployments:write', 'logs:read'], $options);
        $plainTextToken = $issued->plainTextToken;
        $rawSecret = explode('|', $plainTextToken, 2)[1] ?? $plainTextToken;

        Mail::assertSent(TokenIssuedMail::class, 1);
        Mail::assertSent(TokenIssuedMail::class, function (TokenIssuedMail $mail) use ($plainTextToken, $rawSecret): bool {
            $this->assertTrue($mail->hasTo('owner@example.com'));
            $this->assertTrue($mail->hasTo('security@example.com'));
            $this->assertCount(2, $mail->to);

            $envelope = $mail->envelope();
            $this->assertSame('[MyApp Security] Token Issued: CI Deploy Token', $envelope->subject);
            $this->assertNotNull($envelope->from);
            $this->assertSame('noreply@example.com', $envelope->from->address);
            $this->assertSame('MyApp Bot', $envelope->from->name);

            $html = $mail->render();
            $this->assertStringContainsString('CI Deploy Token', $html);
            $this->assertStringContainsString('Alice Owner (owner@example.com)', $html);
            $this->assertStringContainsString('deployments:write', $html);
            $this->assertStringContainsString('192.168.1.10', $html);
            $this->assertStringContainsString('https://dashboard.example.com', $html);
            $this->assertStringContainsString('25', $html);

            // Plain-text secret must NEVER appear in rendered HTML
            $this->assertStringNotContainsString($plainTextToken, $html);
            $this->assertStringNotContainsString($rawSecret, $html);

            return true;
        });
    }

    #[Test]
    public function it_supports_comma_separated_recipients_string_and_deduplicates(): void
    {
        Mail::fake();

        config()->set('sanctum-manager.mail.enabled', true);
        config()->set('sanctum-manager.mail.notify_token_owner', true);
        config()->set('sanctum-manager.mail.recipients', 'sec@example.com, audit@example.com, SEC@EXAMPLE.COM , bad@@email');

        $this->assertSame(
            ['sec@example.com', 'audit@example.com'],
            SendTokenLifecycleMail::resolveConfiguredRecipients(),
        );

        $user = $this->createUser('sec@example.com', 'Sec Owner');
        $user->issueToken('Comma Test Token', ['read']);

        Mail::assertSent(TokenIssuedMail::class, function (TokenIssuedMail $mail): bool {
            $this->assertTrue($mail->hasTo('sec@example.com'));
            $this->assertTrue($mail->hasTo('audit@example.com'));
            $this->assertCount(2, $mail->to);

            return true;
        });
    }

    #[Test]
    public function it_sends_token_revoked_mail_in_zero_migration_hard_delete_mode(): void
    {
        Mail::fake();

        config()->set('sanctum-manager.storage_mode', 'zero_migration');
        config()->set('sanctum-manager.security.keep_revoked_tokens', false);
        config()->set('sanctum-manager.mail.enabled', true);
        config()->set('sanctum-manager.mail.events.issued', false);
        config()->set('sanctum-manager.mail.events.revoked', true);

        $user = $this->createUser('bob@example.com', 'Bob User');
        $issued = $user->issueToken('Legacy Integration', ['read']);
        /** @var PersonalAccessToken $token */
        $token = $issued->accessToken;
        $tokenId = $token->id;

        SanctumManager::revoke($token, 'Suspected key exposure');

        $this->assertNull(PersonalAccessToken::query()->find($tokenId));

        Mail::assertSent(TokenRevokedMail::class, 1);
        Mail::assertSent(TokenRevokedMail::class, function (TokenRevokedMail $mail): bool {
            $this->assertTrue($mail->hasTo('bob@example.com'));
            $this->assertSame('Legacy Integration', $mail->tokenName);
            $this->assertSame('Suspected key exposure', $mail->reason);
            $this->assertSame('[Sanctum Manager] Token Revoked: Legacy Integration', $mail->envelope()->subject);

            $html = $mail->render();
            $this->assertStringContainsString('Legacy Integration', $html);
            $this->assertStringContainsString('Suspected key exposure', $html);
            $this->assertStringContainsString('Bob User (bob@example.com)', $html);

            return true;
        });
    }

    #[Test]
    public function it_sends_token_revoked_mail_in_extended_columns_soft_revoke_mode(): void
    {
        $this->enableExtendedMigrations();
        Mail::fake();

        config()->set('sanctum-manager.security.keep_revoked_tokens', true);
        config()->set('sanctum-manager.mail.enabled', true);
        config()->set('sanctum-manager.mail.events.issued', false);
        config()->set('sanctum-manager.mail.events.revoked', true);

        $user = $this->createUser('carol@example.com', 'Carol Admin');
        $issued = $user->issueToken('Billing Worker', ['invoices:read']);
        /** @var PersonalAccessToken $token */
        $token = $issued->accessToken;

        SanctumManager::revoke($token, 'Employee offboarded');

        $refreshed = PersonalAccessToken::query()->find($token->id);
        $this->assertNotNull($refreshed);
        $this->assertTrue($refreshed->isRevoked());

        Mail::assertSent(TokenRevokedMail::class, 1);
        Mail::assertSent(TokenRevokedMail::class, function (TokenRevokedMail $mail): bool {
            $this->assertTrue($mail->hasTo('carol@example.com'));
            $this->assertSame('Billing Worker', $mail->tokenName);
            $this->assertSame('Employee offboarded', $mail->reason);

            $html = $mail->render();
            $this->assertStringContainsString('Billing Worker', $html);
            $this->assertStringContainsString('Employee offboarded', $html);

            return true;
        });
    }

    #[Test]
    public function it_sends_token_rotated_mail_with_grace_period_and_never_leaks_new_secret(): void
    {
        Mail::fake();

        config()->set('sanctum-manager.mail.enabled', true);
        config()->set('sanctum-manager.mail.events.issued', false);
        config()->set('sanctum-manager.mail.events.rotated', true);

        $user = $this->createUser('dave@example.com', 'Dave Dev');
        $issued = $user->issueToken('Mobile Client', ['profile:read']);
        /** @var PersonalAccessToken $oldToken */
        $oldToken = $issued->accessToken;

        $rotated = SanctumManager::rotate($oldToken, gracePeriodMinutes: 45, newName: 'Mobile Client v2');
        $newPlainText = $rotated->newToken->plainTextToken;
        $newSecret = explode('|', $newPlainText, 2)[1] ?? $newPlainText;

        Mail::assertSent(TokenRotatedMail::class, 1);
        Mail::assertSent(TokenRotatedMail::class, function (TokenRotatedMail $mail) use ($newPlainText, $newSecret): bool {
            $this->assertTrue($mail->hasTo('dave@example.com'));
            $this->assertSame('Mobile Client', $mail->oldTokenName);
            $this->assertSame('Mobile Client v2', $mail->newTokenName);
            $this->assertNotEmpty($mail->graceExpiresAt);
            $this->assertSame('[Sanctum Manager] Token Rotated: Mobile Client', $mail->envelope()->subject);

            $html = $mail->render();
            $this->assertStringContainsString('Mobile Client', $html);
            $this->assertStringContainsString('Mobile Client v2', $html);
            $this->assertStringContainsString($mail->graceExpiresAt, $html);

            $this->assertStringNotContainsString($newPlainText, $html);
            $this->assertStringNotContainsString($newSecret, $html);

            return true;
        });
    }

    #[Test]
    public function it_respects_per_event_toggles(): void
    {
        Mail::fake();

        config()->set('sanctum-manager.mail.enabled', true);
        config()->set('sanctum-manager.mail.events.issued', false);
        config()->set('sanctum-manager.mail.events.revoked', true);
        config()->set('sanctum-manager.mail.events.rotated', false);

        $user = $this->createUser('eve@example.com', 'Eve');
        $issued = $user->issueToken('Scoped Toggle Token', ['read']);
        /** @var PersonalAccessToken $token */
        $token = $issued->accessToken;

        $rotated = SanctumManager::rotate($token, gracePeriodMinutes: 10);
        /** @var PersonalAccessToken $newToken */
        $newToken = $rotated->newToken->accessToken;

        SanctumManager::revoke($newToken, 'Manual revoke');

        Mail::assertNotSent(TokenIssuedMail::class);
        Mail::assertNotSent(TokenRotatedMail::class);
        Mail::assertSent(TokenRevokedMail::class, 1);

        // Now invert toggles: issued = true, revoked = false, rotated = true
        Mail::fake();
        config()->set('sanctum-manager.mail.events.issued', true);
        config()->set('sanctum-manager.mail.events.revoked', false);
        config()->set('sanctum-manager.mail.events.rotated', true);

        $issued2 = $user->issueToken('Second Token', ['read']);
        /** @var PersonalAccessToken $token2 */
        $token2 = $issued2->accessToken;

        $rotated2 = SanctumManager::rotate($token2, gracePeriodMinutes: 10);
        /** @var PersonalAccessToken $newToken2 */
        $newToken2 = $rotated2->newToken->accessToken;

        SanctumManager::revoke($newToken2, 'Silent revoke');

        Mail::assertSent(TokenIssuedMail::class, 1);
        Mail::assertSent(TokenRotatedMail::class, 1);
        Mail::assertNotSent(TokenRevokedMail::class);
    }

    #[Test]
    public function it_sends_only_to_configured_recipients_when_notify_token_owner_is_false(): void
    {
        Mail::fake();

        config()->set('sanctum-manager.mail.enabled', true);
        config()->set('sanctum-manager.mail.notify_token_owner', false);
        config()->set('sanctum-manager.mail.recipients', ['ops@example.com', 'secops@example.com']);

        $user = $this->createUser('owner@example.com', 'Owner');
        $user->issueToken('Ops Only Token', ['read']);

        Mail::assertSent(TokenIssuedMail::class, function (TokenIssuedMail $mail): bool {
            $this->assertFalse($mail->hasTo('owner@example.com'));
            $this->assertTrue($mail->hasTo('ops@example.com'));
            $this->assertTrue($mail->hasTo('secops@example.com'));
            $this->assertCount(2, $mail->to);

            return true;
        });
    }

    #[Test]
    public function it_queues_mailable_cleanly_even_after_revoked_token_is_hard_deleted(): void
    {
        Mail::fake();

        config()->set('sanctum-manager.storage_mode', 'zero_migration');
        config()->set('sanctum-manager.security.keep_revoked_tokens', false);
        config()->set('sanctum-manager.mail.enabled', true);
        config()->set('sanctum-manager.mail.events.issued', false);
        config()->set('sanctum-manager.mail.events.revoked', true);
        config()->set('sanctum-manager.mail.queue', true);
        config()->set('sanctum-manager.mail.queue_connection', 'redis');
        config()->set('sanctum-manager.mail.queue_name', 'notifications');

        $user = $this->createUser('queued@example.com', 'Queued User');
        $issued = $user->issueToken('Ephemeral Token', ['read']);
        /** @var PersonalAccessToken $token */
        $token = $issued->accessToken;
        $tokenId = $token->id;

        SanctumManager::revoke($token, 'Hard deleted before queue worker runs');

        $this->assertNull(PersonalAccessToken::query()->find($tokenId));

        Mail::assertNothingSent();
        Mail::assertQueued(TokenRevokedMail::class, 1);
        Mail::assertQueued(TokenRevokedMail::class, function (TokenRevokedMail $mail): bool {
            $this->assertSame('redis', $mail->connection);
            $this->assertSame('notifications', $mail->queue);
            $this->assertTrue($mail->hasTo('queued@example.com'));

            // Simulate queue worker serialization/deserialization after the DB row is gone
            /** @var TokenRevokedMail $unserialized */
            $unserialized = unserialize(serialize($mail));
            $html = $unserialized->render();

            $this->assertStringContainsString('Ephemeral Token', $html);
            $this->assertStringContainsString('Hard deleted before queue worker runs', $html);

            return true;
        });
    }

    #[Test]
    public function it_renders_test_notification_mail_cleanly(): void
    {
        config()->set('sanctum-manager.mail.subject_prefix', '[Sanctum Test]');

        $mail = new TestNotificationMail(
            recipientEmail: 'admin@example.com',
            sentAt: '2026-11-25T13:15:00+00:00',
        );

        $this->assertSame('[Sanctum Test] Test Notification', $mail->envelope()->subject);

        $html = $mail->render();
        $this->assertStringContainsString('Mail notifications are working', $html);
        $this->assertStringContainsString('admin@example.com', $html);
        $this->assertStringContainsString('2026-11-25T13:15:00+00:00', $html);
    }
}
