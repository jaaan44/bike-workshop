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
            ['label' => 'Alerts', 'icon' => '🔔', 'route' => 'customer.notifications.index', 'pattern' => 'customer.notifications.*', 'badge' => $user->unreadNotifications()->count()],
            ['label' => 'Profile', 'icon' => '👤', 'route' => 'profile.edit', 'pattern' => 'profile.*'],
        ];
@endphp

<nav class="fixed bottom-0 inset-x-0 z-40 bg-white border-t border-gray-200 pb-[env(safe-area-inset-bottom)]">
    <div class="max-w-2xl mx-auto grid {{ $user->isWorkshopUser() ? 'grid-cols-4' : 'grid-cols-5' }}">
        @foreach ($items as $item)
            @php $active = request()->routeIs($item['pattern']); @endphp
            <a href="{{ route($item['route']) }}"
               class="relative flex flex-col items-center justify-center gap-0.5 py-2.5 text-xs font-medium {{ $active ? 'text-indigo-600' : 'text-gray-500' }}">
                <span class="relative text-xl leading-none" aria-hidden="true">
                    {{ $item['icon'] }}
                    @if (! empty($item['badge']))
                        <span class="absolute -top-1.5 -right-2.5 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-red-600 text-white text-[0.6rem] leading-[1.1rem] font-bold text-center">
                            {{ $item['badge'] > 9 ? '9+' : $item['badge'] }}
                        </span>
                    @endif
                </span>
                <span>
                    {{ $item['label'] }}
                    @if (! empty($item['badge']))
                        <span class="sr-only">({{ $item['badge'] }} unread)</span>
                    @endif
                </span>
            </a>
        @endforeach
    </div>
</nav>
