@extends('layouts.app')

@section('content')
    @livewire('sbar-report', [], key('sbar-report'), ['lazy' => true])
@endsection