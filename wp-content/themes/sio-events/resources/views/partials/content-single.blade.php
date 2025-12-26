<article class="wrapper prose flow">
  <header class="flow">
    @include('partials.entry-meta')

    <h1 class="flow-space-2xs">
      {!! $title !!}
    </h1>
  </header>

  @php the_content() @endphp
</article>
