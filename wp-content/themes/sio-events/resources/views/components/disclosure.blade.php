@props([
	'collapseText' => __('Skrij', 'sage'),
	'expandText' => __('Prikaži več', 'sage'),
])

@php $uniqueId = uniqid(); @endphp

<div id="controlled-content-{{ $uniqueId }}" hidden class="prose | flow">
    {!! $slot !!}
</div>

<button
        type="button"
        class="button"
        aria-expanded="false"
        aria-controls="controlled-content-{{ $uniqueId }}"
        data-collapse-text="{{ $collapseText }}">
    {{ $expandText }}
</button>