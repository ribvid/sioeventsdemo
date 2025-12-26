@php
    $course = get_field('related_course');
    $form_id = get_post_meta(get_the_ID(), 'submission_form_id', true)
@endphp

<article class="wrapper prose flow">
    <header>
        <h1>
            {!! $title !!}
        </h1>
    </header>

    <ul role="list">
        <li>Datum začetka: <strong>{{ get_field('start_date') ?: "N/A" }}</strong></li>
        <li>Datum zaključka: <strong>{{ get_field('start_date') ?: "N/A" }}</strong></li>
        <li>Kraj: <strong>{{ get_field('location') ?: "N/A" }}</strong></li>
        <li>Institucija: <strong>{{ get_field('institution') ?: "N/A" }}</strong></li>
    </ul>

    <p>
        Št. prijav:
        <strong>
            {{ $form_id ? GFAPI::count_entries($form_id) : 0 }}
            @if ($max_attendees = get_field('max_attendees'))
                / {{ $max_attendees }}
            @endif
        </strong>
    </p>

    @if ($course)
        <div class="flow">
            <x-disclosure expandText="O dogodku">
                {!! apply_filters('the_content', get_post_field('post_content', $course)) !!}
            </x-disclosure>
        </div>
    @endif

    @if ($form_id)
        <hr>

        <div class="flow">
            <h2>Prijavnica</h2>
            {!! do_shortcode('[gravityform id="' . $form_id . '" title="false" description="false" ]') !!}
        </div>
    @endif
</article>
