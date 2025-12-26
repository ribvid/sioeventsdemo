@extends('layouts.app')

@section('content')
    <div class="wrapper flow prose">
        <h1>Pattern Library</h1>
        <ul>
            <li><a href="{{ route('prose') }}">Global CSS & Prose</a></li>
            <li><a href="{{ route('disclosure') }}">Disclosure</a></li>
            <li><a href="{{ route('accordion') }}">Accordion</a></li>
        </ul>
    </div>
@endsection
