@extends('layouts.app')

@section('content')
    <div class="prose | wrapper flow">
        <h1>Accordion</h1>

        <x-accordion>
            <x-accordion-item>
                <x-slot:heading>Lorem ipsum dolor sit amet Lorem ipsum dolor sit amet, consectetur adipisicing elit.
                    Alias architecto culpa debitis dolore dolorem doloremque eveniet maiores non quasi quo.
                </x-slot:heading>
                <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Blanditiis consectetur consequatur cum,
                    dicta
                    doloribus excepturi exercitationem facere illo illum incidunt itaque nesciunt nulla odio officiis,
                    placeat
                    porro repudiandae soluta voluptates?</p>
                <p>Alias animi at consequatur deserunt, dicta dolores eaque eveniet ex facilis hic illum impedit in
                    inventore,
                    ipsum magnam magni necessitatibus nisi numquam perspiciatis, placeat possimus quam quidem sit totam
                    voluptas.</p>
                <p>Cum cupiditate neque odio perspiciatis quisquam, recusandae repellat voluptas. Accusantium corporis
                    dignissimos dolorem ipsam odio quasi tempore unde voluptatibus? Accusamus aspernatur corporis eum
                    exercitationem expedita hic magnam maiores odio, velit.</p>
            </x-accordion-item>

            <x-accordion-item>
                <x-slot:heading>A ad debitis fugit nisi quos recusandae?</x-slot:heading>
                <p>Alias animi at consequatur deserunt, dicta dolores eaque eveniet ex facilis hic illum impedit in
                    inventore,
                    ipsum magnam magni necessitatibus nisi numquam perspiciatis, placeat possimus quam quidem sit totam
                    voluptas.</p>
                <p>Cum cupiditate neque odio perspiciatis quisquam, recusandae repellat voluptas. Accusantium corporis
                    dignissimos dolorem ipsam odio quasi tempore unde voluptatibus? Accusamus aspernatur corporis eum
                    exercitationem expedita hic magnam maiores odio, velit.</p>
            </x-accordion-item>

            <x-accordion-item>
                <x-slot:heading>Consequatur enim error nam suscipit</x-slot:heading>
                <p>Cum cupiditate neque odio perspiciatis quisquam, recusandae repellat voluptas. Accusantium corporis
                    dignissimos dolorem ipsam odio quasi tempore unde voluptatibus? Accusamus aspernatur corporis eum
                    exercitationem expedita hic magnam maiores odio, velit.</p>
            </x-accordion-item>
        </x-accordion>
    </div>
@endsection