<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Validation and sign-in messages come back in the language chosen on the
 * site (ar / fr / es; English otherwise), with friendly field names.
 */
class ValidationLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_language_has_every_validation_rule(): void
    {
        $english = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');

        foreach (['ar', 'fr', 'es'] as $locale) {
            $ours = require lang_path("{$locale}/validation.php");
            $this->assertSame([], array_values(array_diff(array_keys($english), array_keys($ours))), "lang/{$locale}/validation.php is missing rules");

            foreach (['auth', 'passwords', 'pagination'] as $file) {
                $this->assertFileExists(lang_path("{$locale}/{$file}.php"));
            }
        }
    }

    public function test_messages_follow_the_site_language(): void
    {
        $cases = [
            'ar' => 'حقل البريد الإلكتروني مطلوب.',
            'fr' => 'Le champ adresse e-mail est obligatoire.',
            'es' => 'El campo correo electrónico es obligatorio.',
            'en' => 'The email field is required.',
        ];

        foreach ($cases as $locale => $expected) {
            app()->setLocale($locale);
            $errors = Validator::make([], ['email' => 'required'])->errors();
            $this->assertSame($expected, $errors->first('email'), $locale);
        }
    }

    public function test_a_failed_sign_in_is_explained_in_arabic(): void
    {
        User::factory()->create(['email' => 'ada@example.test']);

        $this->withHeader('Accept-Language', 'ar')
            ->post('/login', ['email' => 'ada@example.test', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'بيانات الاعتماد هذه غير متطابقة مع سجلاتنا.']);
    }
}
