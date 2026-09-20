<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-2 mb-0">
            <x-page-header title="Notifications" class="mb-0" />

            @if ($notifications->contains(fn ($notification) => $notification->unread()))
                <form method="POST" action="{{ route('customer.notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>
    </x-slot>

    @if ($notifications->isEmpty())
        <x-empty-state
            title="No notifications yet"
            description="You'll see updates here when your repairs reach an important stage."
        />
    @else
        <div class="space-y-2">
            @foreach ($notifications as $notification)
                @php $unread = $notification->unread(); @endphp
                <form method="POST" action="{{ route('customer.notifications.read', $notification->id) }}">
                    @csrf
                    <button type="submit" class="w-full text-left rounded-xl border p-4 flex items-start gap-3 {{ $unread ? 'bg-indigo-50 border-indigo-200' : 'bg-white border-gray-200' }}">
                        <span
                            class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full {{ $unread ? 'bg-indigo-600' : 'bg-transparent' }}"
                            aria-hidden="true"
                        ></span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm {{ $unread ? 'font-semibold text-gray-900' : 'font-medium text-gray-700' }}">
                                {{ $notification->data['message'] }}
                                @if ($unread)
                                    <span class="sr-only">(unread)</span>
                                @endif
                            </span>
                            <span class="block text-xs text-gray-500 mt-0.5">
                                {{ $notification->data['booking_reference_number'] ?? '' }} &middot; {{ $notification->created_at->diffForHumans() }}
                            </span>
                        </span>
                    </button>
                </form>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $notifications->links() }}
        </div>
    @endif
</x-app-layout>
