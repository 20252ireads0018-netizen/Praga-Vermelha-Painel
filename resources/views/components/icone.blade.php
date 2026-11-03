@props(['n'])
{{-- Ícone próprio do site (public/img/icones.svg). Ex.: <x-icone n="dados"/>; no JS: icone('dados') --}}
<svg {{ $attributes->merge(['class' => 'ico']) }} aria-hidden="true" focusable="false"><use href="{{ asset('img/icones.svg') }}?v={{ @filemtime(public_path('img/icones.svg')) }}#i-{{ $n }}"></use></svg>
