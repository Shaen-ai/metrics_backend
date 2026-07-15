<?php

namespace Tests\Unit;

use App\Mail\ContactFormMailable;
use Tests\TestCase;

class ContactFormMailableTest extends TestCase
{
    public function test_rendered_email_includes_share_url(): void
    {
        $shareUrl = 'https://vista.tunzone.com/share/test-token';
        $mailable = new ContactFormMailable(
            'Jane Doe',
            'jane@example.com',
            'Please quote this room.',
            '+37499123456',
            'vista_price_quote',
            $shareUrl,
        );

        $html = $mailable->render();
        $text = view('emails.contact-form-text', [
            'senderName' => $mailable->senderName,
            'senderEmail' => $mailable->senderEmail,
            'bodyText' => $mailable->bodyText,
            'phone' => $mailable->phone,
            'source' => $mailable->source,
            'shareUrl' => $mailable->shareUrl,
        ])->render();

        $this->assertStringContainsString($shareUrl, $html);
        $this->assertStringContainsString('href="'.$shareUrl.'"', $html);
        $this->assertStringContainsString('Share URL: '.$shareUrl, $text);
    }
}
