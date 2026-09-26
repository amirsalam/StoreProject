<x-mail::message>
# New contact message

**From:** {{ $contactMessage->name }} &lt;{{ $contactMessage->email }}&gt;
**Subject:** {{ $contactMessage->subject }}

{{ $contactMessage->message }}

<x-mail::button :url="route('admin.contact.index')">
Open the inbox
</x-mail::button>

Reply to this email to answer {{ $contactMessage->name }} directly.
</x-mail::message>
