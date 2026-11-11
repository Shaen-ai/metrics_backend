<?php

namespace Tests\Unit;

use App\Mail\FeatureRequestMailable;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FeatureRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
    }

    public function test_request_with_optional_email_is_sent_to_support(): void
    {
        config()->set('mail.contact_inbound.address', 'support@tunzone.com');
        Mail::fake();

        $this->postJson('/api/feature-requests', [
            'description' => 'I need a way to compare two room designs.',
            'email' => 'visitor@example.com',
            'page' => '/studio',
        ])->assertOk()->assertJson(['ok' => true]);

        Mail::assertSent(FeatureRequestMailable::class, function (FeatureRequestMailable $mail) {
            return $mail->hasTo('support@tunzone.com')
                && $mail->senderEmail === 'visitor@example.com'
                && $mail->page === '/studio';
        });
    }

    public function test_request_without_email_is_accepted(): void
    {
        config()->set('mail.contact_inbound.address', 'support@tunzone.com');
        Mail::fake();

        $this->postJson('/api/feature-requests', [
            'description' => 'Please add a faster way to choose a sofa.',
        ])->assertOk();

        Mail::assertSent(FeatureRequestMailable::class, fn (FeatureRequestMailable $mail) => $mail->senderEmail === null);
    }

    public function test_short_request_is_rejected(): void
    {
        Mail::fake();

        $this->postJson('/api/feature-requests', ['description' => 'short'])
            ->assertUnprocessable();

        Mail::assertNothingSent();
    }
}
