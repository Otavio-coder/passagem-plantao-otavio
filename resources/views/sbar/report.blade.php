@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                @livewire('sbar-report')
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Any additional JavaScript needed for the SBAR report page
    document.addEventListener('livewire:load', function () {
        // Your code here
    });
</script>
@endpush