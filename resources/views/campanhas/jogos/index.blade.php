@extends('layout')

@section('title', 'Jogos · ' . $campanha->nome)

@section('conteudo')
<link rel="stylesheet" href="{{ asset('css/campanha.css') }}?v={{ @filemtime(public_path('css/campanha.css')) }}">
<link rel="stylesheet" href="{{ asset('css/jogos.css') }}?v={{ @filemtime(public_path('css/jogos.css')) }}">

<div class="camp-wrap camp-wrap-larga jg" id="jg-lobby">
    <header class="jg-topo">
        <a href="{{ route('campanhas.show', $campanha) }}" class="camp-voltar">← {{ $campanha->nome }}</a>
        <h1>Jogos da campanha</h1>
        <p class="camp-sub">Desafie alguém da mesa. As partidas acontecem ao vivo: os dois jogam pelo navegador e os outros podem assistir.</p>
    </header>

    <nav class="jg-abas" role="tablist">
        <button type="button" class="jg-aba" role="tab" data-aba="gladio">⚔️ Gladio</button>
        <button type="button" class="jg-aba" role="tab" data-aba="pela_dama">♛ Pela Dama</button>
    </nav>
    <p class="jg-msg" id="jg-msg" hidden></p>

    {{-- ===================== GLADIO ===================== --}}
    <section class="jg-painel" data-painel="gladio" hidden>
        <div class="jg-colunas">
            <div class="jg-col">
                <section class="camp-card">
                    <div class="camp-card-cab"><h2>Desafiar no Gladio</h2></div>
                    <p class="camp-dica">A aposta vira a vida de cada um na partida. Ela sai do seu Dinheiro quando o desafio é aceito, e quem vencer leva as duas. Aposta 0 é amistoso (30 de vida, sem Dinheiro).</p>
                    <form class="camp-form jg-desafio" data-jogo="gladio">
                        <label>Oponente
                            <select name="convidado_id" class="jg-oponente"></select>
                        </label>
                        <label>Aposta (Dinheiro)
                            <input type="number" name="aposta" min="0" value="0">
                        </label>
                        <label>Seu deck (também ao aceitar)
                            <select name="deck_id" id="jg-deck-escolhido"></select>
                        </label>
                        <button class="camp-btn camp-btn-primario">Lançar desafio</button>
                    </form>
                    <p class="camp-dica">Seu Dinheiro: <strong id="jg-meu-dinheiro">0</strong></p>
                </section>

                <section class="camp-card">
                    <div class="camp-card-cab"><h2>Partidas de Gladio</h2></div>
                    <div class="jg-partidas" data-lista="gladio"></div>
                </section>

                <section class="camp-card">
                    <div class="camp-card-cab">
                        <h2>Meus decks</h2>
                        <span class="camp-contagem" id="jg-cont-decks">0</span>
                    </div>
                    <p class="camp-dica">Monte decks com as cartas da campanha: de {{ \App\Models\GladioDeck::MIN }} a {{ \App\Models\GladioDeck::MAX }} cartas, até {{ \App\Models\GladioDeck::COPIAS }} cópias de cada. Sem deck, você joga com 20 cartas sorteadas.</p>
                    <button type="button" class="camp-btn camp-btn-primario" id="jg-novo-deck">+ Novo deck</button>
                    <form class="jg-criador jg-criador-deck" id="jg-criador-deck" hidden>
                        <input type="hidden" name="registro">
                        <div class="jg-deck-topo">
                            <label>Nome do deck<input type="text" name="nome" maxlength="60" required placeholder="Ex.: Fúria dos Kuros"></label>
                            <span class="jg-deck-total" id="jg-deck-total">0 cartas</span>
                        </div>
                        <div class="jg-deck-lista" id="jg-deck-lista"></div>
                        <div class="jg-botoes">
                            <button class="camp-btn camp-btn-primario">Salvar deck</button>
                            <button type="button" class="camp-btn" data-acao="cancelar">Cancelar</button>
                        </div>
                    </form>
                    <div class="jg-decks" id="jg-decks"></div>
                </section>

                <section class="camp-card">
                    <div class="camp-card-cab"><h2>Dinheiro</h2></div>
                    @if ($souMestre)<p class="camp-dica">Como mestre, você define quanto Dinheiro cada um tem.</p>@endif
                    <div class="jg-carteiras" id="jg-carteiras"></div>
                </section>
            </div>

            <div class="jg-col">
                <section class="camp-card">
                    <div class="camp-card-cab">
                        <h2>Cartas</h2>
                        <span class="camp-contagem" id="jg-cont-cartas">0</span>
                    </div>
                    <p class="camp-dica"><strong>Lenda</strong>: fica na mesa e ataca a partir do turno seguinte. <strong>Feitiço</strong>: efeito de uso único. Cada um monta um baralho de 20 com as cartas abaixo.</p>
                    @if ($souMestre)
                        <button type="button" class="camp-btn camp-btn-primario" id="jg-nova-carta">+ Nova carta</button>
                        <form class="jg-criador" id="jg-criador-carta" hidden>
                            <div class="jg-criador-campos">
                                <input type="hidden" name="registro">
                                <label>Nome<input type="text" name="nome" maxlength="60" required></label>
                                <label>Tipo
                                    <select name="tipo">
                                        <option value="lenda">Lenda</option>
                                        <option value="feitico">Feitiço</option>
                                    </select>
                                </label>
                                <label>Custo (energia)<input type="number" name="custo" min="0" max="10" value="1"></label>
                                <label class="jg-so-lenda">Ataque<input type="number" name="ataque" min="0" max="9999" value="1"></label>
                                <label class="jg-so-lenda">Vida<input type="number" name="vida" min="1" max="9999" value="1"></label>
                                <div class="jg-so-lenda jg-larga jg-palavras">
                                    <span>Palavras-chave</span>
                                    <label title="O dano que passar da vida de quem bloquear vai para o jogador"><input type="checkbox" name="palavras" value="sobrepujar"> Sobrepujar</label>
                                    <label title="Pode atacar no mesmo turno em que entra na mesa"><input type="checkbox" name="palavras" value="impeto"> Ímpeto</label>
                                </div>
                                <label class="jg-so-feitico">Efeito
                                    <select name="efeito">
                                        <option value="dano">Dano (Lenda inimiga ou adversário)</option>
                                        <option value="dano_todos">Dano em todas as Lendas inimigas</option>
                                        <option value="cura">Cura (Lenda sua ou você)</option>
                                        <option value="comprar">Comprar cartas</option>
                                        <option value="fortalecer">Fortalecer (+ataque numa Lenda sua)</option>
                                        <option value="energia">Ganhar energia neste turno</option>
                                    </select>
                                </label>
                                <label class="jg-so-feitico">Valor<input type="number" name="valor" min="0" max="9999" value="1"></label>
                                <label class="jg-larga">Descrição<textarea name="descricao" rows="2" maxlength="500" placeholder="Texto de ambientação (opcional)"></textarea></label>
                                <div class="jg-larga jg-img-linha">
                                    <button type="button" class="camp-btn camp-btn-pequeno" data-acao="galeria">Da galeria</button>
                                    <label class="camp-btn camp-btn-pequeno">Do computador<input type="file" accept="image/*,.heic,.heif,.tif,.tiff,.avif,.jfif,.bmp,.ico,.svg" hidden name="arquivo"></label>
                                    <button type="button" class="camp-btn camp-btn-pequeno" data-acao="tirar-img">Tirar imagem</button>
                                </div>
                                <div class="jg-larga jg-ajuste" id="jg-ajuste" hidden>
                                    <label>Zoom <input type="range" id="jg-zoom" min="1" max="4" step="0.05" value="1"></label>
                                    <button type="button" class="camp-btn camp-btn-pequeno" data-acao="centralizar">Centralizar</button>
                                    <span class="camp-dica">Arraste a imagem na prévia para escolher o enquadramento (a roda do mouse também dá zoom).</span>
                                </div>
                                <div class="jg-larga jg-escolher" id="jg-escolher" hidden>
                                    <div class="jg-galeria-barra">
                                        <select id="jg-escolher-pasta" aria-label="Pasta da galeria"></select>
                                        <button type="button" class="camp-btn camp-btn-pequeno" data-acao="fechar-galeria">Fechar</button>
                                    </div>
                                    <div class="jg-galeria" id="jg-escolher-grade"></div>
                                </div>
                                <div class="jg-larga jg-botoes">
                                    <button class="camp-btn camp-btn-primario">Salvar carta</button>
                                    <button type="button" class="camp-btn" data-acao="cancelar">Cancelar</button>
                                </div>
                            </div>
                            <div class="jg-criador-previa" id="jg-previa-carta"></div>
                        </form>
                    @endif
                    <div class="jg-cartas" id="jg-cartas"></div>
                </section>

                @if ($souMestre)
                    <section class="camp-card">
                        <div class="camp-card-cab">
                            <h2>Galeria de imagens</h2>
                            <span class="camp-contagem" id="jg-cont-imagens">0</span>
                        </div>
                        <p class="camp-dica">Imagens para a arte das cartas. Envie várias de uma vez, separe por pasta e depois escolha em "Da galeria" no criador de cartas.</p>
                        <div class="jg-galeria-envio">
                            <label>Pasta das novas imagens
                                <input type="text" id="jg-galeria-pasta-nova" maxlength="60" list="jg-pastas" placeholder="Ex.: Vilões (opcional)">
                            </label>
                            <datalist id="jg-pastas"></datalist>
                            <label class="camp-btn camp-btn-primario">Enviar imagens<input type="file" id="jg-galeria-arquivos" accept="image/*,.heic,.heif,.tif,.tiff,.avif,.jfif,.bmp,.ico,.svg" multiple hidden></label>
                        </div>
                        <p class="camp-dica" id="jg-galeria-progresso" hidden></p>
                        <div class="jg-galeria-barra">
                            <select id="jg-galeria-filtro" aria-label="Filtrar por pasta"></select>
                        </div>
                        <div class="jg-galeria jg-galeria--gerir" id="jg-galeria"></div>
                    </section>
                @endif
            </div>
        </div>
    </section>

    {{-- ===================== PELA DAMA ===================== --}}
    <section class="jg-painel" data-painel="pela_dama" hidden>
        <div class="jg-colunas">
            <div class="jg-col">
                <section class="camp-card">
                    <div class="camp-card-cab"><h2>Desafiar no Pela Dama</h2></div>
                    <p class="camp-dica">Xadrez com xeque-mate. Quem desafia joga de brancas. Cada um entra com o exército que montou ao lado.</p>
                    <form class="camp-form jg-desafio" data-jogo="pela_dama">
                        <label>Oponente
                            <select name="convidado_id" class="jg-oponente"></select>
                        </label>
                        <button class="camp-btn camp-btn-primario">Lançar desafio</button>
                    </form>
                </section>

                <section class="camp-card">
                    <div class="camp-card-cab"><h2>Partidas de Pela Dama</h2></div>
                    <div class="jg-partidas" data-lista="pela_dama"></div>
                </section>

                <section class="camp-card">
                    <div class="camp-card-cab"><h2>Meu exército</h2></div>
                    <p class="camp-dica">Escolha a peça de cada casa (vista das brancas; nas pretas o exército fica espelhado no outro lado). Precisa ter exatamente um Rei. Vale para os próximos desafios que você lançar ou aceitar.</p>
                    <div class="jg-exercito" id="jg-exercito"></div>
                    <div class="jg-botoes">
                        <button type="button" class="camp-btn camp-btn-pequeno" id="jg-exercito-padrao">Voltar ao padrão</button>
                        <span class="camp-dica" id="jg-exercito-aviso"></span>
                    </div>
                </section>
            </div>

            <div class="jg-col">
                <section class="camp-card">
                    <div class="camp-card-cab">
                        <h2>Minhas peças</h2>
                        <span class="camp-contagem" id="jg-cont-pecas">0</span>
                    </div>
                    <button type="button" class="camp-btn camp-btn-primario" id="jg-nova-peca">+ Criar peça</button>
                    <form class="jg-criador jg-criador-peca" id="jg-criador-peca" hidden>
                        <input type="hidden" name="registro">
                        <div class="jg-criador-campos">
                            <label>Nome<input type="text" name="nome" maxlength="40" required placeholder="Ex.: Arqueiro"></label>
                            <label>Símbolo<input type="text" name="simbolo" maxlength="2" required placeholder="A ou ✦"></label>
                        </div>
                        <p class="camp-dica">A peça fica no centro e a frente é para cima. Clique numa casa para marcar um <strong>salto</strong> até ela (pula peças). Cada clique troca o modo: <span class="jg-leg jg-m-ambos">move e captura</span> <span class="jg-leg jg-m-mover">só move</span> <span class="jg-leg jg-m-capturar">só captura</span>.</p>
                        <label class="camp-check"><input type="checkbox" id="jg-espelhar" checked> Espelhar esquerda/direita</label>
                        <div class="jg-grade-mov" id="jg-grade-mov"></div>
                        <p class="camp-dica"><strong>Deslizar</strong>: anda em linha reta várias casas (não atravessa peças).</p>
                        <div class="jg-raios" id="jg-raios"></div>
                        <div class="jg-botoes">
                            <button class="camp-btn camp-btn-primario">Salvar peça</button>
                            <button type="button" class="camp-btn" data-acao="limpar">Limpar movimentos</button>
                            <button type="button" class="camp-btn" data-acao="cancelar">Cancelar</button>
                        </div>
                    </form>
                    <div class="jg-pecas" id="jg-pecas"></div>
                </section>
            </div>
        </div>
    </section>
