@extends('layouts.app')

@section('content')
    <p>QR kode</p>

    @php $entries = GFAPI::get_entries(1); @endphp

    <ul role="list">
        @foreach ($entries as $entry)
            <li>
                @php
                    $entry_id = $entry['id'];
                @endphp

                <img src="{{ get_qr_code($entry_id)->getDataUri() }}" alt="QR koda za prisotnost"/>

                {{ $entry['id'] }}
            </li>
        @endforeach
    </ul>
@endsection

