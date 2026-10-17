{{-- Posts the signed payment request to CMI's hosted page (CmiController@redirect). --}}
@php
    $locale = app()->getLocale();
    $direction = \App\Http\Middleware\SetLocale::direction($locale);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('messages.checkout.cmi_redirect_title') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, sans-serif; background: #0f0f14; color: #e5e5e5; }
        main { text-align: center; padding: 24px; max-width: 420px; }
        button { margin-top: 16px; padding: 10px 20px; border: 0; border-radius: 8px; background: #7c6cf0; color: #fff; font-size: 15px; cursor: pointer; }
        p { color: #a3a3a3; }
    </style>
</head>
<body>
<main>
    <h1 style="font-size: 20px">{{ __('messages.checkout.cmi_redirect_title') }}</h1>
    <p>{{ __('messages.checkout.cmi_redirect_body') }}</p>

    <form id="cmi-form" method="POST" action="{{ $form['action'] }}">
        @foreach ($form['fields'] as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <button type="submit">{{ __('messages.checkout.cmi_redirect_button') }}</button>
    </form>
</main>
<script>document.getElementById('cmi-form').submit();</script>
</body>
</html>
