@if ($pagination = paginate_links([
        'end_size' => 1,
        'mid_size' => 2,
        'prev_text' => __('Previous', 'sage'),
        'next_text' => __('Next', 'sage'),
      ]))
    <div class="cluster pagination">
        {!! $pagination !!}
    </div>
@endif