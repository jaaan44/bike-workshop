<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-50">
            <header class="bg-white border-b border-gray-200">
                <div class="max-w-2xl mx-auto px-4 h-14 flex items-center justify-between">
                    <a href="{{ auth()->user()->isWorkshopUser() ? route('staff.dashboard') : route('customer.home') }}" class="font-semibold text-gray-900">
                        {{ config('app.name') }}
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-gray-500 hover:text-gray-900">
                            {{ __('Log Out') }}
                        </button>
                    </form>
                </div>

                @isset($header)
                    <div class="max-w-2xl mx-auto px-4 pb-4">
                        {{ $header }}
                    </div>
                @endisset
            </header>

            <!-- Page Content -->
            <main class="max-w-2xl mx-auto px-4 py-6 pb-24">
                {{ $slot }}
            </main>

            <x-bottom-nav />
        </div>
    </body>
</html>
