<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMailSettingsRequest;
use App\Models\ActivityLog;
use App\Services\BrandingService;
use App\Services\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Email: configure the SMTP server for all outgoing email and send
 * a test message. Replaces editing MAIL_* in .env.
 */
class MailSettingsController extends Controller
{
    public function __construct(private readonly MailSettings $settings) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('admin/mail/edit', [
            'settings' => $this->settings->forForm(),
            'encryptions' => MailSettings::ENCRYPTIONS,
            'testRecipient' => $request->user()->email,
        ]);
    }

    public function update(UpdateMailSettingsRequest $request): RedirectResponse
    {
        $this->settings->save($request->validated());

        ActivityLog::record('mail_settings.updated', $request->user(), [
            'enabled' => $request->boolean('enabled'),
            'host' => $request->validated('host'),
        ]);

        return back()->with('success', __('Email settings saved. Send a test email to check them.'));
    }

    public function test(Request $request, BrandingService $branding): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'email', 'max:255']]);

        if (! $this->settings->forForm()['enabled']) {
            return back()->with('error', __('Turn on "Send emails through this server" and save first.'));
        }

        // Use the settings as saved now, not a mailer built earlier in this request.
        $this->settings->apply();
        $manager = app('mail.manager');
        if ($manager instanceof MailManager) {
            $manager->forgetMailers();
        }

        $brand = $branding->summary()['title'];

        try {
            Mail::raw(
                "This is a test email from {$brand}. If you can read it, your email settings work — order confirmations and other emails will be delivered.",
                fn ($message) => $message->to($data['to'])->subject("Test email from {$brand}"),
            );
        } catch (\Throwable $e) {
            // The SMTP server's own error (bad login, wrong port, refused
            // connection) is what the admin needs to fix the settings.
            return back()->with('error', __('The test email could not be sent: :error', ['error' => $e->getMessage()]));
        }

        return back()->with('success', __('Test email sent to :to. Check that inbox (and its spam folder).', ['to' => $data['to']]));
    }
}
