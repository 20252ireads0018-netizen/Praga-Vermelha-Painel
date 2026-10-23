@extends('layout')

@section('title', $mesa->nome . ' · Tabletop')

@section('conteudo')
<div class="tt-page" id="tt-page">

    {{-- ========== BARRA DO TOPO ========== --}}
    <div class="tt-topbar">
        <a href="{{ route('tabletop.index') }}" class="botao-voltar">← Mesas</a>
        <input type="text" id="tt-nome-mesa" class="tt-nome-mesa" maxlength="80" value="{{ $mesa->nome }}" aria-label="Nome da mesa">
        <span id="tt-status" class="tt-status" data-estado="salvo">Salvo</span>
    </div>

    <div class="tt-layout">

        {{-- ========== PAINEL LATERAL ========== --}}
        <aside class="tt-side">

            <details class="tt-painel" open>
                <summary>Mapa e grade</summary>
                <div class="tt-painel__corpo">
                    <div class="tt-linha3">
                        <label class="tt-campo">Colunas<input type="number" min="4" max="60" data-cfg="cols"></label>
                        <label class="tt-campo">Linhas<input type="number" min="4" max="60" data-cfg="rows"></label>
                        <label class="tt-campo">Casa (px)<input type="number" min="20" max="80" step="5" data-cfg="cel"></label>
                    </div>
                    <label class="tt-check"><input type="checkbox" data-cfg="grade"> Mostrar grade</label>
                    <label class="tt-check"><input type="checkbox" data-cfg="nomes"> Mostrar nomes das peças</label>
                    <div class="tt-botoes">
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
                        <button type="button" class="pv-btn pv-btn--ghost tt-btn-sm" data-acao="salvar-modelo-novo">Salvar modelo</button>
                    </div>
                </div>
            </details>

            <details class="tt-painel" open>
                <summary>Peças selecionadas</summary>
                <div class="tt-painel__corpo" id="tt-sel"></div>
            </details>

            <details class="tt-painel">
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
                <button type="button" class="tt-ferr" data-acao="ferr" data-ferr="pincel">Pincel</button>
                <button type="button" class="tt-ferr" data-acao="ferr" data-ferr="borracha">Borracha</button>
                <button type="button" class="tt-ferr" data-acao="ferr" data-ferr="circulo">Círculo</button>
                <button type="button" class="tt-ferr" data-acao="ferr" data-ferr="quadrado">Quadrado</button>
                <button type="button" class="tt-ferr" data-acao="ferr" data-ferr="cone">Cone</button>
                <button type="button" class="tt-ferr" data-acao="ferr" data-ferr="triangulo">Triângulo</button>
                <span class="tt-sep"></span>
                <input type="color" data-des="cor" value="#f472b6" title="Cor do desenho">
                <input type="range" min="1" max="24" value="3" data-des="larg" title="Espessura">
                <label class="tt-check tt-check--inline"><input type="checkbox" data-des="preencher" checked> Preencher</label>
                <span class="tt-sep"></span>
                <button type="button" class="tt-ferr" data-acao="des-desfazer" id="tt-desfazer">Desfazer</button>
                <button type="button" class="tt-ferr" data-acao="des-refazer" id="tt-refazer">Refazer</button>
                <button type="button" class="tt-ferr" data-acao="des-limpar">Limpar</button>
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
            </p>
        </section>

    </div>
</div>

<script type="application/json" id="tt-cfg">{!! json_encode([
    'urlSalvar' => route('tabletop.update', $mesa),
    'csrf'      => csrf_token(),
    'nome'      => $mesa->nome,
    'estado'    => $estado,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endsection

@section('script')
    <script src="{{ asset('js/tabletop.js') }}"></script>
@endsection