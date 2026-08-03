@extends('vendor.layouts.dashboard')

@section('title', 'Notifications')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Notifications</h1>
            <p class="text-dark-300 text-sm mt-1">Stay updated with your business activity</p>
        </div>
        <div class="flex items-center gap-3">
            @if($unread_count > 0)
            <form action="{{ route('vendor.notifications.read-all') }}" method="POST">
                @csrf
                <button type="submit" class="px-4 py-2 bg-violet-600/20 text-violet-400 rounded-xl text-sm font-medium hover:bg-violet-600/30 transition-all">
                    Mark all read
                </button>
            </form>
            @endif
        </div>
    </div>

    @if($unread_count > 0)
    <div class="glass-card p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-violet-500/20 flex items-center justify-center">
            <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
        </div>
        <div>
            <p class="text-white font-medium text-sm">You have {{ $unread_count }} unread notification{{ $unread_count > 1 ? 's' : '' }}</p>
            <p class="text-dark-300 text-xs">New updates need your attention</p>
        </div>
    </div>
    @endif

    @if($notifications->isEmpty())
    <div class="glass-card p-12 text-center">
        <div class="w-16 h-16 rounded-2xl bg-dark-700/50 flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-dark-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
        </div>
        <h3 class="text-white font-semibold mb-1">No notifications yet</h3>
        <p class="text-dark-300 text-sm">When you get notifications, they'll show up here</p>
    </div>
    @else
    <div class="space-y-2">
        @foreach($notifications as $notification)
        <a href="{{ route('vendor.notifications.show', $notification) }}"
           class="glass-card p-4 flex items-start gap-4 block transition-all hover:border-violet-500/30 {{ !$notification->is_read ? 'border-l-2 border-l-violet-500' : '' }}">
            <div class="w-10 h-10 rounded-xl flex-shrink-0 flex items-center justify-center
                {{ str_contains($notification->type, 'order') ? 'bg-emerald-500/20 text-emerald-400' :
                   str_contains($notification->type, 'booking') ? 'bg-blue-500/20 text-blue-400' :
                   str_contains($notification->type, 'review') ? 'bg-amber-500/20 text-amber-400' :
                   str_contains($notification->type, 'claim') ? 'bg-violet-500/20 text-violet-400' :
                   'bg-dark-600/50 text-dark-300' }}">
                @if(str_contains($notification->type, 'order'))
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                @elseif(str_contains($notification->type, 'booking'))
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                @elseif(str_contains($notification->type, 'review'))
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                @elseif(str_contains($notification->type, 'claim'))
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @else
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <h3 class="text-white text-sm font-semibold {{ !$notification->is_read ? '' : 'font-medium' }}">{{ $notification->title }}</h3>
                    @if(!$notification->is_read)
                        <span class="w-2 h-2 rounded-full bg-violet-500 flex-shrink-0"></span>
                    @endif
                </div>
                @if($notification->body)
                    <p class="text-dark-300 text-sm mt-0.5 line-clamp-2">{{ $notification->body }}</p>
                @endif
                <p class="text-dark-400 text-xs mt-1">{{ $notification->created_at->diffForHumans() }}</p>
            </div>
            <form action="{{ route('vendor.notifications.destroy', $notification) }}" method="POST" onclick="event.stopPropagation(); event.preventDefault(); this.submit();" class="flex-shrink-0">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-dark-400 hover:text-red-400 transition-colors p-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </form>
        </a>
        @endforeach
    </div>

    <div class="flex justify-center">
        {{ $notifications->links() }}
    </div>
    @endif
</div>
@endsection
