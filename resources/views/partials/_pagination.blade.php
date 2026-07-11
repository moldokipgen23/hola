@if ($paginator->hasPages())
    <nav class="flex items-center justify-center gap-1">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="px-3 py-2 rounded-lg text-sm text-slate-300 cursor-not-allowed">←</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="px-3 py-2 rounded-lg text-sm text-slate-600 hover:bg-primary-50 hover:text-primary-600 transition-colors">←</a>
        @endif

        {{-- Pages --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-3 py-2 rounded-lg text-sm text-slate-400">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="px-3 py-2 rounded-lg text-sm bg-primary-500 text-white font-medium">{{ $page }}</span>
                    @elseif ($page == $paginator->currentPage() - 1 || $page == $paginator->currentPage() + 1 || $page == 1 || $page == $paginator->lastPage())
                        <a href="{{ $url }}" class="px-3 py-2 rounded-lg text-sm text-slate-600 hover:bg-primary-50 hover:text-primary-600 transition-colors">{{ $page }}</a>
                    @elseif ($page == $paginator->currentPage() - 2 || $page == $paginator->currentPage() + 2)
                        <span class="px-2 py-2 text-sm text-slate-400">...</span>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="px-3 py-2 rounded-lg text-sm text-slate-600 hover:bg-primary-50 hover:text-primary-600 transition-colors">→</a>
        @else
            <span class="px-3 py-2 rounded-lg text-sm text-slate-300 cursor-not-allowed">→</span>
        @endif
    </nav>
    <p class="text-center text-xs text-slate-400 mt-3">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }} ({{ $paginator->total() }} results)</p>
@endif
