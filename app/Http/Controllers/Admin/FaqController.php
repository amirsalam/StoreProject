<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\MovesSortOrder;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Requests\Admin\FaqRequest;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → FAQ: the homepage "Frequently asked" questions — add, edit,
 * delete, show/hide and reorder, each in every site language.
 */
class FaqController extends Controller
{
    use MovesSortOrder;

    public function index(): Response
    {
        return Inertia::render('admin/faqs/index', [
            'faqs' => Faq::query()->orderBy('sort_order')->orderBy('id')->get(),
            'locales' => SetLocale::SUPPORTED,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/faqs/create', ['locales' => SetLocale::SUPPORTED]);
    }

    public function store(FaqRequest $request): RedirectResponse
    {
        Faq::create([
            ...$request->faqData(),
            'sort_order' => (int) Faq::query()->max('sort_order') + 1,
        ]);

        return redirect()->route('admin.faqs.index')->with('success', __('Question added.'));
    }

    public function edit(Faq $faq): Response
    {
        return Inertia::render('admin/faqs/edit', [
            'faq' => $faq,
            'locales' => SetLocale::SUPPORTED,
        ]);
    }

    public function update(FaqRequest $request, Faq $faq): RedirectResponse
    {
        $faq->update($request->faqData());

        return redirect()->route('admin.faqs.index')->with('success', __('Question updated.'));
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return redirect()->route('admin.faqs.index')->with('success', __('Question deleted.'));
    }

    public function move(Request $request, Faq $faq): RedirectResponse
    {
        $this->moveInOrder($request, $faq);

        return back();
    }

    public function toggle(Faq $faq): RedirectResponse
    {
        $faq->update(['is_active' => ! $faq->is_active]);

        return back();
    }
}
