@extends('vendor.layouts.dashboard')

@section('title', $notification->title)

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <a href="{{ route('vendor.notifications') }}" class="inline-flex items-center gap-2 text-dark-300 hover:text-white transition-colors text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to Notifications
    </a>

    <div class="glass-card p-6">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl flex-shrink-0 flex items-center justify-center
                {{ str_contains($notification->type, 'order') ? 'bg-emerald-500/20 text-emerald-400' :
                   str_contains($notification->type, 'booking') ? 'bg-blue-500/20 text-blue-400' :
                   str_contains($notification->type, 'review') ? 'bg-amber-500/20 text-amber-400' :
                   str_contains($notification->type, 'claim') ? 'bg-violet-500/20 text-violet-400' :
                   'bg-dark-600/50 text-dark-300' }}">
                @if(str_contains($notification->type, 'order'))
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                @elseif(str_contains($notification->type, 'booking'))
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                @elseif(str_contains($notification->type, 'review'))
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                @elseif(str_contains($notification->type, 'claim'))
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @else
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @endif
            </div>
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-2">
                    <h1 class="text-xl font-bold text-white">{{ $notification->title }}</h1>
                    @if(!$notification->is_read)
                        <span class="px-2 py-0.5 rounded-full bg-violet-500/20 text-violet-400 text-xs font-medium">New</span>
                    @endif
                </div>
                <p class="text-dark-400 text-sm mb-4">{{ $notification->created_at->format('F j, Y \a\t g:i A') }}</p>

                @if($notification->body)
                    <div class="text-dark-200 text-sm leading-relaxed whitespace-pre-line">{{ $notification->body }}</div>
                @endif

                @if($notification->data)
                    <div class="mt-6 glass-card p-4">
                        <h3 class="text-white font-semibold text-sm mb-3">Details</h3>
                        <dl class="space-y-2">
                            @foreach($notification->data as $key => $value)
                                <div class="flex items-center gap-3">
                                    <dt class="text-dark-400 text-xs uppercase tracking-wider w-32 flex-shrink-0">{{ str_replace('_', ' ', $key) }}</dt>
                                    <dd class="text-dark-200 text-sm">{{ is_array($value) ? json_encode($value) : $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif

                <div class="mt-6 flex items-center gap-3">
                    @if(str_contains($notification->type, 'order'))
                        <a href="{{ route('vendor.orders') }}" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-medium hover:bg-emerald-700 transition-all">View Orders</a>
                    @elseif(str_contains($notification->type, 'booking'))
                        <a href="{{ route('vendor.bookings') }}" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700 transition-all">View Bookings</a>
                    @endif

                    @if(!$notification->is_read)
                        <form action="{{ route('vendor.notifications.read', $notification) }}" method="POST">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-violet-600/20 text-violet-400 rounded-xl text-sm font-medium hover:bg-violet-600/30 transition-all">Mark as Read</button>
                        </form>
                    @endif

                    <form action="{{ route('vendor.notifications.destroy', $notification) }}" method="POST" onsubmit="return confirm('Delete this notification?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2 bg-red-600/20 text-red-400 rounded-xl text-sm font-medium hover:bg-red-600/30 transition-all">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
