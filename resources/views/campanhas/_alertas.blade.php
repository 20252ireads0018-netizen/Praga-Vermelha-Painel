@if (session('ok'))
    <div class="camp-alerta camp-alerta-ok">{{ session('ok') }}</div>
@endif
@if ($errors->any())
    <div class="camp-alerta camp-alerta-erro">
        @foreach ($errors->all() as $erro)
            <div>{{ $erro }}</div>
        @endforeach
    </div>
@endif