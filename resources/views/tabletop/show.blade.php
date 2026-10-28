@extends('layout')

@section('title', $mesa->nome . ' · Tabletop')

@section('conteudo')
<style>
    /* Barra da sala online (topo da mesa) */
    .tt-sala { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-left: auto; }
    .tt-sala__cod { font-size: .85rem; }
    .tt-sala__cod strong { margin-left: .25rem; padding: .15rem .5rem; border: 1px solid currentColor; border-radius: .4rem;
                           font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: .18em; }
    .tt-sala__on { font-size: .8rem; opacity: .8; cursor: default; }
    .tt-sala__aviso { font-size: .75rem; opacity: .7; }
    /* Convidados não mexem em linhas, colunas, fundo, nome da mesa nem em "Minhas peças" */
    .tt-convidado .tt-so-dono { display: none !important; }
    .tt-convidado .tt-nome-mesa { pointer-events: none; }
    /* Ferramentas de desenho em ícones */
    .tt-ferr--ico { display: inline-flex; align-items: center; justify-content: center; padding: .4rem .55rem; }
    .tt-ferr--ico svg { width: 1.15rem; height: 1.15rem; fill: none; stroke: currentColor; stroke-width: 1.8;
                        stroke-linecap: round; stroke-linejoin: round; display: block; }
    /* Quadro para mover a imagem dentro da peça */
    .tt-pan-wrap { display: flex; flex-direction: column; align-items: center; gap: .3rem; margin: .5rem 0; }
    .tt-pan-wrap small { font-size: .72rem; opacity: .7; }
    .tt-pan { width: 110px; height: 110px; overflow: hidden; border: 1px solid currentColor; background: rgba(255,255,255,.05);
              cursor: grab; touch-action: none; user-select: none; }
    .tt-pan:active { cursor: grabbing; }
    .tt-pan img { width: 100%; height: 100%; object-fit: cover; display: block; pointer-events: none; transform-origin: center; }
    .tt-pan--circulo { border-radius: 50%; }
    .tt-pan--losango { clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%); }
</style>

