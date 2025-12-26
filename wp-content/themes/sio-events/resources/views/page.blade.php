@extends('layouts.app')

@section('content')
    @while(have_posts())
        @php the_post() @endphp
        <article class="wrapper prose flow">
            <h1>{!! get_the_title() !!}</h1>
            @php the_content() @endphp
        </article>
    @endwhile
@endsection
