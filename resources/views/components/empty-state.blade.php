@props(['title', 'description' => null, 'actionLabel' => null, 'actionHref' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center text-center px-6 py-12 bg-white rounded-xl border border-gray-200']) }}>
    <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-4 text-gray-400">
        {{ $icon ?? '' }}
    </div>

    <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>

    @if ($description)
        <p class="mt-1 text-sm text-gray-500 max-w-xs">{{ $description }}</p>
    @endif

    @if ($actionLabel && $actionHref)
        <a href="{{ $actionHref }}" class="mt-5 inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 active:bg-indigo-700">
            {{ $actionLabel }}
        </a>
    @endif
</div>
