@extends('layouts.app')

@section('content')
    @push('head')
        <link rel="prefetch" href="{{ route('sbar.report') }}" as="document">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
        <meta name="description" content="Sistema SBAR - Passagem de Plantão Digital">
        <meta name="format-detection" content="telephone=no">

    @endpush

    <div class="w-full my-2 text-[#004D9D] no-zoom">
        @livewire('sbar-report', [], key('sbar-report-' . now()->timestamp))
    </div>
@endsection


