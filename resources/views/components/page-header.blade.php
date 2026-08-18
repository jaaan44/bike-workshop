@props(['title', 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'mb-5']) }}>
    <h1 class="text-xl font-bold text-gray-900">{{ $title }}</h1>

    @if ($subtitle)
        <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
    @endif
</div>
