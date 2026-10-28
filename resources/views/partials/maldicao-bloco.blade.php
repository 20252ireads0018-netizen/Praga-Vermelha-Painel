{{--
    resources/views/partials/maldicao-bloco.blade.php

    Bloco de uma maldição na ficha (somente leitura).
    Recebe de personagem/fichafeita.blade.php:
      $maldicao  -> array do catálogo (config/maldicoes.php)
      $slug      -> slug da maldição (usado na rota maldicoes.show)
      $ameacaMax -> nível de ameaça liberado para o personagem
      $aberto    -> true quando deve começar expandido

    O arquivo é tolerante com o formato do catálogo: aceita vários nomes de chave
    (nome/titulo, descricao/resumo, ameacas/niveis) e itens como texto ou array.
--}}
@php
    $maldicao  = is_array($maldicao ?? null) ? $maldicao : [];
    $slug      = $slug ?? '';
    $ameacaMax = (int) ($ameacaMax ?? 0);
    $aberto    = (bool) ($aberto ?? false);

    // Lê a primeira chave existente e preenchida.
    $pegar = function (array $dados, array $chaves, $padrao = null) {
        foreach ($chaves as $c) {
            if (isset($dados[$c]) && $dados[$c] !== '' && $dados[$c] !== []) {
                return $dados[$c];
            }
        }
        return $padrao;
    };

    // Converte qualquer valor em texto simples (listas viram linhas separadas).
    $textoDe = function ($valor) use (&$textoDe) {
        if (is_array($valor)) {
            return collect($valor)->map(fn ($v) => $textoDe($v))->filter()->implode("\n");
        }
        return trim((string) $valor);
    };

    $nome      = $pegar($maldicao, ['nome', 'titulo', 'name'], \Illuminate\Support\Str::headline($slug));
    $descricao = $textoDe($pegar($maldicao, ['descricao', 'resumo', 'sobre', 'texto'], ''));

    // Ameaças: lista (0, 1, 2...) ou mapa com o nível como chave.
    $brutas   = $pegar($maldicao, ['ameacas', 'niveis', 'ameaça', 'ameaca'], []);
    $ameacas  = [];
    if (is_array($brutas)) {
        foreach ($brutas as $chave => $item) {
            $nivel = is_array($item) ? ($item['nivel'] ?? $item['ameaca'] ?? null) : null;
            if ($nivel === null) {
                $nivel = is_numeric($chave) ? (int) $chave : null;
            }
            if ($nivel === null) {
                continue;
            }
            $ameacas[] = [
                'nivel'     => (int) $nivel,
                'titulo'    => is_array($item) ? $textoDe($pegar($item, ['nome', 'titulo'], '')) : '',
                'descricao' => is_array($item)
                    ? $textoDe($pegar($item, ['descricao', 'efeito', 'efeitos', 'texto', 'habilidades'], ''))
                    : $textoDe($item),
            ];
        }
    }
    $ameacas = collect($ameacas)->sortBy('nivel')->values();

    $liberadas = $ameacas->filter(fn ($a) => $a['nivel'] <= $ameacaMax)->values();
    $bloqueadas = $ameacas->count() - $liberadas->count();
@endphp

<details class="maldicao-bloco" @if($aberto) open @endif>
    <summary class="maldicao-bloco-titulo">
        <span class="maldicao-bloco-nome">{{ $nome }}</span>
        <span class="personagem-card-tag">Ameaça {{ $ameacaMax }}</span>
    </summary>

    <div class="maldicao-bloco-corpo">
        @if($descricao !== '')
            <p class="descricao-texto">{!! nl2br(e($descricao)) !!}</p>
        @endif

        @forelse($liberadas as $a)
            <div class="item-mostrar-card maldicao-ameaca">
                <div class="item-mostrar-topo">
                    <h4 class="item-mostrar-nome">
                        Ameaça {{ $a['nivel'] }}@if($a['titulo'] !== '') &middot; {{ $a['titulo'] }}@endif
                    </h4>
                </div>
                @if($a['descricao'] !== '')
                    <p class="item-mostrar-texto">{!! nl2br(e($a['descricao'])) !!}</p>
                @endif
            </div>
        @empty
            <p class="lista-vazia">Nenhuma ameaça liberada para este nível.</p>
        @endforelse

        @if($bloqueadas > 0)
            <p class="lista-vazia">
                {{ $bloqueadas }} {{ $bloqueadas === 1 ? 'ameaça ainda não liberada' : 'ameaças ainda não liberadas' }}.
            </p>
        @endif

        @if($slug !== '' && \Illuminate\Support\Facades\Route::has('maldicoes.show'))
            <p style="margin-top:.75rem;">
                <a href="{{ route('maldicoes.show', $slug) }}" class="maldicao-link">Ver página completa da maldição</a>
            </p>
        @endif
    </div>
</details>