</div>

<script type="application/json" id="jg-dados">{!! json_encode([
    'eu'        => (int) auth()->id(),
    'campanha'  => $campanha->id,
    'souMestre' => $souMestre,
    'csrf'      => csrf_token(),
    'membros'   => $membros,
    'cartas'    => $cartas->map(fn ($c) => ['id' => $c->id] + $c->paraJogo() + ['imagem' => $c->imagem])->values(),
    'pecas'     => $pecas->map(fn ($p) => ['id' => $p->id, 'tipo' => $p->tipoId(), 'nome' => $p->nome, 'simbolo' => $p->simbolo, 'regras' => $p->regras ?: []])->values(),
    'imagens'   => $imagens,
    'decks'     => $decks,
    'deckRegra' => ['min' => \App\Models\GladioDeck::MIN, 'max' => \App\Models\GladioDeck::MAX, 'copias' => \App\Models\GladioDeck::COPIAS],
    'padrao'    => \App\Jogos\PelaDama::tiposPadrao(),
    'exercito'  => \App\Jogos\PelaDama::exercitoPadrao(),
    'lobby'     => $lobby,
    'url'       => [
        'lobby'    => route('campanhas.jogos.lobby', $campanha),
        'dinheiro' => route('campanhas.jogos.dinheiro', $campanha),
        'cartas'   => route('campanhas.jogos.cartas.salvar', $campanha),
        'pecas'    => route('campanhas.jogos.pecas.salvar', $campanha),
        'imagens'  => route('campanhas.jogos.imagens.enviar', $campanha),
        'decks'    => route('campanhas.jogos.decks.salvar', $campanha),
        'partidas' => route('campanhas.jogos.partidas.criar', $campanha),
        'base'     => url('/campanhas/' . $campanha->id . '/jogos'),
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<script src="{{ asset('js/jogos-comum.js') }}?v={{ @filemtime(public_path('js/jogos-comum.js')) }}"></script>
<script src="{{ asset('js/jogos-lobby.js') }}?v={{ @filemtime(public_path('js/jogos-lobby.js')) }}"></script>
@endsection