<div class="tt-page{{ $convidado ? ' tt-convidado' : '' }}" id="tt-page">

    {{-- ========== BARRA DO TOPO ========== --}}
    <div class="tt-topbar">
        <a href="{{ route('tabletop.index') }}" class="botao-voltar">← Mesas</a>
        <input type="text" id="tt-nome-mesa" class="tt-nome-mesa" maxlength="80" value="{{ $mesa->nome }}" aria-label="Nome da mesa"@if($convidado) readonly @endif>
        <span id="tt-status" class="tt-status" data-estado="salvo">{{ $sala ? 'Sincronizado' : 'Salvo' }}</span>

        <div class="tt-sala" id="tt-sala">
            @if($sala)
                <span class="tt-sala__cod" title="Código da sala">Sala <strong>{{ $sala['codigo'] }}</strong></span>
                <button type="button" class="pv-btn pv-btn--ghost tt-btn-sm" data-acao="sala-copiar">Copiar código</button>
                <span class="tt-sala__on" id="tt-sala-on">1 online</span>
                @if($sala['dono'])
                    <button type="button" class="pv-btn pv-btn--outline tt-btn-sm" data-acao="sala-encerrar">Encerrar sala</button>
                @else
                    <span class="tt-sala__aviso">Você está na sala de outra pessoa</span>
                    <button type="button" class="pv-btn pv-btn--outline tt-btn-sm" data-acao="sala-sair">Sair da sala</button>
                @endif
            @else
                <button type="button" class="pv-btn pv-btn--ghost tt-btn-sm" data-acao="sala-abrir"
                        title="Gera um código para outras pessoas entrarem nesta mesa e moverem as peças junto com você">Abrir sala online</button>
            @endif
        </div>
    </div>

    <div class="tt-layout">

        {{-- ========== PAINEL LATERAL ========== --}}
        <aside class="tt-side">

            <details class="tt-painel" open>
                <summary>Mapa e grade</summary>
                <div class="tt-painel__corpo">
                    <div class="tt-linha3">
                        <label class="tt-campo tt-so-dono">Colunas<input type="number" min="4" max="60" data-cfg="cols"></label>
                        <label class="tt-campo tt-so-dono">Linhas<input type="number" min="4" max="60" data-cfg="rows"></label>
                        <label class="tt-campo">Casa (px)<input type="number" min="20" max="80" step="5" data-cfg="cel"></label>
                    </div>
                    <label class="tt-check"><input type="checkbox" data-cfg="grade"> Mostrar grade</label>
                    <label class="tt-check"><input type="checkbox" data-cfg="nomes"> Mostrar nomes das peças</label>
                    <div class="tt-botoes tt-so-dono">
                        <label class="pv-btn pv-btn--ghost tt-btn-sm">Imagem de fundo
                            <input type="file" accept="image/*" hidden data-arq="mapa">
                        </label>
                        <button type="button" class="pv-btn pv-btn--ghost tt-btn-sm" data-acao="tirar-mapa" id="tt-tirar-mapa">Remover fundo</button>
                    </div>
                </div>
            </details>

            <details class="tt-painel" open>
                <summary>Peças prontas</summary>
                <div class="tt-painel__corpo">
                    <div id="tt-prontas" class="tt-prontas"></div>
                </div>
            </details>

            <details class="tt-painel">
                <summary>Nova peça</summary>
                <div class="tt-painel__corpo">
                    <label class="tt-campo">Nome<input type="text" maxlength="24" data-novo="nome" placeholder="Ex.: Goblin"></label>
                    <div class="tt-linha2">
                        <label class="tt-campo">Formato
                            <select data-novo="forma">
                                <option value="circulo">Círculo</option>
                                <option value="quadrado">Quadrado</option>
                                <option value="losango">Losango</option>
                            </select>
                        </label>
                        <label class="tt-campo">Tamanho
                            <select data-novo="tam">
                                <option value="1">1×1</option><option value="2">2×2</option>
                                <option value="3">3×3</option><option value="4">4×4</option>
                            </select>
                        </label>
                    </div>
                    <label class="tt-campo">Cor<input type="color" data-novo="cor" value="#a78bfa"></label>
                    <div class="tt-novo-img">
                        <span id="tt-novo-prev" class="tt-mini"></span>
                        <label class="pv-btn pv-btn--ghost tt-btn-sm">Imagem
                            <input type="file" accept="image/*" hidden data-arq="novo">
                        </label>
                        <button type="button" class="pv-btn pv-btn--ghost tt-btn-sm" data-acao="tirar-img-novo" id="tt-tirar-img-novo" hidden>Tirar imagem</button>
                    </div>
                    <div class="tt-botoes">
                        <button type="button" class="pv-btn pv-btn--primary tt-btn-sm" data-acao="add-novo">Adicionar ao mapa</button>
                        <button type="button" class="pv-btn pv-btn--ghost tt-btn-sm tt-so-dono" data-acao="salvar-modelo-novo">Salvar modelo</button>
                    </div>
                </div>
            </details>

            <details class="tt-painel" open>
                <summary>Peças selecionadas</summary>
                <div class="tt-painel__corpo" id="tt-sel"></div>
            </details>

            <details class="tt-painel tt-so-dono">
                <summary>Minhas peças</summary>
                <div class="tt-painel__corpo">
                    <div id="tt-modelos" class="tt-modelos"></div>
                </div>
            </details>

        </aside>

        {{-- ========== CENA ========== --}}
        <section class="tt-palco">

            <div class="tt-ferramentas" id="tt-ferramentas">
                <span class="tt-ferramentas__rot">Desenho</span>
                <button type="button" class="tt-ferr tt-ferr--ico" data-acao="ferr" data-ferr="pincel" title="Pincel" aria-label="Pincel"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9.06 11.9 8.07-8.06a2.85 2.85 0 1 1 4.03 4.03l-8.06 8.08"/><path d="M7.07 14.94c-1.66 0-3 1.35-3 3.02 0 1.33-2.5 1.52-2 2.02 1.08 1.1 2.49 2.02 4 2.02 2.2 0 4-1.8 4-4.04a3.01 3.01 0 0 0-3-3.02z"/></svg></button>
                <button type="button" class="tt-ferr tt-ferr--ico" data-acao="ferr" data-ferr="borracha" title="Borracha" aria-label="Borracha"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 21-4.3-4.3c-1-1-1-2.5 0-3.4l9.6-9.6c1-1 2.5-1 3.4 0l5.6 5.6c1 1 1 2.5 0 3.4L13 21"/><path d="M22 21H7"/><path d="m5 11 9 9"/></svg></button>
                <button type="button" class="tt-ferr tt-ferr--ico" data-acao="ferr" data-ferr="circulo" title="Círculo" aria-label="Círculo"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/></svg></button>
                <button type="button" class="tt-ferr tt-ferr--ico" data-acao="ferr" data-ferr="quadrado" title="Quadrado" aria-label="Quadrado"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="1"/></svg></button>
                <button type="button" class="tt-ferr tt-ferr--ico" data-acao="ferr" data-ferr="cone" title="Cone" aria-label="Cone"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12 21 4v16z"/></svg></button>
                <button type="button" class="tt-ferr tt-ferr--ico" data-acao="ferr" data-ferr="triangulo" title="Triângulo" aria-label="Triângulo"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4 21 20H3z"/></svg></button>
                <span class="tt-sep"></span>
                <input type="color" data-des="cor" value="#f472b6" title="Cor do desenho">
                <input type="range" min="1" max="24" value="3" data-des="larg" title="Espessura">
                <label class="tt-check tt-check--inline"><input type="checkbox" data-des="preencher" checked> Preencher</label>
                <span class="tt-sep"></span>
                <button type="button" class="tt-ferr tt-ferr--ico" data-acao="des-desfazer" id="tt-desfazer" title="Desfazer (Ctrl+Z)" aria-label="Desfazer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg></button>
                <button type="button" class="tt-ferr tt-ferr--ico" data-acao="des-refazer" id="tt-refazer" title="Refazer (Ctrl+Shift+Z)" aria-label="Refazer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 14 5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/></svg></button>
                <button type="button" class="tt-ferr tt-ferr--ico" data-acao="des-limpar" title="Limpar todo o desenho" aria-label="Limpar desenho"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
            </div>

            <div class="tt-cena" id="tt-cena">
                <div class="tt-grade" id="tt-grade" tabindex="0" aria-label="Mapa do tabletop">
                    <img class="tt-mapa" id="tt-mapa" alt="" hidden draggable="false">
                    <div class="tt-linhas"></div>
                    <svg class="tt-des" id="tt-svg" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                        <g id="tt-g-des"></g>
                        <g id="tt-g-vivo"></g>
                    </svg>
                    <div class="tt-pecas" id="tt-pecas"></div>
                    <div class="tt-caixa" id="tt-caixa" hidden></div>
                </div>
            </div>

            <p class="tt-dica">
                Arraste as peças para movê-las (elas encaixam nas casas). Arraste no fundo para selecionar várias; Shift/Ctrl soma à seleção.
                Setas movem uma casa, Delete exclui, Esc limpa a seleção, Ctrl+Z desfaz o desenho.
                @if($sala)
                    Na sala, o que cada pessoa faz aparece para todos em cerca de 1 segundo.
                @endif
            </p>
        </section>

    </div>
</div>

<script type="application/json" id="tt-cfg">{!! json_encode([
    'urlSalvar' => $convidado ? '' : route('tabletop.update', $mesa),
    'urlSala'   => $convidado ? '' : route('tabletop.sala.abrir', $mesa),
    'csrf'      => csrf_token(),
    'nome'      => $mesa->nome,
    'estado'    => $estado,
    'sala'      => $sala,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endsection

@section('script')
    <script src="{{ asset('js/tabletop.js') }}?v={{ @filemtime(public_path('js/tabletop.js')) ?: time() }}"></script>
@endsection