<?php

namespace Tests\Feature\Contact;

use App\Http\Middleware\SetLocale;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('');
        Mail::fake();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Amina Haddad',
            'email' => 'amina@example.com',
            'subject' => 'Question about licenses',
            'message' => 'Can I move a license to another site after activation?',
        ], $overrides);
    }

    public function test_contact_page_renders_for_guests(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('contact'));
    }

    public function test_a_guest_can_send_a_message(): void
    {
        $this->post(route('contact.store'), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $message = ContactMessage::query()->sole();
        $this->assertSame('amina@example.com', $message->email);
        $this->assertSame(ContactMessage::STATUS_NEW, $message->status);
        $this->assertNull($message->user_id);
    }

    public function test_a_logged_in_sender_is_linked_to_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('contact.store'), $this->payload())
            ->assertRedirect();

        $this->assertSame($user->id, ContactMessage::query()->sole()->user_id);
    }

    public function test_it_validates_required_fields(): void
    {
        $this->post(route('contact.store'), ['name' => '', 'email' => 'not-an-email', 'subject' => '', 'message' => 'short'])
            ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_the_honeypot_field_rejects_bots(): void
    {
        $this->post(route('contact.store'), $this->payload(['website' => 'http://spam.example']))
            ->assertSessionHasErrors('website');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_submissions_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('contact.store'), $this->payload(['subject' => "Subject {$i}"]))->assertRedirect();
        }

        $this->post(route('contact.store'), $this->payload(['subject' => 'One too many']))
            ->assertStatus(429);

        $this->assertDatabaseCount('contact_messages', 5);
    }

    public function test_no_email_is_sent_when_no_notify_address_is_configured(): void
    {
        config(['contact.notify_to' => null]);

        $this->post(route('contact.store'), $this->payload())->assertRedirect();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_an_email_goes_out_when_a_notify_address_is_configured(): void
    {
        config(['contact.notify_to' => 'inbox@example.com']);

        $this->post(route('contact.store'), $this->payload())->assertRedirect();

        Mail::assertSent(ContactMessageReceived::class, function (ContactMessageReceived $mail) {
            return $mail->hasTo('inbox@example.com')
                && $mail->hasReplyTo('amina@example.com')
                && $mail->contactMessage->subject === 'Question about licenses';
        });
    }

    public function test_the_message_is_still_stored_when_the_mailer_fails(): void
    {
        config(['contact.notify_to' => 'inbox@example.com']);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $this->post(route('contact.store'), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_every_locale_defines_the_same_contact_keys(): void
    {
        $expected = $this->flattenKeys(trans('messages.contact', [], 'en'));
        $this->assertNotEmpty($expected);

        foreach (SetLocale::SUPPORTED as $locale) {
            // fallback=false: a missing block must not silently resolve to en
            $this->assertSame(
                $expected,
                $this->flattenKeys(trans()->get('messages.contact', [], $locale, false)),
                "lang/{$locale}/messages.php 'contact' keys differ from en",
            );
        }
    }

    /** @return list<string> */
    private function flattenKeys(mixed $node, string $prefix = ''): array
    {
        if (! is_array($node)) {
            return [$prefix];
        }

        $keys = [];
        foreach ($node as $key => $child) {
            array_push($keys, ...$this->flattenKeys($child, ltrim("{$prefix}.{$key}", '.')));
        }
        sort($keys);

        return $keys;
    }
}
