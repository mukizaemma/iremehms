@extends('layouts.guest')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-body text-center py-5">
                    <h1 class="h4 mb-3">{{ $hotel->name }}</h1>
                    <p class="text-muted">Public booking page</p>
                    <p class="small text-muted">This is the public booking URL for your hotel. You can add your booking form or link to your reservation system here.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
