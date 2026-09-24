<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('contact');
    }

    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $message = ContactMessage::create($request->contactMessageAttributes());

        $this->notify($message);

        return back()->with('success', __('messages.contact.form.sent'));
    }

    /**
     * Email a copy to the address in config/contact.php, when one is set.
     * Storage is the source of truth (the admin inbox), so a mail failure
     * must never lose the message or fail the request.
     */
    private function notify(ContactMessage $message): void
    {
        $recipient = config('contact.notify_to');

        if (! is_string($recipient) || $recipient === '') {
            return;
        }

        try {
            Mail::to($recipient)->send(new ContactMessageReceived($message));
        } catch (\Throwable $e) {
            Log::error('Contact notification failed', [
                'contact_message_id' => $message->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
