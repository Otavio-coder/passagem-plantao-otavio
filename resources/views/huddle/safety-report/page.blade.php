@extends('layouts.app')

@section('content')
    @push('head')
        <meta name="description" content="Consulta dos Round Unidade - Huddle de Gestão de Altas">
    @endpush

    <div class="w-full my-2 text-[#004D9D]">
        @livewire('huddle-safety-report')
    </div>
@endsection
