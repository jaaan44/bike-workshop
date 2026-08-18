@php
    $user = auth()->user();

    $items = $user->isWorkshopUser()
        ? [
            ['label' => 'Dashboard', 'icon' => '📊', 'route' => 'staff.dashboard', 'pattern' => 'staff.dashboard'],
            ['label' => 'Bookings', 'icon' => '📋', 'route' => 'staff.bookings.index', 'pattern' => 'staff.bookings.*'],
            ['label' => 'Jobs', 'icon' => '🛠️', 'route' => 'staff.jobs.index', 'pattern' => 'staff.jobs.*'],
            ['label' => 'Profile', 'icon' => '👤', 'route' => 'profile.edit', 'pattern' => 'profile.*'],
        ]
        : [
            ['label' => 'Home', 'icon' => '🏠', 'route' => 'customer.home', 'pattern' => 'customer.home'],
            ['label' => 'Repairs', 'icon' => '🔧', 'route' => 'customer.repairs.index', 'pattern' => 'customer.repairs.*'],
            ['label' => 'Bikes', 'icon' => '🚲', 'route' => 'customer.bikes.index', 'pattern' => 'customer.bikes.*'],
            ['label' => 'Profile', 'icon' => '👤', 'route' => 'profile.edit', 'pattern' => 'profile.*'],
        ];
@endphp

<nav class="fixed bottom-0 inset-x-0 z-40 bg-white border-t border-gray-200 pb-[env(safe-area-inset-bottom)]">
    <div class="max-w-2xl mx-auto grid grid-cols-4">
        @foreach ($items as $item)
            @php $active = request()->routeIs($item['pattern']); @endphp
            <a href="{{ route($item['route']) }}"
               class="flex flex-col items-center justify-center gap-0.5 py-2.5 text-xs font-medium {{ $active ? 'text-indigo-600' : 'text-gray-500' }}">
                <span class="text-xl leading-none" aria-hidden="true">{{ $item['icon'] }}</span>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
