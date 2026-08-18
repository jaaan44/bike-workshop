<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900">
        <div class="min-h-screen flex flex-col items-center justify-center px-6 text-center">
            <span class="text-5xl mb-4" aria-hidden="true">🚲</span>
            <h1 class="text-2xl font-bold">{{ config('app.name') }}</h1>
            <p class="mt-2 text-sm text-gray-500 max-w-xs">
                Book a repair, track your bike's progress, and know exactly when it's ready.
            </p>

            <div class="mt-8 w-full max-w-xs space-y-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="block w-full rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        Go to Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="block w-full rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        Log In
                    </a>
                    <a href="{{ route('register') }}" class="block w-full rounded-lg bg-white px-5 py-3 text-sm font-semibold text-gray-900 border border-gray-300 hover:bg-gray-50">
                        Create Account
                    </a>
                @endauth
            </div>
        </div>
    </body>
</html>
