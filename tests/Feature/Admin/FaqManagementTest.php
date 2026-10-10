<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\SetLocale;
use App\Models\Faq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FaqManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'question' => ['en' => 'Do you offer refunds?', 'ar' => 'هل تقدمون استردادًا؟', 'fr' => '', 'es' => ''],
            'answer' => ['en' => 'Yes, within 14 days.', 'ar' => 'نعم، خلال 14 يومًا.', 'fr' => '', 'es' => ''],
            'is_active' => true,
        ], $overrides);
    }

    public function test_guests_and_non_admins_cannot_manage_faqs(): void
    {
        $this->get('/admin/faqs')->assertRedirect('/login');

        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get('/admin/faqs')->assertForbidden();
        $this->actingAs($user)->post('/admin/faqs', $this->payload())->assertForbidden();
    }

    public function test_the_migration_copies_the_original_questions_in_every_language(): void
    {
        $faqs = Faq::visible()->get();

        $this->assertCount(count(trans('messages.faq.items', [], 'en')), $faqs);
        foreach (SetLocale::SUPPORTED as $locale) {
            $this->assertSame(trans('messages.faq.items', [], $locale)[0]['q'], $faqs->first()->question[$locale]);
            $this->assertSame(trans('messages.faq.items', [], $locale)[0]['a'], $faqs->first()->answer[$locale]);
        }
    }

    public function test_admins_see_the_list(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/faqs')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/faqs/index')
                ->has('faqs', Faq::count())
                ->where('locales', SetLocale::SUPPORTED));
    }

    public function test_a_question_can_be_created(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/faqs', $this->payload())
            ->assertRedirect('/admin/faqs')
            ->assertSessionHas('success');

        $faq = Faq::query()->latest('id')->firstOrFail();
        $this->assertSame('Do you offer refunds?', $faq->question['en']);
        $this->assertSame('هل تقدمون استردادًا؟', $faq->question['ar']);
        $this->assertSame('', $faq->question['fr']);
        $this->assertTrue($faq->is_active);
        $this->assertSame(Faq::max('sort_order'), $faq->sort_order);
    }

    public function test_english_is_required_and_other_languages_are_optional(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/faqs', $this->payload(['question' => ['en' => ''], 'answer' => ['en' => '']]))
            ->assertSessionHasErrors(['question.en', 'answer.en']);

        $this->actingAs($admin)
            ->post('/admin/faqs', $this->payload(['question' => ['en' => str_repeat('Q', 256)]]))
            ->assertSessionHasErrors('question.en');

        $this->actingAs($admin)
            ->post('/admin/faqs', ['question' => ['en' => 'Only English?'], 'answer' => ['en' => 'Yes.']])
            ->assertSessionHasNoErrors();
    }

    public function test_unknown_locales_are_not_stored(): void
    {
        $this->actingAs($this->admin())->post('/admin/faqs', $this->payload([
            'question' => ['de' => 'Gibt es Rückerstattungen?'],
            'answer' => ['de' => 'Ja.'],
        ]));

        $faq = Faq::query()->latest('id')->firstOrFail();
        $this->assertSame(SetLocale::SUPPORTED, array_keys($faq->question));
    }

    public function test_a_question_can_be_updated(): void
    {
        $faq = Faq::query()->firstOrFail();

        $this->actingAs($this->admin())
            ->put("/admin/faqs/{$faq->id}", $this->payload([
                'question' => ['en' => 'Updated?', 'fr' => 'Mis à jour ?'],
                'is_active' => false,
            ]))
            ->assertRedirect('/admin/faqs');

        $faq->refresh();
        $this->assertSame('Updated?', $faq->question['en']);
        $this->assertSame('Mis à jour ?', $faq->question['fr']);
        $this->assertFalse($faq->is_active);
    }

    public function test_a_question_can_be_deleted(): void
    {
        $faq = Faq::query()->firstOrFail();

        $this->actingAs($this->admin())->delete("/admin/faqs/{$faq->id}")->assertRedirect('/admin/faqs');

        $this->assertModelMissing($faq);
    }

    public function test_questions_can_be_hidden_and_reordered(): void
    {
        $admin = $this->admin();
        [$first, $second] = Faq::visible()->take(2)->get()->all();

        $this->actingAs($admin)->post("/admin/faqs/{$second->id}/move", ['direction' => 'up']);
        $this->assertSame([$second->id, $first->id], Faq::visible()->take(2)->pluck('id')->all());

        $this->actingAs($admin)->post("/admin/faqs/{$second->id}/toggle");
        $this->assertFalse($second->fresh()->is_active);
        $this->assertSame($first->id, Faq::visible()->value('id'));
    }

    public function test_the_homepage_shows_visible_questions_in_the_visitors_language_with_english_fallback(): void
    {
        Faq::query()->delete();
        Faq::create([
            'question' => ['en' => 'Refunds?', 'ar' => 'استرداد؟', 'fr' => '', 'es' => ''],
            'answer' => ['en' => 'Within 14 days.', 'ar' => 'خلال 14 يومًا.', 'fr' => '', 'es' => ''],
            'sort_order' => 1,
            'is_active' => true,
        ]);
        Faq::create([
            'question' => ['en' => 'Hidden one'],
            'answer' => ['en' => 'Not shown'],
            'sort_order' => 2,
            'is_active' => false,
        ]);

        $this->withCookie(SetLocale::COOKIE_NAME, 'ar')->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('welcome')
                ->has('faqs', 1)
                ->where('faqs.0.q', 'استرداد؟')
                ->where('faqs.0.a', 'خلال 14 يومًا.'));

        $this->withCookie(SetLocale::COOKIE_NAME, 'fr')->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('faqs.0.q', 'Refunds?')
                ->where('faqs.0.a', 'Within 14 days.'));
    }

    public function test_the_homepage_section_is_empty_when_all_questions_are_removed(): void
    {
        Faq::query()->delete();

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->has('faqs', 0));
    }
}
