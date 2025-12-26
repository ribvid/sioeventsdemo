<div class="site-head" @if (is_front_page()) data-variant="frontpage" @endif>
    <header class="site-head__inner repel">
        <a href="{{ home_url('/') }}">
            {{--<img src="{{ Vite::asset('resources/images/logo.svg') }}" alt="{{ $siteName }}" width="99" height="21">--}}
        </a>

        @if (has_nav_menu('primary_navigation'))
            <burger-menu max-width="900">
                <nav class="nav-primary" aria-label="{{ wp_get_nav_menu_name('primary_navigation') }}">
                    <ul class="cluster" role="list">
                        {!! wp_nav_menu([
                            'theme_location' => 'primary_navigation',
                            'container' => '',
                            'items_wrap' => '%3$s',
                            'menu_class' => '',
                            'echo' => false,
                          ]) !!}
                        @if (function_exists('pll_the_languages'))
                            @php $languages = pll_the_languages([ 'raw' => 1, 'echo' => 1, 'hide_current' => 1 ]) @endphp
                            @foreach ($languages as $lang)
                                <li>
                                    <a href="{{ $lang['url'] }}">{{ $lang['slug'] }}</a>
                                </li>
                            @endforeach
                        @endif
                    </ul>
                </nav>
            </burger-menu>
        @endif
    </header>
</div>
