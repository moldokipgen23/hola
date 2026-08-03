@extends('layouts.admin')

@section('title', $section)
@section('header', $section)

@section('content')
<div class="bg-white p-8 rounded-lg shadow text-center">
    <h3 class="text-xl font-semibold mb-2">{{ $section }}</h3>
    <p class="text-gray-500">This section will be built next.</p>
    <a href="{{ route('admin.dashboard') }}" class="mt-4 inline-block text-orange-500">← Back to Dashboard</a>
</div>
@endsection
