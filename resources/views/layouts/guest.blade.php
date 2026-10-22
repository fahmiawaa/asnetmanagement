<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('images/icons/asnet-mark.svg') }}">
        <title>{{ config('app.name', 'ASNet Billing') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="stylesheet" href="{{ asset('css/asnet-ui-reference.css?v=12') }}">
    </head>
    <body class="guest-body">
        <div class="guest-shell">
            <div class="guest-visual">
                <div class="guest-visual-brand">
                    <img src="{{ asset('images/icons/asnet-mark.svg') }}" alt="ASNet">
                    <strong>ASNet</strong>
                </div>
                <p>Billing Management</p>
                <span class="guest-wave guest-wave-one"></span>
                <span class="guest-wave guest-wave-two"></span>
                <span class="guest-wave guest-wave-three"></span>
            </div>
            <div class="guest-pane">
                <div class="guest-brand">
                    <img src="{{ asset('images/icons/asnet-mark.svg') }}" class="brand-mark-image" alt="ASNet">
                    <div><strong>ASNet Billing</strong><small>Management Console</small></div>
                </div>
                <div class="guest-card">{{ $slot }}</div>
                <p class="guest-footnote">Secure operations workspace · ASNet</p>
            </div>
        </div>
    </body>
</html>
