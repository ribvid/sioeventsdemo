@extends('layouts.app')

@section('content')
    @if (! have_posts())
        <article class="wrapper prose flow">
            <h1>{{ __('Ne obstaja', 'sage') }}</h1>
            <p>{{ __('Žal stran, ki si jo želite ogledati, ne obstaja.', 'sage') }}</p>
        </article>
    @endif
@endsection
