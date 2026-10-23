@extends('layout')

@section('conteudo')
@php
    $editando = isset($personagem);
    $habilidadesSalvas = $personagem->habilidades ?? [];
    $pontosGastosAtributos = ($personagem->forca ?? 0) + ($personagem->constituicao ?? 0) + ($personagem->destreza ?? 0)
        + ($personagem->conhecimento ?? 0) + ($personagem->carisma ?? 0) + ($personagem->percepcao ?? 0) + ($personagem->alma ?? 0);
    $pontosRestantesIniciais = $editando ? (300 - $pontosGastosAtributos) : 300;
    

    $vitaisExtrasSalvos = $personagem->vitais_extras ?? [];
    if (is_string($vitaisExtrasSalvos)) {
        $vitaisExtrasSalvos = json_decode($vitaisExtrasSalvos, true) ?: [];
    }
@endphp
<style>
    /* Cards de Talentos Mundanos e Maldições precisam caber textos grandes */
    .talento-card {
        display: flex;
        flex-direction: column;
        gap: 8px;
        width: 100%;
        box-sizing: border-box;
        padding: 14px;
    }

    .talento-titulo-exibido {
        margin: 0 0 4px 0;
        color: var(--tinta, #eee);
        font-size: 1.05rem;
        line-height: 1.3;
        word-break: break-word;
        overflow-wrap: break-word;
        white-space: pre-wrap;
    }

    .talento-titulo {
        width: 100%;
        box-sizing: border-box;
    }

    .talento-descricao {
        width: 100%;
        min-height: 70px;
        max-height: 320px;
        box-sizing: border-box;
        resize: vertical;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-word;
        overflow-wrap: break-word;
        font-family: inherit;
        line-height: 1.35;
    }

    .talento-descricao[readonly] {
        background: rgba(255, 255, 255, 0.03);
    }
    /* ===== Atributos vitais: controles de ajuste ===== */
.vital-ajuste {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: 12px 18px;
    margin-top: 10px;
}

.vital-ajuste-campo {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .85rem;
    color: var(--tinta-fraca, #aaa);
}

.vital-ajuste-campo > span,
.vital-ajuste-campo > label {
    white-space: nowrap;
}

.vital-ajuste-campo input[type="number"],
.vital-ajuste-campo input[type="text"] {
    height: 36px;
    box-sizing: border-box;
    padding: 0 10px;
    min-width: 0;
    text-align: center;
}

/* tira as setinhas do navegador, que cortavam o número */
.vital-ajuste-campo input[type="number"] {
    width: 96px;
}

.vital-ajuste-campo input[type="color"] {
    width: 44px;
    height: 36px;
    padding: 3px;
    cursor: pointer;
    background: transparent;
    border: 1px solid var(--linha, #444);
    border-radius: 8px;
    box-sizing: border-box;
}
.vital-ajuste-campo input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
.vital-ajuste-campo input[type="color"]::-webkit-color-swatch { border: none; border-radius: 5px; }

.vital-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    padding: 0;
    font-size: 1.1rem;
    line-height: 1;
    cursor: pointer;
    border-radius: 8px;
    background: #1f1212;
    color: #fff;
    border: 1px solid var(--linha, #444);
    transition: .2s;
}
.vital-btn:hover { filter: brightness(1.4); }

.vital-extra { margin-top: 18px; }
.vital-extra .barra-preenchimento { transition: width .25s ease; }

/* ===== Formulário "Novo atributo vital" ===== */
.vital-novo {
    max-width: 620px;
    margin: 24px auto 0;
    padding: 18px 20px 20px;
    border: 1px dashed var(--linha, #444);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.02);
    box-sizing: border-box;
}

.vital-novo h4 {
    margin: 0 0 16px 0;
    text-align: center;
    font-size: 1rem;
    letter-spacing: .5px;
}

.vital-novo .vital-ajuste {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 14px;
    align-items: end;
}

/* rótulo em cima, campo embaixo, ocupando a coluna toda */
.vital-novo .vital-ajuste-campo {
    flex-direction: column;
    align-items: stretch;
    gap: 6px;
    min-width: 0;
    text-align: left;
}
.vital-novo .vital-ajuste-campo input[type="number"],
.vital-novo .vital-ajuste-campo input[type="text"],
.vital-novo .vital-ajuste-campo input[type="color"] {
    width: 100%;
}
.vital-novo .vital-ajuste-campo input[type="text"] { text-align: left; }

/* Nome ocupa 3 colunas; Cor, Atual e Máx ocupam 1 cada */
.vital-novo .vital-ajuste-campo:nth-child(1) { grid-column: span 3; }
.vital-novo .vital-ajuste-campo:nth-child(2),
.vital-novo .vital-ajuste-campo:nth-child(3),
.vital-novo .vital-ajuste-campo:nth-child(4) { grid-column: span 1; }

/* botão centralizado, embaixo */
.vital-novo .vital-ajuste > .button {
    grid-column: 1 / -1;
    justify-self: center;
    margin-top: 4px;
}

@media (max-width: 560px) {
    .vital-novo .vital-ajuste { grid-template-columns: repeat(3, 1fr); }
    .vital-novo .vital-ajuste-campo:nth-child(1) { grid-column: 1 / -1; }
}
</style>
<div class="container">
    <div class="card ficha-card">

        <div class="ficha-header">
            <span class="selo">△</span>
            <h1>{{ $editando ? 'Editar Ficha do Agente' : 'Criar Ficha do Agente' }}</h1>
            <p class="ficha-subtitulo">Registro Confidencial</p>
        </div>

        <hr>

    <form action="{{ $editando ? route('personagens.update', $personagem->id) : route('fichafeita.store') }}" method="POST">
        @csrf
        @if($editando)
            @method('PUT')
        @endif

        @include('partials.anotacoes')
        
            <div class="ficha-header colapsavel">
                <h1 id="titulo-info-pessoais">Informações Pessoais</h1>
                <button class="botao-ocultar" id="ocultar-info-pessoais">
                    <span class="seta">▾</span>
                </button>
            </div>
            <div class="dados-pessoais" id="dados-pessoais">
                <div class="campo">
                    <label for="nome">Nome</label>
                    <input id="nome" name="nome" type="text" value="{{ old('nome', $personagem->nome ?? '') }}" placeholder="Digite o nome do agente">
                </div>
                <div class="campo">
                    <label for="idade">Raça</label>
                    <input id="raca" name="raca" type="text" value="{{ old('raca', $personagem->raca ?? '') }}" placeholder="Digite a raça do agente">
                </div>
                @include('partials.maldicoes-campo')
                <div class="campo">
                    <label for="idade">Idade</label>
                    <input id="idade" name="idade" type="number" style="width:40%" value="{{ old('idade', $personagem->idade ?? '') }}" placeholder="Digite a idade do agente">
                </div>
            </div>

            <hr>
            <div class="ficha-header colapsavel">
                    <h1 id="titulo-info-tecnicas">Informações Técnicas</h1>
                    <button class="botao-ocultar" id="ocultar-info-tecnicas">
                        <span class="seta">▾</span>
                    </button>
            </div>
            <div class="dados-pessoais" id="info-tecnicas">
                <div class="campo" style="display: flex">
                    <label for="idade">Profissão</label>
                    <select id="profissao" name="profissao" type="text" style="width:40%">
                        @php $profissaoSalva = $personagem->profissao ?? 'Selecione'; @endphp
                        <option {{ $profissaoSalva == 'Selecione' ? 'selected' : '' }}>Selecione</option>
                        <option id="criminoso" {{ $profissaoSalva == 'Criminoso' ? 'selected' : '' }}>Criminoso</option>
                        <option id="lutador" {{ $profissaoSalva == 'Lutador' ? 'selected' : '' }}>Lutador</option>
                        <option id="intelectual" {{ $profissaoSalva == 'Intelectual' ? 'selected' : '' }}>Intelectual</option>
                        <option id="comunicador" {{ $profissaoSalva == 'Comunicador' ? 'selected' : '' }}>Comunicador</option>
                        <option id="construtor" {{ $profissaoSalva == 'Construtor' ? 'selected' : '' }}>Construtor</option>
                    </select>
                    <button class="button" id="distribuir-niveis">Distribuir Níveis</button>
                </div>
                <div class="campo">
                    <label for="idade">Nível Mundano</label>
                    <input id="nivel-mundano" name="nivel_mundano" type="number" style="width:40%" value="{{ old('nivel_mundano', $personagem->nivel_mundano ?? '') }}" placeholder="Digite o Nível Mundano do agente">
                </div>
                <div class="campo">
                    <label for="idade">Experiência Mundana</label>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <input id="experiencia-mundana" name="experiencia_mundana" type="number" style="width:40%" value="{{ old('experiencia_mundana', $personagem->experiencia_mundana ?? '') }}" placeholder="Experiência Mundana do agente">
                        <p id="experiencia-total" style="margin:0;"></p>
                    </div>
                </div>
                <div class="campo">
                    <label for="nome">Porte físico</label>
                    @php $porteSalvo = $personagem->porte_fisico ?? 'Selecione'; @endphp
                    <select id="porte-fisico" name="porte_fisico">
                        <option {{ $porteSalvo == 'Selecione' ? 'selected' : '' }}>Selecione</option>
                        <option value="Comum" {{ $porteSalvo == 'Comum' ? 'selected' : '' }}>Comum</option>
                        <option value="Magro" {{ $porteSalvo == 'Magro' ? 'selected' : '' }}>Magro</option>
                        <option value="Musculoso" {{ $porteSalvo == 'Musculoso' ? 'selected' : '' }}>Musculoso</option>
                        <option value="Gordo" {{ $porteSalvo == 'Gordo' ? 'selected' : '' }}>Gordo</option>
                        <option value="Infantil" {{ $porteSalvo == 'Infantil' ? 'selected' : '' }}>Infantil</option>
                        <option value="Idoso" {{ $porteSalvo == 'Idoso' ? 'selected' : '' }}>Idoso</option>
                    </select>
                </div>
                <div class="campo">
                    <label for="idade">BE</label>
                    <input id="be" name="be" type="number" style="width:40%" value="{{ old('be', $personagem->be ?? '') }}" placeholder="Digite o BE do agente" readonly>
                </div>
            </div>

            <hr>
            
            <div class="ficha-header colapsavel">
                    <h1 id="titulo-atributos-vitais">Atributo Vitais</h1>
                    <button class="botao-ocultar" id="ocultar-atributos-vitais">
                        <span class="seta">▾</span>
                    </button>
            </div>
            <div class="barras" id="barras-atributos-vitais">
 
                <div class="barra-grupo">
                    <div class="barra-topo">
                        <span class="barra-nome">Vida</span>
                        <span class="barra-valor" id="vida" style="color:white">{{ $personagem->vida_atual ?? '?' }} / {{ $personagem->vida_maxima ?? '?' }}</span>
                    </div>
                    <div class="barra-fundo">
                        <div class="barra-preenchimento barra-vida" style="width: 100%;"></div>
                    </div>
                    <div class="vital-ajuste" data-vital="vida"></div>
                </div>
                <input type="hidden" id="vida_atual" name="vida_atual" value="{{ $personagem->vida_atual ?? '' }}">
                <input type="hidden" id="vida_maxima" name="vida_maxima" value="{{ $personagem->vida_maxima ?? '' }}">
            
                <div class="barra-grupo">
                    <div class="barra-topo">
                        <span class="barra-nome">Sanidade</span>
                        <span class="barra-valor" id="sanidade" style="color:white">{{ $personagem->sanidade_atual ?? '?' }} / {{ $personagem->sanidade_maxima ?? '?' }}</span>
                    </div>
                    <div class="barra-fundo">
                        <div class="barra-preenchimento barra-sanidade" style="width: 100%;"></div>
                    </div>
                    <div class="vital-ajuste" data-vital="sanidade"></div>
                </div>
                <input type="hidden" id="sanidade_atual" name="sanidade_atual" value="{{ $personagem->sanidade_atual ?? '' }}">
                <input type="hidden" id="sanidade_maxima" name="sanidade_maxima" value="{{ $personagem->sanidade_maxima ?? '' }}">
            
                <div class="barra-grupo">
                    <div class="barra-topo">
                        <span class="barra-nome">Aura</span>
                        <span class="barra-valor" id="aura" style="color:white">{{ $personagem->aura_atual ?? '?' }} / {{ $personagem->aura_maxima ?? '?' }}</span>
                    </div>
                    <div class="barra-fundo">
                        <div class="barra-preenchimento barra-aura" style="width: 100%;"></div>
                    </div>
                    <div class="vital-ajuste" data-vital="aura"></div>
                </div>
                <input type="hidden" id="aura_atual" name="aura_atual" value="{{ $personagem->aura_atual ?? '' }}">
                <input type="hidden" id="aura_maxima" name="aura_maxima" value="{{ $personagem->aura_maxima ?? '' }}">
            
                {{-- Atributos vitais personalizados --}}
                <div id="vitais-extras-lista"></div>
            
                <div class="vital-novo">
                    <h4>Novo atributo vital</h4>
                    <div class="vital-ajuste">
                        <div class="vital-ajuste-campo">
                            <label for="vital-novo-nome">Nome</label>
                            <input id="vital-novo-nome" type="text" placeholder="Ex: Fome, Energia...">
                        </div>
                        <div class="vital-ajuste-campo">
                            <label for="vital-novo-cor">Cor</label>
                            <input id="vital-novo-cor" type="color" value="#3b82f6">
                        </div>
                        <div class="vital-ajuste-campo">
                            <label for="vital-novo-atual">Atual</label>
                            <input id="vital-novo-atual" type="number" placeholder="0">
                        </div>
                        <div class="vital-ajuste-campo">
                            <label for="vital-novo-max">Máx</label>
                            <input id="vital-novo-max" type="number" min="0" placeholder="0">
                        </div>
                        <button type="button" class="button" id="vital-novo-adicionar">+ Adicionar</button>
                    </div>
                </div>
            
                <input type="hidden" id="vitais_extras" name="vitais_extras" value="">
            </div>

            <hr>

            <div class="ficha-header colapsavel">
                    <h1 id="titulo-ocultar-atributos">Atributos</h1>
                    <button class="botao-ocultar" id="ocultar-atributos">
                        <span class="seta">▾</span>
                    </button>
            </div>

            <div class="atributos" id="atributos-ocultar">
                <div class="ficha-header colapsavel">
                    <h3 class="secao-titulo" id="pontos-restantes-texto">Pontos: {{ $pontosRestantesIniciais }}</h3>
                </div>
                <div class="atributos-grid">
                    <div class="atributo-card">
                        <span class="atributo-nome">Força</span>
                        <input type="text" class="atributo-valor" placeholder="0" id="forca" name="forca" value="{{ old('forca', $personagem->forca ?? '') }}">
                        <span class="atributo-nome">Mod</span>
                        <input type="text" class="mod" placeholder="0" id="mod-forca" name="mod_forca" value="{{ isset($personagem->forca) ? intdiv((int)$personagem->forca, 10) : '' }}" readonly>
                    </div>
                    <div class="atributo-card" id="card-constituicao">
                        <span class="atributo-nome">Constituição</span>
                        <input type="text" class="atributo-valor" placeholder="0" id="constituicao" name="constituicao" value="{{ old('constituicao', $personagem->constituicao ?? '') }}">
                        <span class="atributo-nome">Mod</span>
                        <input type="text" class="mod" placeholder="0" id="mod-constituicao" name="mod_constituicao" value="{{ isset($personagem->constituicao) ? intdiv((int)$personagem->constituicao, 10) : '' }}" readonly>
                    </div>
                    <div class="atributo-card">
                        <span class="atributo-nome">Destreza</span>
                        <input type="text" class="atributo-valor" placeholder="0" id="destreza" name="destreza" value="{{ old('destreza', $personagem->destreza ?? '') }}">
                        <span class="atributo-nome">Mod</span>
                        <input type="text" class="mod" placeholder="0" id="mod-destreza" name="mod_destreza" value="{{ isset($personagem->destreza) ? intdiv((int)$personagem->destreza, 10) : '' }}" readonly>
                    </div>
                    <div class="atributo-card">
                        <span class="atributo-nome">Conhecimento</span>
                        <input type="text" class="atributo-valor" placeholder="0" id="conhecimento" name="conhecimento" value="{{ old('conhecimento', $personagem->conhecimento ?? '') }}">
                        <span class="atributo-nome">Mod</span>
                        <input type="text" class="mod" placeholder="0" id="mod-conhecimento" name="mod_conhecimento" value="{{ isset($personagem->conhecimento) ? intdiv((int)$personagem->conhecimento, 10) : '' }}" readonly>
                    </div>
                    <div class="atributo-card">
                        <span class="atributo-nome">Carisma</span>
                        <input type="text" class="atributo-valor" placeholder="0" id="carisma" name="carisma" value="{{ old('carisma', $personagem->carisma ?? '') }}">
                        <span class="atributo-nome">Mod</span>
                        <input type="text" class="mod" placeholder="0" id="mod-carisma" name="mod_carisma" value="{{ isset($personagem->carisma) ? intdiv((int)$personagem->carisma, 10) : '' }}" readonly>
                    </div>
                    <div class="atributo-card">
                        <span class="atributo-nome">Percepção</span>
                        <input type="text" class="atributo-valor" placeholder="0" id="percepcao" name="percepcao" value="{{ old('percepcao', $personagem->percepcao ?? '') }}">
                        <span class="atributo-nome">Mod</span>
                        <input type="text" class="mod" placeholder="0" id="mod-percepcao" name="mod_percepcao" value="{{ isset($personagem->percepcao) ? intdiv((int)$personagem->percepcao, 10) : '' }}" readonly>
                    </div>
                    <div class="atributo-card">
                        <span class="atributo-nome">Alma</span>
                        <input type="text" class="atributo-valor" placeholder="0" id="alma" name="alma" value="{{ old('alma', $personagem->alma ?? '') }}">
                        <span class="atributo-nome">Mod</span>
                        <input type="text" class="mod" placeholder="0" id="mod-alma" name="mod_alma" value="{{ isset($personagem->alma) ? intdiv((int)$personagem->alma, 10) : '' }}" readonly>
                    </div>
                    <div class="atributo-card">
                        <span class="atributo-nome" id="nome-sorte" style="{{ isset($personagem->sorte) ? 'color: var(--tinta-fraca);' : 'color: rgba(189, 3, 3, 0.658);' }}">Sorte</span>
                        <input type="text" id="sorte" name="sorte" class="atributo-valor" placeholder="0" value="{{ old('sorte', $personagem->sorte ?? '') }}" readonly>
                    </div>
                    
                    <button class="button" style="width: 30%" id="espalhar-pontos">
                        Espalhar Pontos
                    </button>

                    <button class="button" style="width: 30%" id="re-roll" title="Todo jogador tem direito a re-rolar algum aspecto rolável de sua ficha. Aperte para desbloquear a sua re-rolagem">
                        Re-roll Gratuito
                    </button>
                </div>
            </div>

            <hr>
            <div class="ficha-header colapsavel">
                <h1 id="titulo-rolagem-setor">Rolagem e Acessos</h1>
                <button class="botao-ocultar" id="ocultar-rolagem-setor">
                    <span class="seta">▾</span>
                </button>
            </div>

            <div class="ficha-header" id="maldicao-div">
                <button class="button" style="margin-top:8px;" id="maldicao-adicao">
                    Maldição
                </button>
            </div>

            <div class="ficha-header" id="acessos-div">
                <button class="button" style="margin-top:8px;" id="talentos-mundanos">
                    Talentos Mundanos
                </button>
                <button type="button" class="button" style="margin-top:8px;" id="anotacoes-abrir">
                    Anotações
                </button>
            </div>

            <div class="ficha-header" id="rolagem-setor-conteudo">
                <button class="button" id="rolarD100" style="width:40%;margin-top:10px">
                    Rolagem
                </button>
            </div>

            <div class="rolador-dados" id="rolador-dados">
                <h3 class="secao-titulo">Rolador de Dados</h3>
                <div class="rolador-dados-linha">
                    <div class="rolador-dados-campo">
                        <label for="dado-rolador-qtd">Qtd</label>
                        <input id="dado-rolador-qtd" name="dado_rolador_qtd" type="number" min="1" value="1">
                    </div>
                    <div class="rolador-dados-campo">
                        <label for="dado-rolador-tipo">Dado</label>
                        <select id="dado-rolador-tipo" name="dado_rolador_tipo">
                            <option value="4">d4</option>
                            <option value="6">d6</option>
                            <option value="8">d8</option>
                            <option value="10">d10</option>
                            <option value="12">d12</option>
                            <option value="20">d20</option>
                            <option value="100">d100</option>
                        </select>
                    </div>
                    <button class="button" type="button" id="rolar-dado-diverso">Rolar</button>
                </div>

                <div class="rolador-resultado-card" id="rolador-resultado-card">
                    <span class="rotulo">Resultado</span>
                    <span class="numero" id="resultado-dado-diverso">—</span>
                    <span class="detalhe" id="resultado-dado-diverso-detalhe"></span>
                </div>
            </div>

            <hr>

            <div class="habilidades">
                <div class="ficha-header colapsavel">
                    <h1 id="titulo-habilidades">Habilidades</h1>
                    <button class="botao-ocultar" id="ocultar-habilidades">
                        <span class="seta">▾</span>
                    </button>
                </div>
                <div class="ficha-header" id="pontos-a-distribuir">
                    <h4 id="pontos-habilidade-restantes-texto">Pontos a distribuir: </h4>
                </div>

                <div id="habilidades-conteudo">

        <!-- ==================== HABILIDADES FÍSICAS ==================== -->
        <div class="habilidades-lista">
            <div class="ficha-header colapsavel">
                <h3 id="titulo-habilidades-fisicas">Habilidades Físicas</h3>
                <button class="botao-ocultar" id="ocultar-habilidades-fisicas">
                    <span class="seta">▾</span>
                </button>
            </div>
            <div id="conteudo-habilidades-fisicas">

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Luta</span>
                    <input type="text" class="habilidade-mod" name="mod_luta" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="luta" name="luta" data-habilidade="luta" placeholder="0" value="{{ $habilidadesSalvas['luta'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="luta" title="Rolar Luta"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Resiliência</span>
                    <input type="text" class="habilidade-mod" name="mod_resiliencia" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="resiliencia" name="resiliencia" data-habilidade="resiliencia" placeholder="0" value="{{ $habilidadesSalvas['resiliencia'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="resiliencia" title="Rolar Resiliência"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Acrobacia</span>
                    <input type="text" class="habilidade-mod" name="mod_acrobacia" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="acrobacia" name="acrobacia" data-habilidade="acrobacia" placeholder="0" value="{{ $habilidadesSalvas['acrobacia'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="acrobacia" title="Rolar Acrobacia"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Atletismo</span>
                    <input type="text" class="habilidade-mod" name="mod_atletismo" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="atletismo" name="atletismo" data-habilidade="atletismo" placeholder="0" value="{{ $habilidadesSalvas['atletismo'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="atletismo" title="Rolar Atletismo"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Agarrar</span>
                    <input type="text" class="habilidade-mod" name="mod_agarrar" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="agarrar" name="agarrar" data-habilidade="agarrar" placeholder="0" value="{{ $habilidadesSalvas['agarrar'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="agarrar" title="Rolar Agarrar"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Arremesso</span>
                    <input type="text" class="habilidade-mod" name="mod_arremesso" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="arremesso" name="arremesso" data-habilidade="arremesso" placeholder="0" value="{{ $habilidadesSalvas['arremesso'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="arremesso" title="Rolar Arremesso"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Desviar</span>
                    <input type="text" class="habilidade-mod" name="mod_desviar" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="desviar" name="desviar" data-habilidade="desviar" placeholder="0" value="{{ $habilidadesSalvas['desviar'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="desviar" title="Rolar Desviar"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Contra ataque</span>
                    <input type="text" class="habilidade-mod" name="mod_contra_ataque" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="contra-ataque" name="contra_ataque" data-habilidade="contra-ataque" placeholder="0" value="{{ $habilidadesSalvas['contra_ataque'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="contra-ataque" title="Rolar Contra ataque"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Bloquear</span>
                    <input type="text" class="habilidade-mod" name="mod_bloquear" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="bloquear" name="bloquear" data-habilidade="bloquear" placeholder="0" value="{{ $habilidadesSalvas['bloquear'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="bloquear" title="Rolar Bloquear"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Iniciativa</span>
                    <input type="text" class="habilidade-mod" name="mod_iniciativa" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="iniciativa" name="iniciativa" data-habilidade="iniciativa" placeholder="0" value="{{ $habilidadesSalvas['iniciativa'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="iniciativa" title="Rolar Iniciativa"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Furtividade</span>
                    <input type="text" class="habilidade-mod" name="mod_furtividade" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="furtividade" name="furtividade" data-habilidade="furtividade" placeholder="0" value="{{ $habilidadesSalvas['furtividade'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="furtividade" title="Rolar Furtividade"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Enxergar</span>
                    <input type="text" class="habilidade-mod" name="mod_enxergar" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="enxergar" name="enxergar" data-habilidade="enxergar" placeholder="0" value="{{ $habilidadesSalvas['enxergar'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="enxergar" title="Rolar Enxergar"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Olfato</span>
                    <input type="text" class="habilidade-mod" name="mod_olfato" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="olfato" name="olfato" data-habilidade="olfato" placeholder="0" value="{{ $habilidadesSalvas['olfato'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="olfato" title="Rolar Olfato"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Audição</span>
                    <input type="text" class="habilidade-mod" name="mod_audicao" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="audicao" name="audicao" data-habilidade="audicao" placeholder="0" value="{{ $habilidadesSalvas['audicao'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="audicao" title="Rolar Audição"></button>
                </div>

            </div>
        </div>


        <!-- ==================== HABILIDADES INTELECTUAIS ==================== -->
        <div class="habilidades-lista">
            <div class="ficha-header colapsavel">
                <h3 id="titulo-habilidades-intelectuais">Habilidades Intelectuais</h3>
                <button class="botao-ocultar" id="ocultar-habilidades-intelectuais">
                    <span class="seta">▾</span>
                </button>
            </div>
            <div id="conteudo-habilidades-intelectuais">

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Maldição</span>
                    <input type="text" class="habilidade-mod" name="mod_maldicao_hab" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="maldicao" name="maldicao_hab" placeholder="0" value="{{ $habilidadesSalvas['maldicao_hab'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="maldicao" title="Rolar Maldição"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Precisão</span>
                    <input type="text" class="habilidade-mod" name="mod_precisao" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="precisao" name="precisao" placeholder="0" value="{{ $habilidadesSalvas['precisao'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="precisao" title="Rolar Precisão"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Investigação</span>
                    <input type="text" class="habilidade-mod" name="mod_investigacao" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="investigacao" name="investigacao" placeholder="0" value="{{ $habilidadesSalvas['investigacao'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="investigacao" title="Rolar Investigação"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Sobrevivência</span>
                    <input type="text" class="habilidade-mod" name="mod_sobrevivencia" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="sobrevivencia" name="sobrevivencia" placeholder="0" value="{{ $habilidadesSalvas['sobrevivencia'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="sobrevivencia" title="Rolar Sobrevivência"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Construção</span>
                    <input type="text" class="habilidade-mod" name="mod_construcao" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="construcao" name="construcao" placeholder="0" value="{{ $habilidadesSalvas['construcao'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="construcao" title="Rolar Construção"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Procura</span>
                    <input type="text" class="habilidade-mod" name="mod_procura" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="procura" name="procura" placeholder="0" value="{{ $habilidadesSalvas['procura'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="procura" title="Rolar Procura"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Intuição</span>
                    <input type="text" class="habilidade-mod" name="mod_intuicao" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="intuicao" name="intuicao" placeholder="0" value="{{ $habilidadesSalvas['intuicao'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="intuicao" title="Rolar Intuição"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Tecnologia</span>
                    <input type="text" class="habilidade-mod" name="mod_tecnologia" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="tecnologia" name="tecnologia" placeholder="0" value="{{ $habilidadesSalvas['tecnologia'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="tecnologia" title="Rolar Tecnologia"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Religião</span>
                    <input type="text" class="habilidade-mod" name="mod_religiao" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="religiao" name="religiao" placeholder="0" value="{{ $habilidadesSalvas['religiao'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="religiao" title="Rolar Religião"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Raciocínio</span>
                    <input type="text" class="habilidade-mod" name="mod_raciocinio" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="raciocinio" name="raciocinio" placeholder="0" value="{{ $habilidadesSalvas['raciocinio'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="raciocinio" title="Rolar Raciocínio"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Medicina</span>
                    <input type="text" class="habilidade-mod" name="mod_medicina" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="medicina" name="medicina" placeholder="0" value="{{ $habilidadesSalvas['medicina'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="medicina" title="Rolar Medicina"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Primeiros socorros</span>
                    <input type="text" class="habilidade-mod" name="mod_primeiros_socorros" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="primeiros-socorros" name="primeiros_socorros" placeholder="0" value="{{ $habilidadesSalvas['primeiros_socorros'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="primeiros-socorros" title="Rolar Primeiros socorros"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Culinária</span>
                    <input type="text" class="habilidade-mod" name="mod_culinaria" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="culinaria" name="culinaria" placeholder="0" value="{{ $habilidadesSalvas['culinaria'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="culinaria" title="Rolar Culinária"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Dirigir</span>
                    <input type="text" class="habilidade-mod" name="mod_dirigir" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="dirigir" name="dirigir" placeholder="0" value="{{ $habilidadesSalvas['dirigir'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="dirigir" title="Rolar Dirigir"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Explosivos</span>
                    <input type="text" class="habilidade-mod" name="mod_explosivos" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="explosivos" name="explosivos" placeholder="0" value="{{ $habilidadesSalvas['explosivos'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="explosivos" title="Rolar Explosivos"></button>
                </div>

            </div>
        </div>


        <!-- ==================== HABILIDADES INTROSPECTIVAS ==================== -->
        <div class="habilidades-lista">
            <div class="ficha-header colapsavel">
                <h3 id="titulo-habilidades-introspectivas">Habilidades Introspectivas</h3>
                <button class="botao-ocultar" id="ocultar-habilidades-introspectivas">
                    <span class="seta">▾</span>
                </button>
            </div>
            <div id="conteudo-habilidades-introspectivas">

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Fotografia</span>
                    <input type="text" class="habilidade-mod" name="mod_fotografia" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="fotografia" name="fotografia" placeholder="0" value="{{ $habilidadesSalvas['fotografia'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="fotografia" title="Rolar Fotografia"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Lábia</span>
                    <input type="text" class="habilidade-mod" name="mod_labia" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="labia" name="labia" placeholder="0" value="{{ $habilidadesSalvas['labia'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="labia" title="Rolar Lábia"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Barganhar</span>
                    <input type="text" class="habilidade-mod" name="mod_barganhar" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="barganhar" name="barganhar" placeholder="0" value="{{ $habilidadesSalvas['barganhar'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="barganhar" title="Rolar Barganhar"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Manipulação</span>
                    <input type="text" class="habilidade-mod" name="mod_manipulacao" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="manipulacao" name="manipulacao" placeholder="0" value="{{ $habilidadesSalvas['manipulacao'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="manipulacao" title="Rolar Manipulação"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Intimidar</span>
                    <input type="text" class="habilidade-mod" name="mod_intimidar" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="intimidar" name="intimidar" placeholder="0" value="{{ $habilidadesSalvas['intimidar'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="intimidar" title="Rolar Intimidar"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Arte</span>
                    <input type="text" class="habilidade-mod" name="mod_arte" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="arte" name="arte" placeholder="0" value="{{ $habilidadesSalvas['arte'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="arte" title="Rolar Arte"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Disfarce</span>
                    <input type="text" class="habilidade-mod" name="mod_disfarce" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="disfarce" name="disfarce" placeholder="0" value="{{ $habilidadesSalvas['disfarce'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="disfarce" title="Rolar Disfarce"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Performance</span>
                    <input type="text" class="habilidade-mod" name="mod_performance" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="performance" name="performance" placeholder="0" value="{{ $habilidadesSalvas['performance'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="performance" title="Rolar Performance"></button>
                </div>

                <div class="habilidade-linha">
                    <span class="habilidade-nome">Detectar mentira</span>
                    <input type="text" class="habilidade-mod" name="mod_detectar_mentira" value="+0" readonly>
                    <input type="text" class="habilidade-valor" id="detectar-mentira" name="detectar_mentira" placeholder="0" value="{{ $habilidadesSalvas['detectar_mentira'] ?? '' }}">
                    <button class="botao-rolar-habilidade" data-habilidade="detectar-mentira" title="Rolar Detectar mentira"></button>
                </div>

            </div>
        </div>

    </div>

</div>

    <hr>

        <div class="itens-acoes">
            <div class="ficha-header colapsavel">
                <h1 id="titulo-itens">Itens e Ações</h1>
                <button class="botao-ocultar" id="ocultar-itens">
                    <span class="seta">▾</span>
                </button>
            </div>

            <div id="itens-conteudo">
                <div class="item-form-linha">
                    <div class="item-form-campo item-form-nome">
                        <label for="item-nome">Nome</label>
                        <input id="item-nome" name="item_nome" type="text" placeholder="Nome do item ou ação">
                    </div>
                    <div class="item-form-campo item-form-categoria">
                        <label for="item-categoria">Categoria</label>
                        <select id="item-categoria" name="item_categoria">
                            <option>Selecione</option>
                            <option value="Arma">Arma</option>
                            <option value="Ataque">Ataque</option>
                            <option value="Consumível">Consumível</option>
                            <option value="Equipamento">Equipamento</option>
                            <option value="Habilidade Amaldiçoada">Habilidade Amaldiçoada</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>
                    <div class="item-form-campo item-form-qtd">
                        <label for="item-qtd-dados">Nº de dados</label>
                        <input id="item-qtd-dados" name="item_qtd_dados" type="number" min="0" placeholder="1">
                    </div>
                    <div class="item-form-campo item-form-dado">
                        <label for="item-dado">Dado</label>
                        <select id="item-dado" name="item_dado">
                            <option value="0">Nenhum</option>
                            <option value="4">d4</option>
                            <option value="6">d6</option>
                            <option value="8">d8</option>
                            <option value="10">d10</option>
                            <option value="12">d12</option>
                            <option value="20">d20</option>
                            <option value="100">d100</option>
                        </select>
                    </div>
                    <div class="item-form-campo item-form-valor-fixo">
                        <label for="item-valor-fixo">Valor fixo</label>
                        <input id="item-valor-fixo" name="item_valor_fixo" type="number" placeholder="0">
                    </div>
                </div>

                <div class="campo campo-descricao">
                    <label for="item-descricao">Descrição</label>
                    <textarea id="item-descricao" name="item_descricao" placeholder="Descreva o item ou ação"></textarea>
                </div>

                <div class="campo campo-descricao">
                    <label for="item-modificacoes">Modificações</label>
                    <textarea id="item-modificacoes" name="item_modificacoes" placeholder="Bônus, penalidades, efeitos extras..."></textarea>
                </div>

                <div class="itens-botoes">
                    <button class="botao-adicionar-talento" type="button" id="adicionar-item">
                        + Adicionar Item
                    </button>
                    <button class="botao-rolar-preview" type="button" id="rolar-preview-item" title="Rola o dano configurado acima, sem precisar adicionar o item">
                        🎲 Rolar Dano
                    </button>
                </div>

                <p class="rolar-preview-resultado" id="rolar-preview-resultado"></p>

                <div class="itens-adicionados-titulo" id="itens-adicionados-titulo" style="display:none">
                    <h3 class="secao-titulo">Itens Adicionados</h3>
                </div>
                <div class="lista-itens" id="lista-itens"></div>
            </div>
                

            <hr>

            <div class="ficha-header colapsavel">
                <h1 id="titulo-descricao">Descrição</h1>
                <button class="botao-ocultar" id="ocultar-descricao">
                    <span class="seta">▾</span>
                </button>
            </div>
            <div id="descricao-conteudo">
                <div class="campo campo-descricao">
                    <textarea id="descricao" name="descricao" placeholder="Digite a descrição do agente">{{ old('descricao', $personagem->descricao ?? '') }}</textarea>
                </div>
            </div>

            <button class="button" type="button" id="preencher-teste" style=" margin-top:8px;margin-bottom:8px;">
                Preencher com Dados de Teste
            </button>

            <button class="button" type="submit">{{ $editando ? 'Salvar Alterações' : 'Salvar Ficha' }}</button>

        </div>

        <div class="card card-rolagem card-talentos" id="talentos-mundanos-card" style="max-height: 50%; overflow-y:auto;">
            <button class="botao-fechar" id="fechar-talentos">X</button>

            <div class="botoes-grid" style="text-align:center">
                <h1>Talentos Mundanos</h1>
            </div>

            <div class="lista-talentos" style="justify-content:center;" id="lista-talentos"></div>

            <button class="botao-adicionar-talento" id="adicionar-talento">
                + Adicionar Talento
            </button>
        </div>

        <div class="card card-rolagem" id="card-rolagem">
            <button class="botao-fechar" id="x">✕</button>
            <div class="titulo-rolagem">
                <h1>Rolagem</h1>
            </div>

            <hr>
            

            <div class="botoes-grid">
                <button class="semi-botao atributo-card" style="color:white" id="rolagem-sorte">
                    Rolar sua Sorte
                </button>

                <button class="semi-botao atributo-card" style="color:white" id="rolagem-vida">
                    Rolar sua Vida
                </button>

                <button class="semi-botao atributo-card" style="color:white" id="rolagem-sanidade">
                    Rolar sua Sanidade
                </button>

                <button class="semi-botao atributo-card" style="color:white" id="rolagem-aura">
                    Rolar sua Aura
                </button>

                <button class="semi-botao atributo-card" style="color:white" id="rolagem-maldicao">
                    Rolar sua Maldição
                </button>

                <hr>

                <div class="titulo-rolagem">
                    <h3><p id="resultado">?</p></h3>
                </div>
            </div>
        </div>

        <div class="popup" id="popup">
            <div class="popup-conteudo">
                <h3>Aviso</h3>
                <p id="popup-mensagem"></p>
                <div class="botoes-grid">
                    <button id="popup-fechar">
                        OK
                    </button>
                </div>
            </div>
        </div>

        <div class="popup" id="popup-maldicao">
            <div class="popup-conteudo">
                <p id="mensagem-popup-maldicao"></p>

                <button class="atributo-card" 
                style="width: 60%
                display: flex !important;
                flex-direction: column !important;
                align-items: center;
                justify-content: center;
                text-align: center;
                background: #1f1212;
                border: 1px solid var(--linha);
                border-radius: 14px;
                padding: 16px 11px;
                gap: 8px;
                min-width: 0;
                transition: .3s;"
                id="rolagem-raridade">
                    Rolar Raridade
                </button>
                
                <div id="div-maldicao" style="display:none">
                    <button class="atributo-card" 
                    style="width: 60%
                    display: flex !important;
                    flex-direction: column !important;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    background: #1f1212;
                    border: 1px solid var(--linha);
                    border-radius: 14px;
                    padding: 16px 11px;
                    gap: 8px;
                    min-width: 0;
                    transition: .3s;"
                    id="rolagem-maldicao-def">
                        Rolar Maldição
                    </button>
                </div>

                <button id="popup-fechar-maldicao" style="width: 50%">
                    Fechar
                </button>

                <p id="resultado-maldicao">Resultado: ?</p>
            </div>
        </div>

        <div id="rolagem-habilidade" class="popup">
            <div class="popup-conteudo">
                <h1 id="titulo-rolagem-habilidade">Rolar Habilidade</h1>
                <div class="rolagem-habilidade-info">
                <div class="rolagem-habilidade-valores">
                    <div class="rolagem-habilidade-card">
                        <span class="rotulo">Valor</span>
                        <span class="valor" id="valor-habilidade-popup">0</span>
                    </div>
                    <div class="rolagem-habilidade-card mod">
                        <span class="rotulo">Mod</span>
                        <span class="valor" id="mod-habilidade-popup">+0</span>
                    </div>
                </div>
            </div>
                <div class="resultado-habilidade">

                    <div class="vantagem-extra-campo">
                        <label for="vantagem-extra-input">Vantagens extras</label>
                        <div class="vantagem-extra-linha">
                            <input type="text" id="vantagem-extra-input" name="vantagem_extra_input" placeholder="Ex: +10">
                        </div>
                        <div class="vantagem-extra-lista" id="vantagem-extra-lista"></div>
                    </div>
                    <div class="resultado-habilidade-linha natural">
                        <span class="rotulo">Dado (d100)</span>
                        <span class="numero" id="resultado-natural">?</span>
                    </div>

                    <div class="resultado-habilidade-linha reducao">
                        <span class="rotulo">Redução aplicada</span>
                        <span class="numero" id="resultado-reducao">0</span>
                    </div>

                    <div class="resultado-habilidade-final" id="resultado-final-card">
                        <span class="rotulo">Resultado Final</span>
                        <span class="numero" id="resultado-final">?</span>
                    </div>

                    <button class="botao-rolar-dado-habilidade" id="rolar-dado-habilidade">
                        Rolar
                    </button>
                </div>
                <button id="fechar-rolar-habilidade" style="width: 50%">
                    Fechar
                </button>
            </div>
        </div>

        <div class="popup" id="popup-re-roll">
            <div class="popup-conteudo">
                <h3>Re-Rolagem Grátis</h3>
                <p id="popup-re-roll-mensagem"></p>
                <div class="botoes-grid">

                    <button class="atributo-card" id="re-roll-vida" style="width: 60%
                    display: flex !important;
                    flex-direction: column !important;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    background: #1f1212;
                    border: 1px solid var(--linha);
                    border-radius: 14px;
                    padding: 16px 11px;
                    gap: 8px;
                    min-width: 0;
                    transition: .3s;">
                        Re-rolar seus Pontos de Vida
                    </button>

                    <button class="atributo-card" id="re-roll-sanidade" style="width: 60%
                    display: flex !important;
                    flex-direction: column !important;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    background: #1f1212;
                    border: 1px solid var(--linha);
                    border-radius: 14px;
                    padding: 16px 11px;
                    gap: 8px;
                    min-width: 0;
                    transition: .3s;">
                        Re-rolar seus Pontos de Sanidade
                    </button>

                    <button class="atributo-card" id="re-roll-sorte" style="width: 60%
                    display: flex !important;
                    flex-direction: column !important;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    background: #1f1212;
                    border: 1px solid var(--linha);
                    border-radius: 14px;
                    padding: 16px 11px;
                    gap: 8px;
                    min-width: 0;
                    transition: .3s;">
                        Re-rolar seus Pontos de Sorte
                    </button>

                    <button class="atributo-card" id="re-roll-aura" style="width: 60%
                    display: flex !important;
                    flex-direction: column !important;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    background: #1f1212;
                    border: 1px solid var(--linha);
                    border-radius: 14px;
                    padding: 16px 11px;
                    gap: 8px;
                    min-width: 0;
                    transition: .3s;">
                        Re-rolar seus Pontos de Aura
                    </button>

                    <button id="popup-re-roll-fechar">
                        OK
                    </button>
                </div>
            </div>
        </div>

        <div class="popup" id="distribuir-niveis-popup">
            <div class="popup-conteudo">
                <h3>Distribua seus níveis de Profissão</h3>
                <div id="campos-distribuicao-niveis"></div>
                    <button class="button" id="fechar-distribuir">Fechar</button>
                </div>
        </div>

        <div class="card card-rolagem card-talentos card-maldicoes" id="maldicao-card">
    <button type="button" class="botao-fechar" id="fechar-maldicao-card">X</button>

    <div class="botoes-grid" style="text-align:center">
        <h1>Maldições</h1>
    </div>

    <div class="maldicao-config">
        <div class="campo">
            <label for="nivel-hexomante">Nível de Hexomante</label>
            <input id="nivel-hexomante" name="nivel_hexomante" type="number" min="0" placeholder="Nível">
        </div>
        <div class="campo">
            <label for="ameaca">Ameaça de Maldição</label>
            <input id="ameaca" name="ameaca" type="number" min="0" placeholder="Ameaça">
        </div>
    </div>

        <div class="maldicao-seletor">
            <div class="campo">
                <label for="maldicao-catalogo">Maldição</label>
                <select id="maldicao-catalogo"></select>
            </div>
            <p class="maldicao-previa" id="maldicao-previa"></p>

            <div class="maldicao-custom" id="maldicao-custom">
                <div class="campo">
                    <label for="maldicao-nome-custom">Nome</label>
                    <input id="maldicao-nome-custom" type="text" placeholder="Nome da maldição personalizada">
                </div>
            </div>

            <button class="botao-adicionar-talento" type="button" id="adicionar-maldicao">
                + Adicionar Maldição
            </button>
        </div>

        <div class="lista-maldicoes-ficha" id="lista-maldicoes"></div>
    </div>

    
    </div>

    
    </form>
</div>

<script>window.VITAIS_EXTRAS = @json($vitaisExtrasSalvos);</script>
<script src="{{ asset('js/vitais.js') }}"></script>
@endsection

@section('script')
<script>
    const botaoX = document.getElementById("x");

    //ocultação
    const botaoOcultarInfoPessoais = document.getElementById("ocultar-info-pessoais");
    const dadosPessoais = document.getElementById("dados-pessoais");
    const botaoOcultarInfoTecnicas = document.getElementById("ocultar-info-tecnicas");
    const infoTecnicas = document.getElementById("info-tecnicas");
    const tituloInfoPessoais = document.getElementById("titulo-info-pessoais");
    const tituloInfoTecnicas = document.getElementById("titulo-info-tecnicas");
    const tituloAtributosVitais = document.getElementById("titulo-atributos-vitais");
    const barrasAtributosVitais = document.getElementById("barras-atributos-vitais");
    const botaoVitaisOcultos = document.getElementById("ocultar-atributos-vitais");
    const ocultarAtributos = document.getElementById("atributos-ocultar");
    const botaoOcultarAtributos = document.getElementById("ocultar-atributos");
    const tituloOcultarAtributos = document.getElementById("titulo-ocultar-atributos");
    const botaoOcultarRolagemSetor = document.getElementById("ocultar-rolagem-setor");
    const rolagemSetorConteudo = document.getElementById("rolagem-setor-conteudo");
    const tituloRolagemSetor = document.getElementById("titulo-rolagem-setor");
    const tituloHabilidadesFisicas = document.getElementById("titulo-habilidades-fisicas");
    const botaoOcultarHabilidadesFisicas = document.getElementById("ocultar-habilidades-fisicas");
    const conteudoHabilidadesFisicas = document.getElementById("conteudo-habilidades-fisicas");
    const acessosDiv = document.getElementById("acessos-div");
    const maldicaoDiv = document.getElementById("maldicao-div");
    const roladorDados = document.getElementById("rolador-dados");

    const tituloHabilidadesIntelectuais = document.getElementById("titulo-habilidades-intelectuais");
    const botaoOcultarHabilidadesIntelectuais = document.getElementById("ocultar-habilidades-intelectuais");
    const conteudoHabilidadesIntelectuais = document.getElementById("conteudo-habilidades-intelectuais");

    const tituloHabilidadesIntrospectivas = document.getElementById("titulo-habilidades-introspectivas");
    const botaoOcultarHabilidadesIntrospectivas = document.getElementById("ocultar-habilidades-introspectivas");
    const conteudoHabilidadesIntrospectivas = document.getElementById("conteudo-habilidades-introspectivas");

    const botaoOcultarHabilidades = document.getElementById("ocultar-habilidades");
    const habilidadesConteudo = document.getElementById("habilidades-conteudo");
    const tituloHabilidades = document.getElementById("titulo-habilidades");
    const pontosADistribuir = document.getElementById("pontos-a-distribuir");

    const botaoOcultarDescricao = document.getElementById("ocultar-descricao");
    const descricaoConteudo = document.getElementById("descricao-conteudo");
    const tituloDescricao = document.getElementById("titulo-descricao");

    //Popups
    const popup = document.getElementById("popup");
    const popupMensagem = document.getElementById("popup-mensagem");
    const popupFechar = document.getElementById("popup-fechar");
    const porteFisico = document.getElementById("porte-fisico");
    const popupMaldicao = document.getElementById("popup-maldicao");
    const mensagemPopupMaldicao = document.getElementById("mensagem-popup-maldicao");
    const popupFecharMaldicao = document.getElementById("popup-fechar-maldicao");
    const popupReRoll = document.getElementById("popup-re-roll");
    const popupReRollMensagem = document.getElementById("popup-re-roll-mensagem");
    const popupReRollFechar = document.getElementById("popup-re-roll-fechar");

    //atributos vitais
    const vida = document.getElementById("vida");
    const sanidade = document.getElementById("sanidade");
    const rolagemAura = document.getElementById("rolagem-aura");
    const aura = document.getElementById("aura");

    //rolagens
    const d100 = document.getElementById("rolarD100");
    const resultadoDado = document.getElementById("resultado");
    const rolagemCard = document.getElementById("card-rolagem");
    const rolagemSorte = document.getElementById("rolagem-sorte");
    const rolagemSanidade = document.getElementById("rolagem-sanidade");
    const rolagemVida = document.getElementById("rolagem-vida");
    const nomeSorte = document.getElementById("nome-sorte");
    const rolagemMaldicao = document.getElementById("rolagem-maldicao");
    const rolagemRaridade = document.getElementById("rolagem-raridade");
    const resultadoMaldicao = document.getElementById("resultado-maldicao");
    const divMaldicao = document.getElementById("div-maldicao");
    const rolagemMaldicaoDef = document.getElementById("rolagem-maldicao-def");
    const reRoll = document.getElementById("re-roll");
    const reRollVida = document.getElementById("re-roll-vida");
    const reRollSanidade = document.getElementById("re-roll-sanidade");
    const reRollSorte = document.getElementById("re-roll-sorte");
    const reRollAura = document.getElementById("re-roll-aura");

    //Atributos
    const atributos = document.querySelectorAll(".atributo-valor");
    const forca = document.getElementById("forca");
    const constituicao = document.getElementById("constituicao");
    const destreza = document.getElementById("destreza");
    const conhecimento = document.getElementById("conhecimento");
    const percepcao = document.getElementById("percepcao");
    const carisma = document.getElementById("carisma");
    const alma = document.getElementById("alma");
    const sorte = document.getElementById("sorte");
    const cardConstituicao = document.getElementById("card-constituicao");
    const cardAtributos = document.querySelectorAll(".atributo-card");
    const espalharPontos = document.getElementById("espalhar-pontos");

    //Mods
    const modForca = document.getElementById("mod-forca");
    const modConstituicao = document.getElementById("mod-constituicao");
    const modDestreza = document.getElementById("mod-destreza");
    const modConhecimento = document.getElementById("mod-conhecimento");
    const modPercepcao = document.getElementById("mod-percepcao");
    const modAlma = document.getElementById("mod-alma");
    const be = document.getElementById("be");
    const modCarisma = document.getElementById("mod-carisma");
    const pontosRestantesTexto = document.getElementById("pontos-restantes-texto");

    // ---- PONTOS DE ATRIBUTOS: total fixo e único ponto de verdade ----
    const PONTOS_TOTAL_ATRIBUTOS = 300;
    let pontosRestantes = {{ $pontosRestantesIniciais }};

    // Guarda o último valor válido de cada atributo, usado apenas para
    // reverter o campo quando o usuário tenta gastar mais do que tem.
    let valorForcaTexto = forca.value || "";
    let valorDestrezaTexto = destreza.value || "";
    let valorConstituicaoTexto = constituicao.value || "";
    let valorConhecimentoTexto = conhecimento.value || "";
    let valorPercepcaoTexto = percepcao.value || "";
    let valorAlmaTexto = alma.value || "";
    let valorCarismaTexto = carisma.value || "";

    function recalcularPontosAtributos(){
        const soma =
            Number(forca.value || 0) +
            Number(destreza.value || 0) +
            Number(constituicao.value || 0) +
            Number(conhecimento.value || 0) +
            Number(percepcao.value || 0) +
            Number(alma.value || 0) +
            Number(carisma.value || 0);

        pontosRestantes = PONTOS_TOTAL_ATRIBUTOS - soma;
        pontosRestantesTexto.textContent = "Pontos: " + pontosRestantes;
    }

    //informações técnicas

    const nivelMundano = document.getElementById("nivel-mundano");
    const experienciaMundana = document.getElementById("experiencia-mundana");
    const experienciaTotal = document.getElementById("experiencia-total");

    //Habilidades
    const pontosHabilidadeRestantesTexto = document.getElementById("pontos-habilidade-restantes-texto");
    const habilidades = document.querySelectorAll(".habilidade-valor");
    const modsHabilidade = document.querySelectorAll(".habilidade-mod");

    //Profissões
    const profissao = document.getElementById("profissao");
    const criminoso = document.getElementById("criminoso");
    const lutador = document.getElementById("lutador");
    const comunicador = document.getElementById("comunicador");
    const intelectual = document.getElementById("intelectual");
    const construtor = document.getElementById("construtor");

    //outros
    let rolagensRestantes=1;


    //ocultação

    let atributosOculto = 0;
    botaoOcultarAtributos.addEventListener("click", function(){
        if(atributosOculto === 1){
            ocultarAtributos.classList.remove("oculto");
            atributosOculto = 0;
            tituloOcultarAtributos.classList.remove("titulo-colapsavel");
            return;
        }
        ocultarAtributos.classList.add("oculto");
        tituloOcultarAtributos.classList.add("titulo-colapsavel");
        atributosOculto=1;
    });


    let infoPessoalOculta = 0;
    botaoOcultarInfoPessoais.addEventListener("click", function(){
        if(infoPessoalOculta === 1){
            dadosPessoais.classList.remove("oculto");
            infoPessoalOculta = 0;
            tituloInfoPessoais.classList.remove("titulo-colapsavel");
            return;
        }
        dadosPessoais.classList.add("oculto");
        tituloInfoPessoais.classList.add("titulo-colapsavel");
        infoPessoalOculta=1;
    });

    let infoTecnicaOculta = 0;
    botaoOcultarInfoTecnicas.addEventListener("click", function(){
        if(infoTecnicaOculta === 1){
            infoTecnicas.classList.remove("oculto");
            infoTecnicaOculta = 0;
            tituloInfoTecnicas.classList.remove("titulo-colapsavel");
            return;
        }
        infoTecnicas.classList.add("oculto");
        tituloInfoTecnicas.classList.add("titulo-colapsavel");
        infoTecnicaOculta=1;
    });

    let infoVitaisOcultos = 0;
    botaoVitaisOcultos.addEventListener("click", function(){
        if(infoVitaisOcultos === 1){
            barrasAtributosVitais.classList.remove("oculto");
            infoVitaisOcultos = 0;
            tituloAtributosVitais.classList.remove("titulo-colapsavel");
            return;
        }
        barrasAtributosVitais.classList.add("oculto");
        tituloAtributosVitais.classList.add("titulo-colapsavel");
        infoVitaisOcultos=1;
    });

    let rolagemSetorOculto = 0;
    botaoOcultarRolagemSetor.addEventListener("click", function(){
        if(rolagemSetorOculto === 1){
            rolagemSetorConteudo.classList.remove("oculto");
            rolagemSetorOculto = 0;
            tituloRolagemSetor.classList.remove("titulo-colapsavel");
            acessosDiv.classList.remove("oculto");
            maldicaoDiv.classList.remove("oculto");
            roladorDados.classList.remove("oculto");
            return;
        }
        acessosDiv.classList.add("oculto");
        maldicaoDiv.classList.add("oculto");
        roladorDados.classList.add("oculto");
        rolagemSetorConteudo.classList.add("oculto");
        tituloRolagemSetor.classList.add("titulo-colapsavel");
        rolagemSetorOculto = 1;
    });

    let habilidadesOcultas = 0;
    botaoOcultarHabilidades.addEventListener("click", function(){
        if(habilidadesOcultas === 1){
            habilidadesConteudo.classList.remove("oculto");
            pontosADistribuir.classList.remove("oculto");
            habilidadesOcultas = 0;
            tituloHabilidades.classList.remove("titulo-colapsavel");
            return;
        }
        habilidadesConteudo.classList.add("oculto");
        pontosADistribuir.classList.add("oculto");
        tituloHabilidades.classList.add("titulo-colapsavel");
        habilidadesOcultas = 1;
    });

    let descricaoOculta = 0;
    botaoOcultarDescricao.addEventListener("click", function(){
        if(descricaoOculta === 1){
            descricaoConteudo.classList.remove("oculto");
            descricaoOculta = 0;
            tituloDescricao.classList.remove("titulo-colapsavel");
            return;
        }
        descricaoConteudo.classList.add("oculto");
        tituloDescricao.classList.add("titulo-colapsavel");
        descricaoOculta = 1;
    });

    let habilidadesFisicasOcultas = 0;
    botaoOcultarHabilidadesFisicas.addEventListener("click", function(){
        if(habilidadesFisicasOcultas === 1){
            conteudoHabilidadesFisicas.classList.remove("oculto");
            habilidadesFisicasOcultas = 0;
            tituloHabilidadesFisicas.classList.remove("titulo-colapsavel");
            return;
        }
        conteudoHabilidadesFisicas.classList.add("oculto");
        tituloHabilidadesFisicas.classList.add("titulo-colapsavel");
        habilidadesFisicasOcultas = 1;
    });

    let habilidadesIntelectuaisOcultas = 0;
    botaoOcultarHabilidadesIntelectuais.addEventListener("click", function(){
        if(habilidadesIntelectuaisOcultas === 1){
            conteudoHabilidadesIntelectuais.classList.remove("oculto");
            habilidadesIntelectuaisOcultas = 0;
            tituloHabilidadesIntelectuais.classList.remove("titulo-colapsavel");
            return;
        }
        conteudoHabilidadesIntelectuais.classList.add("oculto");
        tituloHabilidadesIntelectuais.classList.add("titulo-colapsavel");
        habilidadesIntelectuaisOcultas = 1;
    });

    let habilidadesIntrospectivasOcultas = 0;
    botaoOcultarHabilidadesIntrospectivas.addEventListener("click", function(){
        if(habilidadesIntrospectivasOcultas === 1){
            conteudoHabilidadesIntrospectivas.classList.remove("oculto");
            habilidadesIntrospectivasOcultas = 0;
            tituloHabilidadesIntrospectivas.classList.remove("titulo-colapsavel");
            return;
        }
        conteudoHabilidadesIntrospectivas.classList.add("oculto");
        tituloHabilidadesIntrospectivas.classList.add("titulo-colapsavel");
        habilidadesIntrospectivasOcultas = 1;
    });



    //Popup

    const distribuirNiveis = document.getElementById("distribuir-niveis");
    const distribuirNiveisPopup = document.getElementById("distribuir-niveis-popup");
    const fecharDistribuir = document.getElementById("fechar-distribuir");
    const camposDistribuicaoNiveis = document.getElementById("campos-distribuicao-niveis");


    let checkDistribuir = 0;
    camposDistribuicaoNiveis.innerHTML = "";
    distribuirNiveis.addEventListener("click", function(){
        distribuirNiveisPopup.classList.add("ativo");
        let quantidadeNiveis = Number(nivelMundano.value);
        camposDistribuicaoNiveis.innerHTML += "";
        if(checkDistribuir == 0){
            for(let i = 1;i<=quantidadeNiveis;i++){
                checkDistribuir=1;
                camposDistribuicaoNiveis.innerHTML +=`
                <div class="campo">
                    <label>Nível ${i}</label>
                    <select type="text" style="margin-top:5px;" placeholder="Escolha a Profissão do agente no nível ${i}">
                        <option>Criminoso</option>
                        <option>Lutador</option>
                        <option>Intelectual</option>
                        <option>Comunicador</option>
                        <option>Construtor</option>
                    </select>
                `;
            }
        }
    });

    fecharDistribuir.addEventListener("click", function(){
        distribuirNiveisPopup.classList.remove("ativo");
    });

    function mostrarPopup(texto){
        popupMensagem.textContent = texto;
        popup.classList.add("ativo");
    }

    popupFechar.addEventListener("click", ()=>{
        popup.classList.remove("ativo");
    });



    //Popup Maldição
    function mostrarPopupMaldicao(texto){
        mensagemPopupMaldicao.textContent = texto;
        popupMaldicao.classList.add("ativo");
    }

    popupFecharMaldicao.addEventListener("click", function(){
        popupMaldicao.classList.remove("ativo");
        divMaldicao.style.display = "none";
    });



    //Popup Re-roll
    function mostrarPopupReRoll(texto){
        popupReRollMensagem.textContent = texto;
        popupReRoll.classList.add("ativo");
    }

    popupReRollFechar.addEventListener("click", function(){
        popupReRoll.classList.remove("ativo");
    });


    //rolagem de habilidades

    const nomesHabilidades = {
        // Físicas
        "luta": "Luta",
        "resiliencia": "Resiliência",
        "acrobacia": "Acrobacia",
        "atletismo": "Atletismo",
        "agarrar": "Agarrar",
        "arremesso": "Arremesso",
        "desviar": "Desviar",
        "contra-ataque": "Contra ataque",
        "bloquear": "Bloquear",
        "iniciativa": "Iniciativa",
        "furtividade": "Furtividade",
        "enxergar": "Enxergar",
        "olfato": "Olfato",
        "audicao": "Audição",

        // Intelectuais
        "maldicao": "Maldição",
        "precisao": "Precisão",
        "investigacao": "Investigação",
        "sobrevivencia": "Sobrevivência",
        "construcao": "Construção",
        "procura": "Procura",
        "intuicao": "Intuição",
        "tecnologia": "Tecnologia",
        "religiao": "Religião",
        "raciocinio": "Raciocínio",
        "medicina": "Medicina",
        "primeiros-socorros": "Primeiros socorros",
        "culinaria": "Culinária",
        "dirigir": "Dirigir",
        "explosivos": "Explosivos",

        // Introspectivas
        "fotografia": "Fotografia",
        "labia": "Lábia",
        "barganhar": "Barganhar",
        "manipulacao": "Manipulação",
        "intimidar": "Intimidar",
        "arte": "Arte",
        "disfarce": "Disfarce",
        "performance": "Performance",
        "detectar-mentira": "Detectar mentira"
    };

    //rolagens habilidade
    const rolagemHabilidadeCard = document.getElementById("rolagem-habilidade");
    const rolarHabilidadeBotao = document.querySelectorAll(".botao-rolar-habilidade");
    const fecharHabilidade = document.getElementById("fechar-rolar-habilidade");
    const tituloRolagemHabilidade = document.getElementById("titulo-rolagem-habilidade");
    const valorHabilidadePopup = document.getElementById("valor-habilidade-popup");
    const tituloModHabilidade = document.getElementById("mod-habilidade-popup");
    const vantagensExtras = document.getElementById("vantagem-extra-input");
    const aplicacaoFinal = document.getElementById("resultado-reducao");
    const resultadoNatural = document.getElementById("resultado-natural");
    const rolarHabilidade = document.getElementById("rolar-dado-habilidade");
    const resultadoFinal = document.getElementById("resultado-final");

    rolarHabilidadeBotao.forEach(botao =>{
        botao.addEventListener("click", function(){
            let titulo = nomesHabilidades[botao.dataset.habilidade];
            let inputHabilidade = document.getElementById(botao.dataset.habilidade);
            let modHabilidade = vinculoHabilidadeAtributo[botao.dataset.habilidade];
            valorHabilidadePopup.textContent = inputHabilidade.value;
            rolagemHabilidadeCard.classList.add("ativo");
            tituloRolagemHabilidade.textContent = "Rolar: " + titulo;
            tituloModHabilidade.textContent = modHabilidade();
            aplicacaoFinal.textContent = Number(inputHabilidade.value) + Number(modHabilidade()) + Number(vantagensExtras.value);
            vantagensExtras.addEventListener("input", ()=>{
                aplicacaoFinal.textContent = Number(inputHabilidade.value) + Number(modHabilidade()) + Number(vantagensExtras.value);
            });
            rolarHabilidade.addEventListener("click", function(){
                let dadoNatural = rolarDado(100);
                resultadoNatural.textContent = dadoNatural;
                resultadoFinal.textContent = Number(dadoNatural) - Number(modHabilidade()) - Number(vantagensExtras.value) - Number(inputHabilidade.value);
            });
        });
    });

    fecharHabilidade.addEventListener("click", function(){
        rolagemHabilidadeCard.classList.remove("ativo");
    });


    //Rolagem
    function rolarDado(lados){
        return Math.floor(Math.random() * lados) +1;
    }

    d100.addEventListener("click", function(){
        rolagemCard.classList.add("ativo");
    });

    rolagemVida.addEventListener("click", function(){
        if(constituicao.value.trim() === ""){
            mostrarPopup("Defina sua constituição antes de rolar a vida");
            cardConstituicao.classList.remove("erro-atributo");
            void cardConstituicao.offsetWidth;
            cardConstituicao.classList.add("erro-atributo");
            return;
        }
        if(primeiraMudanca === 0){
            mostrarPopup("Você precisa escolher um porte físico antes de rolar seus PVS");
            return;
        }
        let lados=4;
        let resultado = rolarDado(lados);
        resultado += 6;
        resultado += Math.floor(Number((constituicao.value/10)/3));
        resultadoDado.textContent = "Pontos de Vida: " + resultado;
        vida.textContent = resultado + "/" + resultado;
        rolagemVida.classList.add("usado");
    });

    rolagemAura.addEventListener("click", function(){
        let lados=100;
        let resultado = rolarDado(lados);
        while(resultado<40){
            resultado = rolarDado(lados);
        }
        aura.textContent = resultado + "/" + resultado;
        resultadoDado.textContent = "Aura: " + resultado;
        rolagemAura.classList.add("usado");
    });

    rolagemMaldicao.addEventListener("click", function(){
        mostrarPopupMaldicao("Rolagem Maldição");
    });

    rolagemRaridade.addEventListener("click", function(){
        let lados = 100;
        let resultado = rolarDado(lados);
        if(resultado>=60){
            resultadoMaldicao.textContent = "Maldição Comum: " + resultado;
        }
        else if(resultado>=35 && resultado <60){
            resultadoMaldicao.textContent = "Maldição incomum: " + resultado;
        }
        else if(resultado>=20 && resultado <35){
            resultadoMaldicao.textContent = "Maldição rara: " + resultado;
        }
        else if(resultado>=5 && resultado <20){
            resultadoMaldicao.textContent = "Maldição exótica: " + resultado;
        }
        else if(resultado>=1 && resultado <5){
            resultadoMaldicao.textContent = "Maldição impossível: " + resultado;
        }
        divMaldicao.style.display = "block";
        rolagemRaridade.classList.add("usado");
    });

    rolagemMaldicaoDef.addEventListener("click", function(){
        resultadoMaldicao.textContent = "to funcionando";
    });

    rolagemSanidade.addEventListener("click", function(){
        let lados = 100;
        let resultado = rolarDado(lados);
        while(resultado<40 || resultado>75){
            resultado = rolarDado(lados);
        }
        resultadoDado.textContent = "Sanidade: " + resultado;
        sanidade.textContent = resultado + "/" + resultado;
        rolagemSanidade.classList.add("usado");
    });

    rolagemSorte.addEventListener("click", function(){
        let lados = 100;
        let resultado = rolarDado(lados);
        while(resultado<10 || resultado>75){
            resultado = rolarDado(lados);
        }
        resultadoDado.textContent = "Sorte: " + resultado;
        sorte.value = resultado;
        nomeSorte.style="color: var(--tinta-fraca);";
        rolagemSorte.classList.add("usado");
    });
    
    botaoX.addEventListener("click", function(){
        rolagemCard.classList.remove("ativo");
    });

    reRoll.addEventListener("click", function(){
        if(nivelMundano.value>1){
            mostrarPopup("Você não pode re-rolar sua vida se já aumentou seu Nível mundano");
            return;
        }
        mostrarPopupReRoll("Escolha qual parâmetro da ficha você quer re-rolar");
    });

    reRollVida.addEventListener("click", function(){
        if(!rolagemVida.classList.contains("usado")){
            mostrarPopup("Você precisa rolar sua vida antes de re-rolar");
            popupReRoll.classList.remove("ativo");
            return;
        }
        rolagemVida.classList.remove("usado");
        reRollVida.classList.add("usado");
        reRollSanidade.classList.add("usado");
        reRollSorte.classList.add("usado");
        reRollAura.classList.add("usado");
        rolagensRestantes=0;
    });

    reRollSanidade.addEventListener("click", function(){
        if(!rolagemSanidade.classList.contains("usado")){
            mostrarPopup("Você precisa rolar sua sanidade antes de re-rolar");
            popupReRoll.classList.remove("ativo");
            return;
        }
        rolagemSanidade.classList.remove("usado");
        reRollSanidade.classList.add("usado");
        reRollVida.classList.add("usado");
        reRollSorte.classList.add("usado");
        reRollAura.classList.add("usado");
        rolagensRestantes=0;
    });

    reRollSorte.addEventListener("click", function(){
        if(!rolagemSorte.classList.contains("usado")){
            mostrarPopup("Você precisa rolar sua sorte antes de re-rolar");
            popupReRoll.classList.remove("ativo");
            return;
        }
        reRollSorte.classList.add("usado");
        reRollSanidade.classList.add("usado");
        reRollVida.classList.add("usado");
        rolagemSorte.classList.remove("usado");
        rolagensRestantes=0;
    });

    reRollAura.addEventListener("click", function(){
        if(!rolagemAura.classList.contains("usado")){
            mostrarPopup("Você precisa rolar sua aura antes de re-rolar");
            popupReRoll.classList.remove("ativo");
            return;
        }
        reRollAura.classList.add("usado");
        reRollSorte.classList.add("usado");
        reRollSanidade.classList.add("usado");
        reRollVida.classList.add("usado");
        rolagemAura.classList.remove("usado");
        rolagensRestantes=0;
    });




    //Atributos

    // CORREÇÃO: antes, "Espalhar Pontos" só descontava os 210 pontos gastos
    // (30 x 7 atributos) quando pontosRestantes estivesse EXATAMENTE em 300.
    // Se o usuário já tivesse mexido em qualquer atributo antes de clicar,
    // essa condição falhava e o saldo ficava incorreto dali pra frente.
    // Agora o saldo é sempre recalculado a partir dos valores reais dos campos.
    espalharPontos.addEventListener("click", function(){
        constituicao.value=30;
        modConstituicao.value=Math.floor(30/10);
        destreza.value=30;
        modDestreza.value=Math.floor(30/10);
        forca.value=30;
        modForca.value=Math.floor(30/10);
        conhecimento.value=30;
        modConhecimento.value=Math.floor(30/10);
        be.value=modConhecimento.value;
        carisma.value=30;
        modCarisma.value=Math.floor(30/10);
        alma.value=30;
        modAlma.value=Math.floor(30/10);
        percepcao.value=30;
        modPercepcao.value=Math.floor(30/10);

        valorConstituicaoTexto = "30";
        valorDestrezaTexto = "30";
        valorForcaTexto = "30";
        valorConhecimentoTexto = "30";
        valorCarismaTexto = "30";
        valorAlmaTexto = "30";
        valorPercepcaoTexto = "30";

        recalcularPontosAtributos();

        pontosHabilidadeGastos = 0;
        habilidades.forEach(input => {
            input.value = "";
            input.dataset.anterior = "0";
        });

        primeiraMudancaProfissao = 0;

        pontosHabilidadeRestantes = (Number(nivelMundano.value || 1) * Number(be.value || 0)) + 15;
        pontosHabilidadeRestantesTexto.textContent = "Pontos a distribuir: " + pontosHabilidadeRestantes;

        atualizarHabilidadesMod();
    });

    constituicao.addEventListener("input", ()=>{
        if(constituicao.value.trim() !== ""){
            cardConstituicao.classList.remove("erro-atributo");
        }
    });

    atributos.forEach(input => {
        input.addEventListener("input", function(){
                this.value = this.value.replace(/\D/g, "");

                this.value = this.value.slice(0, 2);
        });
    });

    function atualizarHabilidadesMod() {

        habilidades.forEach(habilidade => {

            const idHabilidade = habilidade.id;

            const funcaoModificador =
                vinculoHabilidadeAtributo[idHabilidade];

            if (funcaoModificador) {

                const valorModificador =
                    funcaoModificador();

                const linha =
                    habilidade.closest(".habilidade-linha");

                const inputModificador =
                    linha.querySelector(".habilidade-mod");

                inputModificador.value =
                    valorModificador >= 0
                        ? "+" + valorModificador
                        : valorModificador;
            }
        });
    }

    // CORREÇÃO GERAL DOS 7 LISTENERS ABAIXO:
    // Antes, cada atributo tinha sua própria variável "valorXAnterior" que
    // precisava ser devolvida e re-debitada manualmente a cada input, e
    // recalculada à mão em outros pontos do código (espalhar pontos, troca
    // de porte físico, preencher-teste). Bastava um desses lugares esquecer
    // de atualizar a variável certa para o saldo de pontos entrar em
    // dessincronia com a soma real dos atributos - foi aí que o usuário
    // começou a "perder" pontos sem motivo aparente.
    // Agora cada listener só valida o novo valor e chama
    // recalcularPontosAtributos(), que sempre recalcula o saldo do zero
    // a partir da soma atual dos 7 campos. Não existe mais estado
    // duplicado para dessincronizar.

    forca.addEventListener("input", () => {
        const somaOutros =
            Number(destreza.value || 0) + Number(constituicao.value || 0) +
            Number(conhecimento.value || 0) + Number(percepcao.value || 0) +
            Number(alma.value || 0) + Number(carisma.value || 0);

        if(forca.value === ""){
            modForca.value = "";
        } else {
            const valorAtual = Number(forca.value);

            if((somaOutros + valorAtual) > PONTOS_TOTAL_ATRIBUTOS){
                mostrarPopup("Pontos insuficientes! Você tem apenas " + (PONTOS_TOTAL_ATRIBUTOS - somaOutros) + " pontos disponíveis.");
                forca.value = valorForcaTexto;
                modForca.value = forca.value === "" ? "" : Math.floor(Number(forca.value)/10);
                recalcularPontosAtributos();
                atualizarHabilidadesMod();
                return;
            }

            valorForcaTexto = forca.value;
        }

        modForca.value = forca.value === "" ? "" : Math.floor(Number(forca.value)/10);
        recalcularPontosAtributos();
        atualizarHabilidadesMod();
    });

    destreza.addEventListener("input", ()=>{
        const somaOutros =
            Number(forca.value || 0) + Number(constituicao.value || 0) +
            Number(conhecimento.value || 0) + Number(percepcao.value || 0) +
            Number(alma.value || 0) + Number(carisma.value || 0);

        if(destreza.value === ""){
            modDestreza.value = "";
        } else {
            const valorAtual = Number(destreza.value);

            if((somaOutros + valorAtual) > PONTOS_TOTAL_ATRIBUTOS){
                mostrarPopup("Pontos insuficientes! Você tem apenas " + (PONTOS_TOTAL_ATRIBUTOS - somaOutros) + " pontos disponíveis.");
                destreza.value = valorDestrezaTexto;
                modDestreza.value = destreza.value === "" ? "" : Math.floor(Number(destreza.value)/10);
                recalcularPontosAtributos();
                atualizarHabilidadesMod();
                return;
            }

            valorDestrezaTexto = destreza.value;
        }

        modDestreza.value = destreza.value === "" ? "" : Math.floor(Number(destreza.value)/10);
        recalcularPontosAtributos();
        atualizarHabilidadesMod();
    });

    constituicao.addEventListener("input", ()=>{
        const somaOutros =
            Number(forca.value || 0) + Number(destreza.value || 0) +
            Number(conhecimento.value || 0) + Number(percepcao.value || 0) +
            Number(alma.value || 0) + Number(carisma.value || 0);

        if(constituicao.value === ""){
            modConstituicao.value = "";
        } else {
            const valorAtual = Number(constituicao.value);

            if((somaOutros + valorAtual) > PONTOS_TOTAL_ATRIBUTOS){
                mostrarPopup("Pontos insuficientes! Você tem apenas " + (PONTOS_TOTAL_ATRIBUTOS - somaOutros) + " pontos disponíveis.");
                constituicao.value = valorConstituicaoTexto;
                modConstituicao.value = constituicao.value === "" ? "" : Math.floor(Number(constituicao.value)/10);
                recalcularPontosAtributos();
                atualizarHabilidadesMod();
                return;
            }

            valorConstituicaoTexto = constituicao.value;
        }

        modConstituicao.value = constituicao.value === "" ? "" : Math.floor(Number(constituicao.value)/10);
        recalcularPontosAtributos();
        atualizarHabilidadesMod();
    });

    conhecimento.addEventListener("input", ()=>{
        const somaOutros =
            Number(forca.value || 0) + Number(destreza.value || 0) +
            Number(constituicao.value || 0) + Number(percepcao.value || 0) +
            Number(alma.value || 0) + Number(carisma.value || 0);

        if(conhecimento.value === ""){
            modConhecimento.value = "";
        } else {
            const valorAtual = Number(conhecimento.value);

            if((somaOutros + valorAtual) > PONTOS_TOTAL_ATRIBUTOS){
                mostrarPopup("Pontos insuficientes! Você tem apenas " + (PONTOS_TOTAL_ATRIBUTOS - somaOutros) + " pontos disponíveis.");
                conhecimento.value = valorConhecimentoTexto;
                modConhecimento.value = conhecimento.value === "" ? "" : Math.floor(Number(conhecimento.value)/10);
                be.value = modConhecimento.value;
                recalcularPontosAtributos();
                atualizarHabilidadesMod();
                return;
            }

            valorConhecimentoTexto = conhecimento.value;
        }

        modConhecimento.value = conhecimento.value === "" ? "" : Math.floor(Number(conhecimento.value)/10);
        be.value = modConhecimento.value;
        pontosHabilidadeRestantes = (nivelMundano.value*be.value)+15;
        pontosHabilidadeRestantesTexto.textContent = "Pontos restantes: " + pontosHabilidadeRestantes;
        recalcularPontosAtributos();
        atualizarHabilidadesMod();
    });

    percepcao.addEventListener("input", ()=>{
        const somaOutros =
            Number(forca.value || 0) + Number(destreza.value || 0) +
            Number(constituicao.value || 0) + Number(conhecimento.value || 0) +
            Number(alma.value || 0) + Number(carisma.value || 0);

        if(percepcao.value === ""){
            modPercepcao.value = "";
        } else {
            const valorAtual = Number(percepcao.value);

            if((somaOutros + valorAtual) > PONTOS_TOTAL_ATRIBUTOS){
                mostrarPopup("Pontos insuficientes! Você tem apenas " + (PONTOS_TOTAL_ATRIBUTOS - somaOutros) + " pontos disponíveis.");
                percepcao.value = valorPercepcaoTexto;
                modPercepcao.value = percepcao.value === "" ? "" : Math.floor(Number(percepcao.value)/10);
                recalcularPontosAtributos();
                atualizarHabilidadesMod();
                return;
            }

            valorPercepcaoTexto = percepcao.value;
        }

        modPercepcao.value = percepcao.value === "" ? "" : Math.floor(Number(percepcao.value)/10);
        recalcularPontosAtributos();
        atualizarHabilidadesMod();
    });

    alma.addEventListener("input", ()=>{
        const somaOutros =
            Number(forca.value || 0) + Number(destreza.value || 0) +
            Number(constituicao.value || 0) + Number(conhecimento.value || 0) +
            Number(percepcao.value || 0) + Number(carisma.value || 0);

        if(alma.value === ""){
            modAlma.value = "";
        } else {
            const valorAtual = Number(alma.value);

            if((somaOutros + valorAtual) > PONTOS_TOTAL_ATRIBUTOS){
                mostrarPopup("Pontos insuficientes! Você tem apenas " + (PONTOS_TOTAL_ATRIBUTOS - somaOutros) + " pontos disponíveis.");
                alma.value = valorAlmaTexto;
                modAlma.value = alma.value === "" ? "" : Math.floor(Number(alma.value)/10);
                recalcularPontosAtributos();
                atualizarHabilidadesMod();
                return;
            }

            valorAlmaTexto = alma.value;
        }

        modAlma.value = alma.value === "" ? "" : Math.floor(Number(alma.value)/10);
        recalcularPontosAtributos();
        atualizarHabilidadesMod();
    });

    carisma.addEventListener("input", ()=>{
        const somaOutros =
            Number(forca.value || 0) + Number(destreza.value || 0) +
            Number(constituicao.value || 0) + Number(conhecimento.value || 0) +
            Number(percepcao.value || 0) + Number(alma.value || 0);

        if(carisma.value === ""){
            modCarisma.value = "";
        } else {
            const valorAtual = Number(carisma.value);

            if((somaOutros + valorAtual) > PONTOS_TOTAL_ATRIBUTOS){
                mostrarPopup("Pontos insuficientes! Você tem apenas " + (PONTOS_TOTAL_ATRIBUTOS - somaOutros) + " pontos disponíveis.");
                carisma.value = valorCarismaTexto;
                modCarisma.value = carisma.value === "" ? "" : Math.floor(Number(carisma.value)/10);
                recalcularPontosAtributos();
                atualizarHabilidadesMod();
                return;
            }

            valorCarismaTexto = carisma.value;
        }

        modCarisma.value = carisma.value === "" ? "" : Math.floor(Number(carisma.value)/10);
        recalcularPontosAtributos();
        atualizarHabilidadesMod();
    });


    let primeiraMudanca=0;
    porteFisico.addEventListener("change", function(){
        aumentoBe=0;
        primeiraMudancaProfissao = 0;
        nivelMundano.value=1;
        nivelAnterior = 1;
        historicoGanhosVida = {};
        historicoGanhosAura = {};
        historicoGanhosSanidade = {};
        historicoXpGanho = {};
        xpMaximo = 20;
        xpAtual = 0;
        experienciaMundana.value = "";
        atualizarExperienciaTexto();

        habilidades.forEach(input => {
            input.value = "";
            input.dataset.anterior = "0";
        });

        pontosHabilidadeGastos = 0;
        if(constituicao.value.trim() === ""){

            mostrarPopup("Defina seus atributos para escolher seu porte físico");

            cardAtributos.forEach(card => {
                card.classList.remove("erro-atributo");
                void card.offsetWidth;
                card.classList.add("erro-atributo");
            });

            return;
        }

        cardAtributos.forEach(card => {
            card.classList.remove("erro-atributo");
        });

        if(primeiraMudanca === 1){
            mostrarPopup("Para não acumular bônus e desvantagens, vamos redefinir sua distribuicao de atributos.");
            constituicao.value=30;
            modConstituicao.value=3;
            destreza.value=30;
            modDestreza.value=3;
            forca.value=30;
            modForca.value=3;
            conhecimento.value=30;
            modConhecimento.value=3;
            be.value=3;
            carisma.value=30;
            modCarisma.value=3;
            alma.value=30;
            modAlma.value=3;
            percepcao.value=30;
            modPercepcao.value=3;

            valorConstituicaoTexto = "30";
            valorDestrezaTexto = "30";
            valorForcaTexto = "30";
            valorConhecimentoTexto = "30";
            valorCarismaTexto = "30";
            valorAlmaTexto = "30";
            valorPercepcaoTexto = "30";

            recalcularPontosAtributos();
        }


        if(porteFisico.value === "Magro"){

            constituicao.value=constituicao.value-10;
            destreza.value = Number(destreza.value)+20;
            forca.value=forca.value-10;
            modForca.value = Math.floor(forca.value/10);
            modDestreza.value = Math.floor(destreza.value/10);
            modConstituicao.value = Math.floor(constituicao.value/10);
            if(forca.value<10){
                forca.value=10;
            }
            if(constituicao.value<10){
                constituicao.value=10;
            }
            if(destreza.value>75){
                destreza.value=75;
            }

            valorForcaTexto = forca.value;
            valorDestrezaTexto = destreza.value;
            valorConstituicaoTexto = constituicao.value;
            primeiraMudanca=1;
            rolagemVida.classList.remove("usado");
            rolagemSanidade.classList.remove("usado");
            rolagemAura.classList.remove("usado");
            resultado.textContent = "?";
            vida.textContent = "? / ?";
            aura.textContent = "? / ?";
            sanidade.textContent = "? / ?";
            be.value=modConhecimento.value;
            experienciaMundana.removeAttribute("readonly", false);
        }

        if(porteFisico.value === "Musculoso"){
            destreza.value=destreza.value-10;
            forca.value = Number(forca.value)+10;
            constituicao.value = Number(constituicao.value)+10;
            modForca.value = Math.floor(forca.value/10);
            modDestreza.value = Math.floor(destreza.value/10);
            modConstituicao.value = Math.floor(constituicao.value/10);
            if(forca.value>75){
                forca.value=75;
            }
            if(constituicao.value>75){
                constituicao.value=75;
            }
            if(destreza.value<10){
                destreza.value=10;
            }

            valorForcaTexto = forca.value;
            valorDestrezaTexto = destreza.value;
            valorConstituicaoTexto = constituicao.value;

            rolagemVida.classList.remove("usado");
            rolagemSanidade.classList.remove("usado");
            rolagemAura.classList.remove("usado");
            vida.textContent = "? / ?";
            aura.textContent = "? / ?";
            sanidade.textContent = "? / ?";
            resultado.textContent = "?";
            be.value=modConhecimento.value;
            experienciaMundana.removeAttribute("readonly", false);
            primeiraMudanca=1;
        }

        if(porteFisico.value === "Gordo"){
            forca.value = Number(forca.value) + 10;
            constituicao.value = Number(constituicao.value) + 10;
            destreza.value = Number(destreza.value) - 20;

            if(forca.value > 75) forca.value = 75;
            if(constituicao.value > 75) constituicao.value = 75;
            if(destreza.value < 10) destreza.value = 10;

            modForca.value = Math.floor(forca.value/10);
            modConstituicao.value = Math.floor(constituicao.value/10);
            modDestreza.value = Math.floor(destreza.value/10);

            valorForcaTexto = forca.value;
            valorConstituicaoTexto = constituicao.value;
            valorDestrezaTexto = destreza.value;

            rolagemVida.classList.remove("usado");
            rolagemSanidade.classList.remove("usado");
            rolagemAura.classList.remove("usado");
            vida.textContent = "? / ?";
            aura.textContent = "? / ?";
            sanidade.textContent = "? / ?";
            resultado.textContent = "?";
            be.value=modConhecimento.value;
            experienciaMundana.removeAttribute("readonly", false);
            primeiraMudanca = 1;
        }

        if(porteFisico.value === "Infantil"){
            destreza.value = Number(destreza.value) + 20;
            forca.value = Number(forca.value) - 10;
            constituicao.value = Number(constituicao.value) - 10;
            be.value=be.value*2;

            if(destreza.value > 75) destreza.value = 75;
            if(forca.value < 10) forca.value = 10;
            if(constituicao.value < 10) constituicao.value = 10;

            modDestreza.value = Math.floor(destreza.value/10);
            modForca.value = Math.floor(forca.value/10);
            modConstituicao.value = Math.floor(constituicao.value/10);

            valorDestrezaTexto = destreza.value;
            valorForcaTexto = forca.value;
            valorConstituicaoTexto = constituicao.value;

            rolagemVida.classList.remove("usado");
            rolagemSanidade.classList.remove("usado");
            rolagemAura.classList.remove("usado");
            vida.textContent = "? / ?";
            aura.textContent = "? / ?";
            sanidade.textContent = "? / ?";
            resultado.textContent = "?";
            experienciaMundana.removeAttribute("readonly", false);
            primeiraMudanca = 1;
        }

        if(porteFisico.value === "Idoso"){
            forca.value = Number(forca.value) - 10;
            destreza.value = Number(destreza.value) - 10;
            constituicao.value = Number(constituicao.value) - 10;
            conhecimento.value = Number(conhecimento.value) + 30;

            if(forca.value < 10) forca.value = 10;
            if(destreza.value < 10) destreza.value = 10;
            if(constituicao.value < 10) constituicao.value = 10;
            if(conhecimento.value > 75) conhecimento.value = 75;

            modForca.value = Math.floor(forca.value/10);
            modDestreza.value = Math.floor(destreza.value/10);
            modConstituicao.value = Math.floor(constituicao.value/10);
            modConhecimento.value = Math.floor(conhecimento.value/10);
            be.value = modConhecimento.value;

            valorForcaTexto = forca.value;
            valorDestrezaTexto = destreza.value;
            valorConstituicaoTexto = constituicao.value;
            valorConhecimentoTexto = conhecimento.value;

            rolagemVida.classList.remove("usado");
            rolagemSanidade.classList.remove("usado");
            rolagemAura.classList.remove("usado");
            vida.textContent = "? / ?";
            aura.textContent = "? / ?";
            sanidade.textContent = "? / ?";
            resultado.textContent = "?";
            be.value=modConhecimento.value;
            experienciaMundana.removeAttribute("readonly", false);
            primeiraMudanca = 1;
        }

        if(porteFisico.value === "Comum"){
            primeiraMudanca=1;
            rolagemVida.classList.remove("usado");
            rolagemSanidade.classList.remove("usado");
            rolagemAura.classList.remove("usado");
            resultado.textContent = "?";
            vida.textContent = "? / ?";
            aura.textContent = "? / ?";
            sanidade.textContent = "? / ?";
            be.value=modConhecimento.value;
            experienciaMundana.removeAttribute("readonly", false);
        }

        // Todo ajuste de atributo feito acima (Magro, Musculoso, Gordo,
        // Infantil, Idoso, Comum) altera os campos diretamente sem passar
        // pelos listeners de "input". Antes disso deixava pontosRestantes
        // desatualizado até o próximo clique manual em algum atributo.
        // Agora recalculamos aqui, uma única vez, no fim do bloco.
        recalcularPontosAtributos();

        profissao.value = "Selecione";
        checkDistribuir = 0;
        camposDistribuicaoNiveis.innerHTML = "";
        pontosHabilidadeRestantes = (1 * Number(be.value || 0)) + 15;
        pontosHabilidadeRestantesTexto.textContent = "Pontos a distribuir: " + pontosHabilidadeRestantes;
        atualizarHabilidadesMod();
    });

    let pontosHabilidadeGastos = 0;
    let nivelAnterior = 1;
    let historicoGanhosVida = {};
    let historicoGanhosAura = {};
    let historicoGanhosSanidade = {};
    let historicoXpGanho = {};
    let xpAtual = 0;
    let xpMaximo = 20;

    function atualizarExperienciaTexto(){
        experienciaTotal.textContent = xpAtual + " / " + xpMaximo;
    }

    function rolarGanhoVida(){
        let ganho = 0;
        const mod = Number(modConstituicao.value);

        if(mod == 2 || mod == 3){
            ganho = 1;
        } else if(mod == 4 || mod == 5){
            ganho = rolarDado(2);
        } else if(mod == 6){
            ganho = rolarDado(3);
        } else if(mod == 7){
            ganho = rolarDado(4);
        } else if(mod == 8){
            ganho = rolarDado(4) + 1;
        } else if(mod == 9){
            ganho = rolarDado(4) + 2;
        } else if(mod > 9){
            let base = mod - 9;
            ganho = rolarDado(4+base) + base;
        }

        return ganho;
    }

    function rolarGanhoAura(){
        let ganho = 0;
        const mod = Number(modAlma.value);

        if(mod == 1){
            ganho = rolarDado(4);
        } else if(mod == 2){
            ganho = rolarDado(8);
        } else if(mod == 3){
            ganho = rolarDado(10);
        } else if(mod == 4){
            ganho = rolarDado(12);
        } else if(mod == 5){
            ganho = rolarDado(16);
        } else if(mod == 6){
            ganho = rolarDado(20);
        } else if(mod == 7){
            ganho = rolarDado(20) + 10;
        } else if(mod == 8){
            ganho = rolarDado(20) + 15;
        } else if(mod == 9){
            ganho = rolarDado(20) + rolarDado(20) + 15;
        } else if(mod > 9){
            let extra = mod - 9;
            let numDados = 2 + Math.floor(extra/2);
            let bonus = 15 + (extra*5);
            let total = 0;
            for(let i=0; i<numDados; i++){
                total += rolarDado(20);
            }
            ganho = total + bonus;
        }

        return ganho;
    }

    function rolarGanhoSanidade(){
        const mod = Number(modConhecimento.value);
        return rolarDado(4) + Math.ceil(mod/2);
    }

    nivelMundano.addEventListener("input", () => {

        checkDistribuir = 0;
        camposDistribuicaoNiveis.innerHTML = "";

        let novoNivel = Number(nivelMundano.value);

        if (isNaN(novoNivel) || novoNivel < 1) {
            novoNivel = 1;
            nivelMundano.value = 1;
        }

        let valorBE = Number(be.value || 0);

        let bonusInicial = 15;

        if (profissao.value != "Selecione") {
            bonusInicial = 30;
        }

        let totalNovo =
            (novoNivel * valorBE) + bonusInicial;

        if (novoNivel > nivelAnterior) {

            let faltando = [];

            if (!rolagemVida.classList.contains("usado")) {
                faltando.push("Vida");
            }

            if (!rolagemSanidade.classList.contains("usado")) {
                faltando.push("Sanidade");
            }

            if (!rolagemAura.classList.contains("usado")) {
                faltando.push("Aura");
            }

            if (faltando.length > 0) {

                mostrarPopup(
                    "Você precisa rolar " +
                    faltando.join(", ") +
                    " antes de aumentar o Nível Mundano."
                );

                nivelMundano.value = nivelAnterior;

                return;
            }
        }

        if (
            novoNivel < nivelAnterior &&
            totalNovo < pontosHabilidadeGastos
        ) {

            mostrarPopup(
                "Você precisa reduzir os pontos investidos em habilidades antes de diminuir o Nível Mundano. " +
                "Pontos já gastos: " +
                pontosHabilidadeGastos +
                ", disponíveis no novo nível: " +
                totalNovo
            );

            nivelMundano.value = nivelAnterior;

            return;
        }

        pontosHabilidadeRestantes =
            totalNovo - pontosHabilidadeGastos;

        pontosHabilidadeRestantesTexto.textContent =
            "Pontos a distribuir: " +
            pontosHabilidadeRestantes;

        let partesVida = vida.textContent
            .split("/")
            .map(v => parseInt(v.trim()));

        let atualVida =
            isNaN(partesVida[0])
                ? 0
                : partesVida[0];

        let maximoVida =
            isNaN(partesVida[1])
                ? 0
                : partesVida[1];

        let partesAura = aura.textContent
            .split("/")
            .map(v => parseInt(v.trim()));

        let atualAura =
            isNaN(partesAura[0])
                ? 0
                : partesAura[0];

        let maximoAura =
            isNaN(partesAura[1])
                ? 0
                : partesAura[1];

        let partesSanidade = sanidade.textContent
            .split("/")
            .map(v => parseInt(v.trim()));

        let atualSanidade =
            isNaN(partesSanidade[0])
                ? 0
                : partesSanidade[0];

        let maximoSanidade =
            isNaN(partesSanidade[1])
                ? 0
                : partesSanidade[1];

        if (novoNivel > nivelAnterior) {

            for (
                let lvl = nivelAnterior + 1;
                lvl <= novoNivel;
                lvl++
            ) {

                let ganhoVida = rolarGanhoVida();

                historicoGanhosVida[lvl] = ganhoVida;

                atualVida += ganhoVida;
                maximoVida += ganhoVida;

                let ganhoAura = rolarGanhoAura();

                historicoGanhosAura[lvl] = ganhoAura;

                atualAura += ganhoAura;
                maximoAura += ganhoAura;

                let ganhoSanidade =
                    rolarGanhoSanidade();

                historicoGanhosSanidade[lvl] =
                    ganhoSanidade;

                atualSanidade += ganhoSanidade;
                maximoSanidade += ganhoSanidade;

                historicoXpGanho[lvl] = 30;

                xpMaximo += 30;
            }

            xpAtual = 0;

        } else if (novoNivel < nivelAnterior) {

            for (
                let lvl = nivelAnterior;
                lvl > novoNivel;
                lvl--
            ) {

                let ganhoVidaAnterior =
                    historicoGanhosVida[lvl] || 0;

                atualVida -= ganhoVidaAnterior;
                maximoVida -= ganhoVidaAnterior;

                delete historicoGanhosVida[lvl];

                let ganhoAuraAnterior =
                    historicoGanhosAura[lvl] || 0;

                atualAura -= ganhoAuraAnterior;
                maximoAura -= ganhoAuraAnterior;

                delete historicoGanhosAura[lvl];

                let ganhoSanidadeAnterior =
                    historicoGanhosSanidade[lvl] || 0;

                atualSanidade -= ganhoSanidadeAnterior;
                maximoSanidade -= ganhoSanidadeAnterior;

                delete historicoGanhosSanidade[lvl];

                let xpRemovido =
                    historicoXpGanho[lvl] || 0;

                xpMaximo -= xpRemovido;

                delete historicoXpGanho[lvl];
            }

            xpAtual = 0;
        }

        vida.textContent =
            atualVida +
            " / " +
            maximoVida;

        aura.textContent =
            atualAura +
            " / " +
            maximoAura;

        sanidade.textContent =
            atualSanidade +
            " / " +
            maximoSanidade;

        nivelAnterior = novoNivel;

        experienciaMundana.value = "";

        atualizarExperienciaTexto();

        experienciaMundana.removeAttribute("readonly");

    });


    //Habilidades
    nivelMundano.value = "{{ $personagem->nivel_mundano ?? 1 }}";
    nivelAnterior = Number(nivelMundano.value);
    xpMaximo = 20 + ((nivelAnterior - 1) * 30);
    xpAtual = Number("{{ $personagem->experiencia_mundana ?? 0 }}");
    let pontosHabilidadeGastosSalvos = @json(array_sum(array_map('intval', $habilidadesSalvas)));
    // CORREÇÃO: o bônus inicial de pontos de habilidade é 30 quando o agente já
    // tem uma profissão escolhida (15 base + 15 da profissão) e 15 quando não tem,
    // exatamente como no listener de "nivelMundano". Antes essa conta sempre
    // somava só 15, então ao abrir uma ficha salva com profissão definida os
    // pontos apareciam 15 a menos (podendo até ficar negativos, ex: -15).
    let bonusInicialSalvo = (profissao.value !== "Selecione") ? 30 : 15;
    let pontosHabilidadeRestantes = (Number(nivelMundano.value) * Number(be.value || 0)) + bonusInicialSalvo - pontosHabilidadeGastosSalvos;
    pontosHabilidadeGastos = pontosHabilidadeGastosSalvos;
    pontosHabilidadeRestantesTexto.textContent = "Pontos a distribuir: " + pontosHabilidadeRestantes;
    experienciaMundana.value = xpAtual || "";
    atualizarExperienciaTexto();

    const vinculoHabilidadeAtributo = {
        "luta": () => Math.max(Number(modForca.value), Number(modDestreza.value)),
        "acrobacia": () => Number(modDestreza.value),
        "atletismo": () => Number(modConstituicao.value),
        "resiliencia": () => Number(modConstituicao.value),
        "agarrar": () => Number(modForca.value),
        "arremesso": () => Number(modForca.value),
        "desviar": () => Number(modDestreza.value),
        "contra-ataque": () => Math.max(Number(modForca.value), Number(modDestreza.value)),
        "bloquear": () => Math.max(Number(modForca.value), Number(modDestreza.value)),
        "iniciativa": () => Number(modDestreza.value),
        "furtividade": () => Math.max(Number(modDestreza.value), Number(modConhecimento.value)),
        "enxergar": () => Number(modPercepcao.value),
        "olfato": () => Number(modPercepcao.value),
        "audicao": () => Number(modPercepcao.value),

        "precisao": () => Math.max(Number(modDestreza.value), Number(modPercepcao.value)),
        "investigacao": () => Math.max(Number(modConhecimento.value), Number(modPercepcao.value)),
        "sobrevivencia": () => Number(modConhecimento.value),
        "construcao": () => Number(modConhecimento.value),
        "procura": () => Number(modPercepcao.value),
        "intuicao": () => Number(modConhecimento.value),
        "tecnologia": () => Number(modConhecimento.value),
        "religiao": () => Number(modConhecimento.value),
        "raciocinio": () => Number(modConhecimento.value),
        "medicina": () => Number(modConhecimento.value),
        "primeiros-socorros": () => Number(modDestreza.value),
        "culinaria": () => Number(modConhecimento.value),
        "dirigir": () => Number(modConhecimento.value),

        "fotografia": () => Number(modCarisma.value),
        "labia": () => Number(modCarisma.value),
        "barganhar": () => Number(modCarisma.value),
        "manipulacao": () => Number(modCarisma.value),
        "intimidar": () => Number(modCarisma.value),
        "arte": () => Number(modCarisma.value),
        "disfarce": () => Number(modCarisma.value),
        "performance": () => Number(modCarisma.value),
        "detectar-mentira": () => Math.max(Number(modCarisma.value), Number(modConhecimento.value))
    };

    habilidades.forEach(input => {
        input.dataset.anterior = "0";
        input.addEventListener("input", function(){
            this.value = this.value.replace(/\D/g, "").slice(0, 2);
            let anterior = Number(this.dataset.anterior);
            let novoValor = 0;
            pontosHabilidadeRestantes += anterior;
            pontosHabilidadeGastos -= anterior;
            if(this.value === ""){
                this.dataset.anterior = "0";
            }else {
                novoValor = Number(this.value);
            }
            if(novoValor > pontosHabilidadeRestantes){
                mostrarPopup("Pontos insuficientes!");
                this.value = this.dataset.anterior === "0" ? "" : this.dataset.anterior;
                pontosHabilidadeRestantes -= anterior;
                pontosHabilidadeGastos += anterior;
                pontosHabilidadeRestantesTexto.textContent = "Pontos a distribuir: " + pontosHabilidadeRestantes;
                return;
            }

            pontosHabilidadeRestantes -= novoValor;
            pontosHabilidadeGastos += novoValor;
            this.dataset.anterior = this.value;
            pontosHabilidadeRestantesTexto.textContent = "Pontos a distribuir: " + pontosHabilidadeRestantes;
        });
    });

    experienciaMundana.addEventListener("input", function(){
        let valor = Number(this.value);
        if(isNaN(valor)){
            return;
        }

        xpAtual = valor;

        if(xpAtual >= xpMaximo){
            nivelMundano.value = Number(nivelMundano.value) + 1;
            nivelMundano.dispatchEvent(new Event("input"));
            experienciaMundana.setAttribute("readonly", true);
        }

        if(xpAtual<xpMaximo){
            experienciaMundana.removeAttribute("readonly");
        }

        atualizarExperienciaTexto();
        this.value = xpAtual;
    });

    //Profissões

    // Se a ficha já foi salva com uma profissão escolhida, o bônus dela já
    // entrou na conta de pontosHabilidadeRestantes acima. Sem isso, a primeira
    // vez que o campo de profissão disparasse "change" (mesmo sem o usuário
    // trocar de fato o valor) somaria +15 de novo, duplicando o bônus.
    let primeiraMudancaProfissao = (profissao.value !== "Selecione") ? 1 : 0;

    profissao.addEventListener("change", function(){
        if(primeiraMudancaProfissao == 1){
            pontosHabilidadeRestantes -= 15;
        }
        pontosHabilidadeRestantes += 15;
        pontosHabilidadeRestantesTexto.textContent = "Pontos a distribuir: " + pontosHabilidadeRestantes;
        primeiraMudancaProfissao = 1;
    });

    //Talentos Mundanos
    const talentosMundanosCard = document.getElementById("talentos-mundanos-card");
    const talentosMundanos = document.getElementById("talentos-mundanos");
    const fecharTalentos = document.getElementById("fechar-talentos");
    const adicionarTalentos = document.getElementById("adicionar-talento");
    const listaTalentos = document.getElementById("lista-talentos");

    talentosMundanos.addEventListener("click", function(){
        talentosMundanosCard.classList.add("ativo");
    });

    fecharTalentos.addEventListener("click", function(){
        talentosMundanosCard.classList.remove("ativo");
    });

    adicionarTalentos.addEventListener("click", function(){
        listaTalentos.innerHTML+=`
            <div class="talento-card">
                <button class="botao-fechar" style="max-width:20%">X</button>
                <h4 class="talento-titulo-exibido" style="display:none;"></h4>
                <input class="talento-titulo" placeholder="Nome do talento">
                <textarea class="talento-descricao" placeholder="Descrição do talento"></textarea>
                <button class="button" type="button">Adicionar</button>
            </div>
        `;
    });

    listaTalentos.addEventListener("click", function(e){
        if(e.target.classList.contains("botao-fechar")){
            e.target.closest(".talento-card").remove();
            return;
        }
        if(e.target.classList.contains("button")){
            const card = e.target.closest(".talento-card");
            const adicionar = card.querySelector(".button");
            const titulo = card.querySelector(".talento-titulo");
            const descricao = card.querySelector(".talento-descricao");
            const tituloExibido = card.querySelector(".talento-titulo-exibido");

            if(titulo.value.trim() === ""){
                mostrarPopup("Digite um nome para o talento antes de adicionar.");
                return;
            }

            // O input do título some e o texto digitado vira o cabeçalho do card
            tituloExibido.textContent = titulo.value.trim();
            tituloExibido.style.display = "block";
            titulo.style.display = "none";

            descricao.readOnly = true;
            adicionar.style.display = "none";
        }
    });

    const cardTalento = document.getElementById("card-talento");

    //Outros

    const idade = document.getElementById("idade");

    let aumentoBe=0;
    idade.addEventListener("input", ()=>{
        if(Number(idade.value)<16){
            if(conhecimento.value==""){
                mostrarPopup("Você precisa decidir seu Conhecimento antes de aplicar sua idade.");
                idade.value=0;
                return;
            }
            porteFisico.value="Infantil";
            if(aumentoBe == 0){
                be.value*=2;
                aumentoBe=1;
            }
        }
        if(Number(idade.value)>=16){
            porteFisico.value="Selecione";
            be.value=modConhecimento.value;
        }
        if(Number(idade.value)<16){
            porteFisico.value="Infantil";
            be.value=Number(modConhecimento.value)*2;
        }
    });

        //Maldições

    const CATALOGO_MALDICOES = @json(config('maldicoes', []));
    const CUSTOM_MALDICAO = "__custom__";

    const cardMaldicao = document.getElementById("maldicao-card");
    const fecharMaldicaoCard = document.getElementById("fechar-maldicao-card");
    const listaMaldicoes = document.getElementById("lista-maldicoes");
    const adicionarMaldicao = document.getElementById("adicionar-maldicao");
    const maldicao = document.getElementById("maldicao-adicao");
    const catalogoSelect = document.getElementById("maldicao-catalogo");
    const maldicaoPrevia = document.getElementById("maldicao-previa");
    const maldicaoCustom = document.getElementById("maldicao-custom");
    const maldicaoNomeCustom = document.getElementById("maldicao-nome-custom");
    const ameacaInput = document.getElementById("ameaca");
    const nivelHexomanteInput = document.getElementById("nivel-hexomante");

    let maldicoesAdicionadas = [];
    let proximoIdMaldicao = 1;

    maldicao.addEventListener("click", function(){
        cardMaldicao.classList.add("ativo");
    });

    fecharMaldicaoCard.addEventListener("click", function(){
        cardMaldicao.classList.remove("ativo");
    });

    // ---------- helpers ----------
    function esc(t){
        return String(t === null || t === undefined ? "" : t)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function paragrafos(t){
        return esc(t).split("\n").join("<br>");
    }

    function normalizar(s){
        return String(s || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
    }

    function acharSlugPorNome(nome){
        const alvo = normalizar(nome);
        for(const slug in CATALOGO_MALDICOES){
            if(normalizar(slug) === alvo || normalizar(CATALOGO_MALDICOES[slug].nome) === alvo){
                return slug;
            }
        }
        return null;
    }

    function linhaDado(rotulo, valor){
        if(!valor) return "";
        return "<div><dt>" + esc(rotulo) + "</dt><dd>" + paragrafos(valor) + "</dd></div>";
    }

    function htmlEfeitos(ef){
        if(!ef) return "";
        if(typeof ef === "string") return "<p>" + paragrafos(ef) + "</p>";
        let h = "";
        if(ef.texto) h += "<p>" + paragrafos(ef.texto) + "</p>";
        if(ef.lista && ef.lista.length){
            h += "<ul>" + ef.lista.map(i => "<li>" + esc(i) + "</li>").join("") + "</ul>";
        }
        if(ef.nota) h += '<p class="maldicao-nota">' + paragrafos(ef.nota) + "</p>";
        return h;
    }

    function htmlAprimoramentos(lista){
        if(!lista || !lista.length) return "";
        return '<h5 class="maldicao-subtitulo">Aprimoramentos</h5><ul class="maldicao-aprimoramentos">' +
            lista.map(a =>
                "<li>" +
                '<span class="maldicao-custo">' + esc(a.custo) + "</span> " +
                esc(a.texto) +
                (a.requisito ? ' <em class="maldicao-requisito">Requisito: ' + esc(a.requisito) + "</em>" : "") +
                "</li>"
            ).join("") + "</ul>";
    }

    function htmlTecnica(t){
        const req = parseInt(t.nivel, 10);
        return '<details class="maldicao-tecnica" data-nivel-req="' + (isNaN(req) ? "" : req) + '">' +
            "<summary>" +
                '<span class="maldicao-tecnica-nome">' + esc(t.nome) + "</span>" +
                '<span class="maldicao-trava" title="Nível de Hexomante insuficiente">🔒</span>' +
                '<span class="maldicao-tecnica-meta">' + esc(t.custo || "") + "</span>" +
            "</summary>" +
            '<div class="maldicao-tecnica-corpo">' +
                (t.descricao ? '<p class="maldicao-tecnica-desc">' + paragrafos(t.descricao) + "</p>" : "") +
                '<dl class="maldicao-tecnica-dados">' +
                    linhaDado("Custo", t.custo) +
                    linhaDado("Lógica", t.logica) +
                    linhaDado("Nível", t.nivel) +
                    linhaDado("Materiais", t.materiais) +
                    linhaDado("Processo", t.processo) +
                "</dl>" +
                (t.efeitos ? '<h5 class="maldicao-subtitulo">Efeitos</h5>' + htmlEfeitos(t.efeitos) : "") +
                htmlAprimoramentos(t.aprimoramentos) +
            "</div>" +
        "</details>";
    }

    function htmlAmeacas(cat, max){
        const todas = cat.ameacas || [];
        const liberadas = todas.filter(a => Number(a.nivel) <= max);
        const bloqueadas = todas.length - liberadas.length;

        let h = liberadas.map(a => {
            const efeitos = (a.efeitos || []).map(e =>
                '<div class="maldicao-efeito">' +
                    (e.marca ? '<span class="maldicao-marca">' + esc(e.marca) + "</span>" : "") +
                    "<div>" +
                        (e.rotulo ? "<strong>" + esc(e.rotulo) + "</strong>" : "") +
                        "<p>" + paragrafos(e.texto) + "</p>" +
                    "</div>" +
                "</div>"
            ).join("");

            return '<div class="maldicao-ameaca">' +
                '<div class="maldicao-ameaca-topo">' +
                    '<span class="personagem-card-tag">Ameaça ' + esc(a.nivel) + "</span>" +
                    (a.dado ? '<span class="maldicao-dado">🎲 ' + esc(a.dado) + "</span>" : "") +
                "</div>" +
                (a.regra ? '<p class="maldicao-regra">' + a.regra + "</p>" : "") + // HTML confiável vindo do config
                efeitos +
                (a.penalidade ? '<p class="maldicao-penalidade"><strong>Penalidade:</strong> ' + esc(a.penalidade) + "</p>" : "") +
            "</div>";
        }).join("");

        if(bloqueadas > 0){
            h += '<p class="maldicao-bloqueada-aviso">🔒 ' + bloqueadas + " ameaça(s) ainda não liberada(s) neste nível.</p>";
        }
        return h;
    }

    // ---------- estado -> campo da ficha ----------
    function sincronizarCampoMaldicoes(){
        const campo = document.getElementById("maldicoes");
        if(!campo) return;
        campo.value = maldicoesAdicionadas
            .map(m => m.nome + " (Ameaça " + m.ameaca + ")")
            .join("; ");
    }

    function atualizarTravasTecnicas(){
        const nivel = Number(nivelHexomanteInput.value || 0);
        document.querySelectorAll(".maldicao-tecnica").forEach(function(d){
            const req = d.dataset.nivelReq;
            d.classList.toggle("bloqueada", req !== "" && nivel < Number(req));
        });
    }

    // ---------- bloco de cada maldição adicionada ----------
    function criarBlocoMaldicao(entry){
        const cat = entry.slug ? CATALOGO_MALDICOES[entry.slug] : null;
        const el = document.createElement("details");
        el.className = "maldicao-bloco-form";
        el.dataset.id = entry.id;
        el.open = true;

        let corpo = "";
        if(cat){
            corpo =
                (cat.descricao ? '<p class="maldicao-descricao">' + paragrafos(cat.descricao) + "</p>" : "") +
                '<h4 class="maldicao-secao">Ameaças</h4>' +
                '<div class="maldicao-ameacas">' + htmlAmeacas(cat, entry.ameaca) + "</div>" +
                ((cat.tecnicas && cat.tecnicas.length)
                    ? '<h4 class="maldicao-secao">Técnicas</h4><div class="maldicao-tecnicas">' +
                      cat.tecnicas.map(htmlTecnica).join("") + "</div>"
                    : "");
        } else {
            corpo = '<p class="maldicao-bloqueada-aviso">Maldição fora do catálogo: apenas o nome e a ameaça vão para a ficha.</p>';
        }

        el.innerHTML =
            "<summary>" +
                '<span class="maldicao-bloco-nome">' + esc(entry.nome) + "</span>" +
                (cat ? '<span class="personagem-card-tag">' + esc(cat.raridade) + "</span>" : "") +
                '<button type="button" class="botao-remover-item botao-remover-maldicao" title="Remover maldição">Remover</button>' +
            "</summary>" +
            '<div class="maldicao-bloco-corpo">' +
                '<div class="maldicao-bloco-config">' +
                    "<label>Ameaça</label>" +
                    '<input type="number" min="0" class="maldicao-ameaca-input" value="' + esc(entry.ameaca) + '">' +
                "</div>" +
                corpo +
            "</div>";

        return el;
    }

    function adicionarEntrada(entry){
        entry.id = proximoIdMaldicao++;
        maldicoesAdicionadas.push(entry);
        listaMaldicoes.appendChild(criarBlocoMaldicao(entry));
        sincronizarCampoMaldicoes();
        atualizarTravasTecnicas();
    }

    // ---------- seletor ----------
    function montarSeletorCatalogo(){
        let opcoes = '<option value="">Selecione uma maldição</option>';
        for(const slug in CATALOGO_MALDICOES){
            const c = CATALOGO_MALDICOES[slug];
            opcoes += '<option value="' + esc(slug) + '">' + esc(c.nome) + " (" + esc(c.raridade) + ")</option>";
        }
        opcoes += '<option value="' + CUSTOM_MALDICAO + '">Outra (personalizada)</option>';
        catalogoSelect.innerHTML = opcoes;
    }

    catalogoSelect.addEventListener("change", function(){
        const v = catalogoSelect.value;
        maldicaoCustom.classList.toggle("visivel", v === CUSTOM_MALDICAO);

        if(v && v !== CUSTOM_MALDICAO){
            const c = CATALOGO_MALDICOES[v];
            const n = (c.tecnicas || []).length;
            maldicaoPrevia.textContent = c.raridade + " · " + (c.ameacas || []).length +
                " nível(is) de ameaça · " + n + " técnica(s)";
        } else {
            maldicaoPrevia.textContent = "";
        }
    });

    adicionarMaldicao.addEventListener("click", function(){
        const escolha = catalogoSelect.value;
        const ameaca = Math.max(0, parseInt(ameacaInput.value, 10) || 0);

        if(!escolha){
            mostrarPopup("Escolha uma maldição antes de adicionar.");
            return;
        }

        if(escolha === CUSTOM_MALDICAO){
            const nome = maldicaoNomeCustom.value.trim();
            if(nome === ""){
                mostrarPopup("Digite um nome para a maldição antes de adicionar.");
                return;
            }
            adicionarEntrada({ slug: null, nome: nome, ameaca: ameaca });
            maldicaoNomeCustom.value = "";
        } else {
            if(maldicoesAdicionadas.some(m => m.slug === escolha)){
                mostrarPopup("Essa maldição já foi adicionada.");
                return;
            }
            adicionarEntrada({ slug: escolha, nome: CATALOGO_MALDICOES[escolha].nome, ameaca: ameaca });
        }

        catalogoSelect.value = "";
        catalogoSelect.dispatchEvent(new Event("change"));
    });

    // ---------- remover / mudar ameaça de um bloco ----------
    listaMaldicoes.addEventListener("click", function(e){
        if(e.target.classList.contains("botao-remover-maldicao")){
            e.preventDefault();
            e.stopPropagation();
            const bloco = e.target.closest(".maldicao-bloco-form");
            maldicoesAdicionadas = maldicoesAdicionadas.filter(m => String(m.id) !== bloco.dataset.id);
            bloco.remove();
            sincronizarCampoMaldicoes();
        }
    });

    listaMaldicoes.addEventListener("input", function(e){
        if(!e.target.classList.contains("maldicao-ameaca-input")) return;

        const bloco = e.target.closest(".maldicao-bloco-form");
        const entry = maldicoesAdicionadas.find(m => String(m.id) === bloco.dataset.id);
        if(!entry) return;

        entry.ameaca = Math.max(0, parseInt(e.target.value, 10) || 0);

        const cat = entry.slug ? CATALOGO_MALDICOES[entry.slug] : null;
        if(cat){
            bloco.querySelector(".maldicao-ameacas").innerHTML = htmlAmeacas(cat, entry.ameaca);
        }
        sincronizarCampoMaldicoes();
    });

    nivelHexomanteInput.addEventListener("input", atualizarTravasTecnicas);

    // ---------- ao abrir uma ficha salva, reconstrói a partir do campo "maldicoes" ----------
    function carregarMaldicoesSalvas(){
        const campo = document.getElementById("maldicoes");
        if(!campo || campo.value.trim() === "") return;

        campo.value.split(/[;,\n]+/)
            .map(s => s.trim())
            .filter(s => s !== "" && s.toLowerCase() !== "nenhuma")
            .forEach(function(entrada){
                let nome = entrada;
                let ameaca = 0;
                const m = entrada.match(/^(.*?)\s*\(\s*Amea[çc]a\s*(\d+)\s*\)$/i);
                if(m){
                    nome = m[1].trim();
                    ameaca = parseInt(m[2], 10);
                }
                const slug = acharSlugPorNome(nome);
                adicionarEntrada({
                    slug: slug,
                    nome: slug ? CATALOGO_MALDICOES[slug].nome : nome,
                    ameaca: ameaca
                });
            });
    }

    montarSeletorCatalogo();
    carregarMaldicoesSalvas();


    // ==================== Itens e Ações ====================
const tituloItens = document.getElementById("titulo-itens");
const botaoOcultarItens = document.getElementById("ocultar-itens");
const itensConteudo = document.getElementById("itens-conteudo");

let itensOcultos = 0;
botaoOcultarItens.addEventListener("click", function(){
    if(itensOcultos === 1){
        itensConteudo.classList.remove("oculto");
        itensOcultos = 0;
        tituloItens.classList.remove("titulo-colapsavel");
        return;
    }
    itensConteudo.classList.add("oculto");
    tituloItens.classList.add("titulo-colapsavel");
    itensOcultos = 1;
});

const itemNome = document.getElementById("item-nome");
const itemCategoria = document.getElementById("item-categoria");
const itemQtdDados = document.getElementById("item-qtd-dados");
const itemDado = document.getElementById("item-dado");
const itemValorFixo = document.getElementById("item-valor-fixo");
const itemDescricao = document.getElementById("item-descricao");
const itemModificacoes = document.getElementById("item-modificacoes");
const adicionarItem = document.getElementById("adicionar-item");
const listaItens = document.getElementById("lista-itens");

        const itensAdicionadosTitulo = document.getElementById("itens-adicionados-titulo");

    adicionarItem.addEventListener("click", function(){
        if(itemNome.value.trim() === ""){
            mostrarPopup("Digite um nome para o item antes de adicionar.");
            return;
        }

        const nome = itemNome.value.trim();
        const categoria = itemCategoria.value === "Selecione" ? "" : itemCategoria.value;
        const qtdDados = Number(itemQtdDados.value) || 0;
        const ladosDado = Number(itemDado.value) || 0;
        const valorFixo = Number(itemValorFixo.value) || 0;
        const descricao = itemDescricao.value.trim();
        const modificacoes = itemModificacoes.value.trim();

        let textoDano = "";
        if(qtdDados > 0 && ladosDado > 0){
            textoDano = qtdDados + "d" + ladosDado;
            if(valorFixo !== 0){
                textoDano += (valorFixo > 0 ? " + " : " - ") + Math.abs(valorFixo);
            }
        } else if(valorFixo !== 0){
            textoDano = String(valorFixo);
        }

        const card = document.createElement("div");
        card.className = "item-card";
        card.dataset.qtdDados = qtdDados;
        card.dataset.ladosDado = ladosDado;
        card.dataset.valorFixo = valorFixo;

        card.innerHTML = `
            <div class="item-card-topo">
                <span class="item-card-nome">${nome}</span>
                ${categoria ? `<span class="item-tag">${categoria}</span>` : ""}
                <button class="botao-remover-item" type="button" title="Remover item">Remover</button>
            </div>
            ${textoDano ? `<div class="item-card-dano">Dano/Valor: ${textoDano}</div>` : ""}
            ${descricao ? `<div class="item-card-descricao">${descricao}</div>` : ""}
            ${modificacoes ? `<div class="item-card-modificacoes"><strong>Modificações:</strong> ${modificacoes}</div>` : ""}
            <div class="item-card-rodape">
                <button class="botao-rolar-item" type="button" ${(qtdDados > 0 && ladosDado > 0) || valorFixo !== 0 ? "" : "disabled"}>Rolar</button>
                <span class="item-card-resultado"></span>
            </div>
        `;

        listaItens.appendChild(card);
        itensAdicionadosTitulo.style.display = "block";

        itemNome.value = "";
        itemCategoria.value = "Selecione";
        itemQtdDados.value = "";
        itemDado.value = "0";
        itemValorFixo.value = "";
        itemDescricao.value = "";
        itemModificacoes.value = "";
    });

        listaItens.addEventListener("click", function(e){
        if(e.target.classList.contains("botao-remover-item")){
            e.target.closest(".item-card").remove();
            if(listaItens.children.length === 0){
                itensAdicionadosTitulo.style.display = "none";
            }
            return;
        }

        if(e.target.classList.contains("botao-rolar-item")){
            const card = e.target.closest(".item-card");
            const qtdDados = Number(card.dataset.qtdDados);
            const ladosDado = Number(card.dataset.ladosDado);
            const valorFixo = Number(card.dataset.valorFixo);
            const resultadoSpan = card.querySelector(".item-card-resultado");

            let total = valorFixo;
            let detalhes = [];

            for(let i = 0; i < qtdDados; i++){
                const rolado = rolarDado(ladosDado);
                detalhes.push(rolado);
                total += rolado;
            }

            resultadoSpan.textContent = detalhes.length > 0
                ? "Resultado: " + total + " (" + detalhes.join(" + ") +
                (valorFixo !== 0 ? " " + (valorFixo > 0 ? "+ " : "- ") + Math.abs(valorFixo) : "") + ")"
                : "Resultado: " + total;
        }
    });


    // ==================== Rolador de Dados Diversos ====================
const dadoRoladorQtd = document.getElementById("dado-rolador-qtd");
const dadoRoladorTipo = document.getElementById("dado-rolador-tipo");
const rolarDadoDiverso = document.getElementById("rolar-dado-diverso");
const resultadoDadoDiverso = document.getElementById("resultado-dado-diverso");
const resultadoDadoDiversoDetalhe = document.getElementById("resultado-dado-diverso-detalhe");
const roladorResultadoCard = document.getElementById("rolador-resultado-card");

rolarDadoDiverso.addEventListener("click", function(){
    let qtd = Number(dadoRoladorQtd.value);
    const lados = Number(dadoRoladorTipo.value);

    if(isNaN(qtd) || qtd < 1){
        qtd = 1;
        dadoRoladorQtd.value = 1;
    }

    let total = 0;
    let resultados = [];

    for(let i = 0; i < qtd; i++){
        const rolado = rolarDado(lados);
        resultados.push(rolado);
        total += rolado;
    }

    resultadoDadoDiverso.textContent = total;
    resultadoDadoDiversoDetalhe.textContent = qtd + "d" + lados + " (" + resultados.join(" + ") + ")";

    roladorResultadoCard.classList.remove("piscar");
    void roladorResultadoCard.offsetWidth;
    roladorResultadoCard.classList.add("piscar");
});

const rolarPreviewItem = document.getElementById("rolar-preview-item");
const rolarPreviewResultado = document.getElementById("rolar-preview-resultado");

rolarPreviewItem.addEventListener("click", function(){
    const qtdDados = Number(itemQtdDados.value) || 0;
    const ladosDado = Number(itemDado.value) || 0;
    const valorFixo = Number(itemValorFixo.value) || 0;

    if(qtdDados <= 0 && valorFixo === 0){
        mostrarPopup("Defina ao menos uma quantidade de dados ou um valor fixo antes de rolar.");
        return;
    }

    let total = valorFixo;
    let detalhes = [];

    for(let i = 0; i < qtdDados; i++){
        const rolado = rolarDado(ladosDado);
        detalhes.push(rolado);
        total += rolado;
    }

    let textoDetalhe = detalhes.length > 0
        ? qtdDados + "d" + ladosDado + " (" + detalhes.join(" + ") + ")" +
          (valorFixo !== 0 ? " " + (valorFixo > 0 ? "+ " : "- ") + Math.abs(valorFixo) : "")
        : String(valorFixo);

    rolarPreviewResultado.textContent = "Resultado: " + total + " — " + textoDetalhe;
});

document.querySelectorAll("form button:not([type])").forEach(function(botao){
    botao.setAttribute("type", "button");
});

document.querySelector("form").addEventListener("submit", function(){
    const sync = (spanId, atualId, maximoId) => {
        const partes = document.getElementById(spanId).textContent.split("/").map(v => v.trim());
        document.getElementById(atualId).value = partes[0] !== "?" ? partes[0] : "";
        document.getElementById(maximoId).value = partes[1] !== "?" ? partes[1] : "";
    };
    sync("vida", "vida_atual", "vida_maxima");
    sync("sanidade", "sanidade_atual", "sanidade_maxima");
    sync("aura", "aura_atual", "aura_maxima");
});

// ==================== Preenchimento Rápido (Teste) ====================
const botaoPreencherTeste = document.getElementById("preencher-teste");

botaoPreencherTeste.addEventListener("click", function(){

    // Informações pessoais
    document.getElementById("nome").value = "Agente Teste";
    document.getElementById("raca").value = "Humano";
    document.getElementById("maldicoes").value = "Nenhuma";

    // Atributos (usa o botão já existente pra distribuir e recalcular tudo certo)
    espalharPontos.click();

    // Idade (precisa vir antes do porte físico, senão o listener da idade sobrescreve o porte)
    idade.value = 25;
    idade.dispatchEvent(new Event("input"));

    // Porte físico
    porteFisico.value = "Comum";
    porteFisico.dispatchEvent(new Event("change"));

    // Profissão
    profissao.value = "Criminoso";
    profissao.dispatchEvent(new Event("change"));

    // Vida, Sanidade, Aura e Sorte
    rolagemVida.click();
    rolagemSanidade.click();
    rolagemAura.click();
    rolagemSorte.click();

    // Libera bastante pontos de habilidade só pra esse preenchimento de teste
    pontosHabilidadeRestantes = 9999;
    pontosHabilidadeRestantesTexto.textContent = "Pontos a distribuir: " + pontosHabilidadeRestantes;

    habilidades.forEach(function(input){
        input.value = String(Math.floor(Math.random() * 10) + 1);
        input.dispatchEvent(new Event("input"));
    });

    // Maldições (nível de hexomante e ameaça)
    document.getElementById("nivel-hexomante").value = 1;
    document.getElementById("ameaca").value = 10;

    // Item de exemplo
    itemNome.value = "Faca";
    itemCategoria.value = "Arma";
    itemQtdDados.value = 1;
    itemDado.value = "6";
    itemValorFixo.value = 2;
    itemDescricao.value = "Uma faca comum";
    itemModificacoes.value = "Nenhuma";
    adicionarItem.click();

    // Descrição
    document.getElementById("descricao").value = "Ficha gerada automaticamente para teste.";

    mostrarPopup("Ficha preenchida com dados de teste! Já pode salvar.");
});

</script>
@endsection