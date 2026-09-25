<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactMessageController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->string('search'),
            'status' => (string) $request->string('status'),
        ];

        $query = ContactMessage::query();

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('subject', 'like', $term);
            });
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        return Inertia::render('admin/contact/index', [
            'messages' => $query->latest('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'statuses' => $this->statuses(),
            'unreadCount' => ContactMessage::query()->unread()->count(),
        ]);
    }

    public function update(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', [
                ContactMessage::STATUS_NEW,
                ContactMessage::STATUS_READ,
                ContactMessage::STATUS_ARCHIVED,
            ])],
        ]);

        $contactMessage->update([
            'status' => $data['status'],
            'read_at' => $data['status'] === ContactMessage::STATUS_NEW
                ? null
                : ($contactMessage->read_at ?? now()),
        ]);

        return back()->with('success', 'Message updated.');
    }

    public function destroy(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->delete();

        return back()->with('success', 'Message deleted.');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return [
            ['value' => ContactMessage::STATUS_NEW, 'label' => 'New'],
            ['value' => ContactMessage::STATUS_READ, 'label' => 'Read'],
            ['value' => ContactMessage::STATUS_ARCHIVED, 'label' => 'Archived'],
        ];
    }
}
