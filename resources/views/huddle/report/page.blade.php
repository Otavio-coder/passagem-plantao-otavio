@extends('layouts.app')

@section('content')
    @push('head')
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
        <meta name="description" content="Huddle de Gestão de Altas - Passagem de Plantão">
        <meta name="format-detection" content="telephone=no">
    @endpush

    <div class="w-full my-2 text-[#004D9D] no-zoom">
        @livewire('huddle-board', [], key('huddle-board-' . now()->timestamp))
    </div>
@endsection
