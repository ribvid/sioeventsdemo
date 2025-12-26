{{--
  Template Name: QR
--}}

@extends('layouts.app')

@section('content')
    @while(have_posts())
        @php the_post() @endphp
        <article class="prose wrapper flow">
            @include('partials.page-header')

            @php
                $entry_id = get_query_var('attendance_entry');
                $status = validate_qr_code($entry_id);
            @endphp

            <p>{!! $status !!}</p>
        </article>
    @endwhile
@endsection
