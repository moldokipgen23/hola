@props(['name', 'class' => 'w-5 h-5'])

@php
    $paths = \App\Services\AdminIcons::get($name);
@endphp

@if ($paths)
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="{{ $class }}">
        {!! $paths !!}
    </svg>
@endif
