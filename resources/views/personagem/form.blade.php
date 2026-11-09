@extends('layout')

@section('conteudo')
@php
    $editando = isset($personagem);
    // Regra dos modificadores: primeiro dígito do valor (80 => 8); de 1 a 9 o mod é sempre 1; 0 => 0
    $modDe = fn ($v) => $v >= 10 ? intdiv($v, 10) : ($v >= 1 ? 1 : 0);
    // Mestre vendo a ficha de um jogador (campanhas): mesma página, mas nada pode ser alterado nem salvo
    $somenteLeitura = !empty($somenteLeitura ?? false);
    $habilidadesSalvas = $personagem->habilidades ?? [];
    // Valores extras de cada habilidade, com a origem: {"luta": [{"origem": "...", "valor": 5}]} (sem limite de pontos)
    $habilidadesExtras = $personagem->habilidades_extras ?? [];
    if (is_string($habilidadesExtras)) { $habilidadesExtras = json_decode($habilidadesExtras, true) ?: []; }
    if (!is_array($habilidadesExtras)) { $habilidadesExtras = []; }
    $pontosGastosAtributos = ($personagem->forca ?? 0) + ($personagem->constituicao ?? 0) + ($personagem->destreza ?? 0)
        + ($personagem->conhecimento ?? 0) + ($personagem->carisma ?? 0) + ($personagem->percepcao ?? 0) + ($personagem->alma ?? 0);

    // Pontos extras ganhos de fontes externas (atributos e habilidades)
    $extraAtributos   = (int) ($personagem->pontos_extras_atributos ?? 0);
    $extraHabilidades = (int) ($personagem->pontos_extras_habilidades ?? 0);
    // Ajuste manual dos pontos de habilidade (modo "ficha já existente"); pode ser negativo
    $ajusteHabilidades = (int) ($personagem->pontos_habilidade_ajuste ?? 0);
    // Mods de atributo ajustados à mão (modo "ficha já existente"): {"forca": 10, ...}
    $modsManuais = $personagem->mods_atributos ?? [];
    if (is_string($modsManuais)) { $modsManuais = json_decode($modsManuais, true) ?: []; }
    if (!is_array($modsManuais)) { $modsManuais = []; }

    $pontosRestantesIniciais = $editando ? (300 + $extraAtributos - $pontosGastosAtributos) : 300;

    $vitaisExtrasSalvos = $personagem->vitais_extras ?? [];
    if (is_string($vitaisExtrasSalvos)) {
        $vitaisExtrasSalvos = json_decode($vitaisExtrasSalvos, true) ?: [];
    }

    // Distribuição de níveis por profissão (um item por nível mundano)
    $niveisProfissaoSalvos = $personagem->niveis_profissao ?? [];
    if (is_string($niveisProfissaoSalvos)) {
        $niveisProfissaoSalvos = json_decode($niveisProfissaoSalvos, true) ?: [];
    }
    if (!is_array($niveisProfissaoSalvos)) {
        $niveisProfissaoSalvos = [];
    }

    // Itens e ações registrados na ficha
    $itensSalvos = $personagem->itens ?? [];
    if (is_string($itensSalvos)) {
        $itensSalvos = json_decode($itensSalvos, true) ?: [];
    }
    if (!is_array($itensSalvos)) {
        $itensSalvos = [];
    }

    // Pastas de organização (talentos, técnicas, anotações)
    $pastasSalvas = $personagem->pastas ?? [];
    if (is_string($pastasSalvas)) {
        $pastasSalvas = json_decode($pastasSalvas, true) ?: [];
    }
    if (!is_array($pastasSalvas)) {
        $pastasSalvas = [];
    }

    // Talentos mundanos registrados na ficha
    $talentosSalvos = $personagem->talentos ?? [];
    if (is_string($talentosSalvos)) {
        $talentosSalvos = json_decode($talentosSalvos, true) ?: [];
    }
    if (!is_array($talentosSalvos)) {
        $talentosSalvos = [];
    }

    // Técnicas registradas na ficha
    $tecnicasSalvas = $personagem->tecnicas ?? [];
    if (is_string($tecnicasSalvas)) {
        $tecnicasSalvas = json_decode($tecnicasSalvas, true) ?: [];
    }
    if (!is_array($tecnicasSalvas)) {
        $tecnicasSalvas = [];
    }

    // Armaduras e resistências (coluna armaduras; pode ainda não existir)
    $tiposDanoArmadura = ['cortante', 'perfurante', 'impacto', 'explosivo', 'flamejante', 'congelante', 'eletrizante', 'tóxico', 'amaldiçoado', 'queda'];
    $armaduraPadrao = [
        'pecas' => [
            ['nome' => 'Capacete de armadura', 'descricao' => 'Um capacete de aço comum e customizável.', 'tamanho' => 'Médio', 'alcance_min' => 1, 'alcance_max' => 15, 'defesa' => 6, 'contra_corte_impacto' => 20, 'defesa_base' => 0, 'penalidade_destreza' => 4, 'peso' => 3.5, 'equipada' => false],
            ['nome' => 'Peitoral de armadura', 'descricao' => 'Peitoral comum de aço customizável.', 'tamanho' => 'Médio', 'alcance_min' => 16, 'alcance_max' => 30, 'defesa' => 8, 'contra_corte_impacto' => 25, 'defesa_base' => 5, 'penalidade_destreza' => 6, 'peso' => 5.0, 'equipada' => false],
            ['nome' => 'Calças de armadura', 'descricao' => 'Calças de armadura feitas de aço customizável.', 'tamanho' => 'Médio', 'alcance_min' => 31, 'alcance_max' => 45, 'defesa' => 8, 'contra_corte_impacto' => 25, 'defesa_base' => 5, 'penalidade_destreza' => 6, 'peso' => 5.5, 'equipada' => false],
            ['nome' => 'Braceiras de armadura', 'descricao' => 'Proteções para os braços feitas de aço e customizáveis.', 'tamanho' => 'Médio', 'alcance_min' => 46, 'alcance_max' => 60, 'defesa' => 4, 'contra_corte_impacto' => 14, 'defesa_base' => 5, 'penalidade_destreza' => 5, 'peso' => 2.5, 'equipada' => false],
        ],
        'armadura_natural' => 0,
        'resistencias' => [],
        'imunidades' => [],
    ];
    $armaduraSalva = $personagem->armaduras ?? null;
    if (is_string($armaduraSalva)) {
        $armaduraSalva = json_decode($armaduraSalva, true);
    }
    if (!is_array($armaduraSalva) || !isset($armaduraSalva['pecas']) || !is_array($armaduraSalva['pecas'])) {
        $armaduraSalva = $armaduraPadrao;
    }

    // Artes marciais (coluna artes_marciais; pode ainda não existir): { chave: { nivel, xp, talentos[] } }
    $artesSalvas = $personagem->artes_marciais ?? [];
    if (is_string($artesSalvas)) {
        $artesSalvas = json_decode($artesSalvas, true);
    }
    if (!is_array($artesSalvas)) {
        $artesSalvas = [];
    }

    // Propriedades fisiomantes salvas na ficha (coluna propriedades_fisiomantes) e catálogo completo (resources/data)
    $propriedadesSalvas = $personagem->propriedades_fisiomantes ?? [];
    if (is_string($propriedadesSalvas)) {
        $propriedadesSalvas = json_decode($propriedadesSalvas, true) ?: [];
    }
    if (!is_array($propriedadesSalvas)) {
        $propriedadesSalvas = [];
    }
    $catalogoPropriedades = ['grupos' => [], 'fontes' => []];
    $arquivoCatalogo = resource_path('data/propriedades_fisiomantes.json');
    if (is_file($arquivoCatalogo)) {
        $catalogoPropriedades = json_decode((string) file_get_contents($arquivoCatalogo), true) ?: $catalogoPropriedades;
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

/* ===== Botões: tamanho e alinhamento consistentes ===== */
#form-ficha .button,
#form-ficha .botao-adicionar-talento,
#form-ficha .botao-rolar-preview {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 46px;
    padding: 0 22px;
    box-sizing: border-box;
    line-height: 1.2;
    text-align: center;
    border-radius: 10px;
    cursor: pointer;
}

/* Botão X no topo dos cards (Talentos, Maldições, Rolagem) */
#form-ficha .card-rolagem > .botao-fechar {
    width: 36px;
    height: 36px;
    min-height: 0;
    padding: 0;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .9rem;
    line-height: 1;
}

/* ===== Pontos extras ===== */
.pontos-extras {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    margin: 8px 0 16px;
}
.pontos-extras label { margin: 0; width: auto; white-space: nowrap; }
.pontos-extras input[type="text"] {
    flex: 0 0 90px;
    width: 90px !important;
    max-width: 90px;
    height: 40px;
    padding: 0 10px;
    box-sizing: border-box;
    text-align: center;
}
#pontos-a-distribuir { flex-direction: column; align-items: center; gap: 6px; }

/* ===== Talentos (Mundanos e de Arte) ===== */
#talentos-mundanos-card, #talentos-arte-card {
    max-height: 85vh !important;
    overflow-y: auto;
    padding-bottom: 20px;
}
#talentos-mundanos-card > .botao-adicionar-talento,
#talentos-arte-card .aba-conteudo > .botao-adicionar-talento { width: 100%; margin-top: 8px; }

/* ===== Abas dentro dos painéis (Técnicas de arte, Técnicas) ===== */
.abas-painel { display: flex; gap: 6px; margin: 10px 0 6px; border-bottom: 1px solid rgba(255, 255, 255, .14); }
.aba-painel {
    flex: 1 1 0; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    min-height: 38px; padding: 0 10px; background: rgba(0, 0, 0, .25); color: inherit; font: inherit; font-size: .9rem;
    border: 1px solid rgba(255, 255, 255, .14); border-bottom: none; border-radius: 10px 10px 0 0; cursor: pointer; opacity: .8;
    transition: border-color .15s ease, background .15s ease, opacity .15s ease;
}
.aba-painel:hover { opacity: 1; border-color: rgba(220, 70, 70, .6); }
.aba-painel.ativo { opacity: 1; background: #8b1a1a; border-color: rgba(220, 70, 70, .85); }
.aba-conteudo { display: none; }
.aba-conteudo.ativo { display: block; }

/* ===== Vitais em abas: Vitais / Armaduras / Resistências ===== */
#vitais-abas > .aba-conteudo.ativo[data-aba="vitais"] { display: flex; flex-direction: column; gap: 18px; }
#vitais-abas > .aba-conteudo.ativo[data-aba="armaduras"], #vitais-abas > .aba-conteudo.ativo[data-aba="resistencias"] { display: flex; flex-direction: column; gap: 10px; }
#vitais-abas .aba-painel { font-size: .85rem; padding: 0 4px; min-width: 0; }
.arm-natural { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.arm-natural input { width: 90px; max-width: 100%; box-sizing: border-box; }
.arm-lista { display: flex; flex-direction: column; gap: 8px; }
.arm-peca { background: rgba(0, 0, 0, .25); border: 1px solid rgba(255, 255, 255, .12); border-radius: 10px; padding: 10px 12px; min-width: 0; }
.arm-peca.equipada { border-color: rgba(220, 70, 70, .6); }
.arm-peca.atingida { box-shadow: 0 0 0 2px #8b1a1a, 0 0 12px rgba(220, 70, 70, .6); }
.arm-peca-topo { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.arm-peca-nome { font-weight: 700; flex: 1 1 140px; min-width: 0; overflow-wrap: anywhere; }
.arm-faixa { font-size: .8rem; opacity: .75; }
.arm-peca-stats { display: flex; flex-wrap: wrap; gap: 4px 12px; margin: 6px 0; font-size: .85rem; }
.arm-peca-desc { font-size: .82rem; opacity: .75; margin: 4px 0; overflow-wrap: anywhere; }
.arm-peca-acoes { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
.arm-peca-acoes label, .arm-check { display: inline-flex; align-items: center; gap: 6px; font-size: .88rem; }
.arm-peca-acoes .arm-mini { margin-left: auto; }
.historico-rolagens { margin-top: 12px; padding: 10px 12px; border-radius: 10px; background: rgba(0, 0, 0, .22); border: 1px solid rgba(255, 255, 255, .1); }
.historico-rolagens-cab { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px; }
.historico-rolagens-lista { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
.historico-rolagens-lista li { display: grid; grid-template-columns: auto minmax(0, 1fr) auto auto; align-items: center; gap: 4px 10px; padding: 5px 8px; border-radius: 6px; background: rgba(255, 255, 255, .04); font-size: .88rem; }
.historico-rolagens-lista .hr-hora { opacity: .55; font-size: .78rem; }
.historico-rolagens-lista .hr-rotulo { overflow-wrap: anywhere; }
.historico-rolagens-lista .hr-total { font-size: 1.05rem; }
.historico-rolagens-lista .hr-detalhe { grid-column: 2 / 4; opacity: .65; font-size: .78rem; overflow-wrap: anywhere; }
.historico-rolagens-lista .hr-excluir { grid-row: 1; grid-column: 4; width: 26px; height: 26px; border-radius: 6px; border: 1px solid rgba(255, 255, 255, .15); background: none; color: inherit; cursor: pointer; }
.historico-rolagens-vazio { opacity: .6; font-size: .85rem; margin: 4px 0; }
#historico-rolagens-mais { margin-top: 6px; }
.ficha-condicoes-topo { margin: 8px 0 12px; padding: 8px 12px; border-radius: 10px; background: rgba(127, 29, 29, .35); border: 1px solid rgba(248, 113, 113, .45); font-size: .9rem; }
.arm-chip--cond.ativo { background: rgba(185, 28, 28, .55); border-color: rgba(248, 113, 113, .8); }
.arm-cond-especiais { width: 100%; box-sizing: border-box; min-height: 64px; padding: 8px 10px; border-radius: 8px; background: rgba(0, 0, 0, .25); color: inherit; border: 1px solid rgba(255, 255, 255, .14); font: inherit; resize: vertical; }
.arm-dica { opacity: .7; font-size: .82rem; margin: 2px 0 6px; }
.arm-mini { min-height: 32px; padding: 0 10px; background: rgba(0, 0, 0, .25); color: inherit; border: 1px solid rgba(255, 255, 255, .14); border-radius: 8px; cursor: pointer; font: inherit; font-size: .8rem; }
.arm-mini:hover { border-color: rgba(220, 70, 70, .7); }
.arm-mini + .arm-mini { margin-left: 0 !important; }
.arm-edit { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 8px; margin-top: 6px; }
.arm-edit label { display: flex; flex-direction: column; gap: 2px; font-size: .75rem; opacity: .9; min-width: 0; }
.arm-edit label.larga { grid-column: 1 / -1; }
.arm-edit input, .arm-edit textarea { width: 100%; box-sizing: border-box; min-width: 0; }
.arm-aviso { font-size: .8rem; color: #e8a0a0; margin: 0; }
.arm-resumo { font-size: .9rem; padding: 8px 10px; background: rgba(0, 0, 0, .25); border: 1px solid rgba(255, 255, 255, .10); border-radius: 10px; overflow-wrap: anywhere; }
.arm-rolar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.arm-rolar-res { font-size: .88rem; overflow-wrap: anywhere; }
.arm-dano { background: rgba(0, 0, 0, .25); border: 1px solid rgba(255, 255, 255, .10); border-radius: 10px; padding: 10px 12px; display: flex; flex-direction: column; gap: 8px; }
.arm-dano h4, .arm-grupo-titulo { margin: 0; font-size: .95rem; }
.arm-dano-linhas { display: flex; flex-direction: column; gap: 8px; }
.arm-dano-linha { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto; gap: 8px; align-items: center; }
.arm-dano-linha input, .arm-dano-linha select { width: 100%; box-sizing: border-box; min-width: 0; }
.arm-dano-linha .arm-mini[disabled] { opacity: .35; }
.arm-dica { opacity: .7; font-size: .75rem; }
.arm-log { display: flex; flex-direction: column; gap: 4px; font-size: .8rem; opacity: .9; }
.arm-log:empty { display: none; }
.arm-log div { padding: 4px 6px; border-left: 2px solid rgba(220, 70, 70, .6); overflow-wrap: anywhere; }
.arm-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.arm-chip { min-height: 34px; padding: 0 12px; background: rgba(0, 0, 0, .25); color: inherit; border: 1px solid rgba(255, 255, 255, .14); border-radius: 999px; cursor: pointer; font: inherit; font-size: .85rem; }
.arm-chip.ativo { background: #8b1a1a; border-color: rgba(220, 70, 70, .85); }
.arm-chip:disabled { cursor: default; }

/* sub-abas (dentro de uma aba): um pouco menores e com quebra de linha no celular */
.abas-internas { margin-top: 2px; }
.abas-internas .aba-painel { font-size: .82rem; min-height: 34px; text-align: center; flex-wrap: wrap; }

/* ===== Propriedades fisiomânticas (Técnicas de arte > Fisiomancia > sub-aba) ===== */
.propriedades-titulo { margin: 12px 0 0; font-size: 1.05rem; text-align: center; }
.propriedades-fisio .maldicao-subtitulo { margin: 14px 0 8px; }
.prop-busca-linha { display: grid; grid-template-columns: minmax(0, 1fr); gap: 8px; align-items: center; }
.prop-busca-linha input, .prop-busca-linha select { height: 42px; min-width: 0; width: 100%; box-sizing: border-box; }
.prop-grupo-descricao { font-size: .82rem; opacity: .75; margin: 8px 0 0; }
.prop-grupo-descricao:empty { display: none; }
#prop-catalogo { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
.prop-fonte { background: rgba(0, 0, 0, .25); border: 1px solid rgba(255, 255, 255, .10); border-radius: 10px; padding: 0 12px; }
.prop-fonte > summary { cursor: pointer; list-style: none; display: flex; align-items: center; gap: 8px; min-height: 40px; font-weight: 600; }
.prop-fonte > summary::-webkit-details-marker { display: none; }
.prop-fonte > summary::before { content: "▸"; opacity: .7; font-size: .8rem; }
.prop-fonte[open] > summary::before { content: "▾"; }
.prop-fonte .prop-qtd { opacity: .65; font-size: .78rem; font-weight: 400; font-variant-numeric: tabular-nums; }
.prop-fonte .prop-fonte-descricao { font-size: .82rem; opacity: .75; margin: 0 0 8px; }
.prop-item { padding: 8px 0 10px; border-top: 1px solid rgba(255, 255, 255, .08); display: flex; flex-direction: column; gap: 4px; }
.prop-item-cab { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.prop-item-cab strong { font-size: .95rem; }
.prop-ameaca { font-size: .75rem; padding: 2px 8px; border-radius: 999px; background: rgba(220, 70, 70, .22); border: 1px solid rgba(220, 70, 70, .5); white-space: nowrap; }
.prop-item-cab .button { margin-left: auto; min-height: 32px; min-width: 0; padding: 0 12px; font-size: .8rem; }
.prop-item p, .prop-card p { margin: 0; font-size: .86rem; line-height: 1.4; }
.prop-item .prop-ref { opacity: .7; font-style: italic; }
#prop-minhas-lista { display: flex; flex-direction: column; gap: 8px; }
.prop-card { background: rgba(0, 0, 0, .25); border: 1px solid rgba(220, 70, 70, .35); border-radius: 10px; padding: 10px 12px; display: flex; flex-direction: column; gap: 6px; }
.prop-card .botao-remover-item { margin-left: auto; }
.prop-card-linha { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: .82rem; }
.prop-card-linha label { margin: 0; width: auto; display: inline-flex; align-items: center; gap: 6px; }
.prop-card-linha input[type="number"] { width: 76px; height: 34px; }
.prop-card-linha input[type="text"] { flex: 1 1 160px; min-width: 0; height: 34px; }

.lista-talentos {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 14px;
    margin: 16px 0;
}
.talento-topo { display: flex; align-items: flex-start; gap: 10px; }
.talento-topo .talento-titulo,
.talento-topo .talento-titulo-exibido { flex: 1; min-width: 0; }
.talento-topo .talento-titulo-exibido { padding-top: 4px; }

.talento-card .botao-fechar.talento-remover {
    position: static !important;
    transform: none;
    flex: 0 0 30px;
    width: 30px;
    height: 30px;
    max-width: none;
    margin: 0;
    padding: 0;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .8rem;
    line-height: 1;
}
.talento-card .button { align-self: flex-end; min-width: 140px; }

/* Talentos: custo de Aura e ativação */
.talento-custo-edicao { display: flex; align-items: center; gap: 10px; }
.talento-custo-edicao label { margin: 0; width: auto; white-space: nowrap; }
.talento-custo-edicao .talento-custo { width: 110px; flex: none; box-sizing: border-box; }
.talento-custo-tag { font-weight: 600; font-variant-numeric: tabular-nums; }
.talento-custo-edicao { flex-wrap: wrap; }
.talento-custo-edicao .talento-eficiencia-input { width: 110px; flex: none; box-sizing: border-box; }
.talento-aprim-edicao { display: flex; flex-direction: column; gap: 8px; }
.talento-aprim-edicao .talento-aprim-add { width: 100%; }
.talento-aprim-view:empty { display: none; }
.talento-card .talento-eficiencia-view { align-self: flex-start; }
.talento-acoes { display: flex; justify-content: flex-end; align-items: center; gap: 10px; flex-wrap: wrap; }
.talento-toggle {
    min-width: 140px; padding: 9px 16px; border-radius: 8px; cursor: pointer;
    background: transparent; color: #fff; border: 1px solid rgba(220, 70, 70, .6);
    font-family: inherit; letter-spacing: .04em; text-transform: uppercase; font-size: .8rem;
}
.talento-toggle:hover { background: rgba(139, 26, 26, .35); }
.talento-card.talento-ativo { border-color: rgba(220, 70, 70, .85); box-shadow: inset 0 0 0 1px rgba(220, 70, 70, .35); }
.talento-card.talento-ativo .talento-toggle { background: #8b1a1a; }

/* Popup de distribuição de níveis: grade rolável em vez de uma coluna gigante */
#distribuir-niveis-popup .popup-conteudo {
    display: flex; flex-direction: column; gap: 14px;
    width: min(760px, 92vw); max-width: 92vw;
    max-height: calc(100vh - 150px);
    box-sizing: border-box; overflow: hidden;
}
#distribuir-niveis-popup h3 { margin: 0; text-align: center; flex: none; }
#campos-distribuicao-niveis {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 12px; overflow-y: auto; min-height: 0; flex: 1 1 auto; padding: 4px 6px 4px 2px;
}
#campos-distribuicao-niveis .campo { display: flex; flex-direction: column; align-items: stretch; gap: 4px; margin: 0; }
#campos-distribuicao-niveis .campo label { text-align: left; margin: 0; width: auto; }
#campos-distribuicao-niveis .campo select { width: 100%; margin: 0 !important; }
#fechar-distribuir { flex: none; align-self: center; }

/* ===== Botões de fechar (X) ===== */
#form-ficha .botao-fechar {
    display: inline-flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; min-height: 0; padding: 0; border-radius: 50%;
    background: #8b1a1a; color: #fff;
    border: 1px solid rgba(255, 255, 255, .16);
    font: 700 1rem/1 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; cursor: pointer;
    flex: 0 0 auto;
    transition: background .15s ease, transform .15s ease, box-shadow .15s ease;
}
#form-ficha .botao-fechar:hover {
    background: #b3201f; transform: scale(1.08) rotate(90deg);
    box-shadow: 0 0 0 4px rgba(179, 32, 31, .25);
}
#form-ficha .botao-fechar:active { transform: scale(.94); }
#form-ficha .botao-fechar:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }

/* Nos painéis com rolagem (Talentos, Técnicas, Anotações, Maldições) o X fica preso ao canto
   superior direito e acompanha a rolagem, sem sumir quando a lista é longa */
#form-ficha #talentos-mundanos-card > .botao-fechar,
#form-ficha #talentos-arte-card > .botao-fechar,
#form-ficha #tecnicas-card > .botao-fechar,
#form-ficha #anotacoes-card > .botao-fechar,
#form-ficha #dinheiro-card > .botao-fechar,
#form-ficha #ama-card > .botao-fechar,
#form-ficha #maldicao-card > .botao-fechar {
    position: sticky; top: 0; align-self: flex-end;
    margin: 0 0 -36px auto; z-index: 10;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .5);
}

/* ===== Pastas ===== */
.pastas-barra { display: flex; flex-direction: column; gap: 8px; margin: 12px 0; }
.pastas-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.pasta-chip {
    display: inline-flex; align-items: center; gap: 6px; min-height: 34px; padding: 0 12px;
    border-radius: 999px; background: rgba(0, 0, 0, .25); color: inherit; font: inherit; font-size: .85rem;
    border: 1px solid rgba(255, 255, 255, .14); cursor: pointer;
    transition: border-color .15s ease, background .15s ease;
}
.pasta-chip:hover { border-color: rgba(220, 70, 70, .6); }
.pasta-chip.ativo { background: #8b1a1a; border-color: rgba(220, 70, 70, .85); }
.pasta-chip.pasta-nova { border-style: dashed; }
.pasta-chip .pasta-qtd { opacity: .7; font-size: .75rem; font-variant-numeric: tabular-nums; }
.pastas-acoes { display: flex; flex-wrap: wrap; gap: 10px; }
.pasta-link {
    background: none; border: none; color: inherit; opacity: .75; cursor: pointer;
    font: inherit; font-size: .8rem; text-decoration: underline; padding: 2px 4px;
}
.pasta-link:hover { opacity: 1; }
.pasta-link.perigo.confirmar { color: #ff8a8a; opacity: 1; }
.pasta-editor { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.pasta-editor input { flex: 1 1 180px; min-width: 0; }
.pasta-editor .button { min-height: 38px; }
.pasta-erro { color: #ff8a8a; font-size: .8rem; margin: 0; }
.item-pasta { display: flex; align-items: center; gap: 8px; font-size: .8rem; opacity: .95; }
.item-pasta label { margin: 0; width: auto; white-space: nowrap; }
.item-pasta select { flex: 1; min-width: 0; max-width: 240px; }
.vazio-lista { text-align: center; opacity: .65; padding: 18px 8px; font-size: .9rem; margin: 0; }

/* ===== Botões de acesso (Talentos, Anotações, Técnicas) ===== */
/* Maldição, Talentos, Anotações, Técnicas e Rolagem numa só grade (3 em cima, 2 embaixo).
   As divs continuam existindo para o recolher/expandir; display:contents deixa os botões na grade. */
.acessos-grade {
    display: grid; grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 10px; width: 100%; margin: 10px 0 14px;
}
.acessos-grade > div:not(.oculto) { display: contents; }
.acessos-grade .button {
    grid-column: span 2; width: 100% !important; margin: 0 !important;
    min-height: 52px; white-space: normal; text-align: center;
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
}
/* Desktop (6 col): 3 botões em cima, depois duplas (Arte e Artes Marciais, Anotações e Dinheiro), Rolagem na linha inteira. */
.acessos-grade #talentos-arte-abrir,
.acessos-grade #artes-marciais-abrir,
.acessos-grade #anotacoes-abrir,
.acessos-grade #dinheiro-abrir { grid-column: span 3; }
.acessos-grade #ama-abrir { grid-column: 1 / -1; }
.acessos-grade #rolarD100 { grid-column: 1 / -1; }
@media (max-width: 560px) {
    .acessos-grade { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .acessos-grade .button,
    .acessos-grade #talentos-arte-abrir,
    .acessos-grade #artes-marciais-abrir,
    .acessos-grade #anotacoes-abrir,
    .acessos-grade #dinheiro-abrir,
    .acessos-grade #ama-abrir { grid-column: span 1; }
    /* 7 botões em 2 colunas: Maldição e Rolagem ocupam a linha inteira, sem buraco. */
    .acessos-grade #maldicao-adicao,
    .acessos-grade #rolarD100 { grid-column: 1 / -1; }
}
.contador-painel {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 22px; height: 22px; padding: 0 6px; border-radius: 999px;
    background: rgba(255, 255, 255, .16); font-size: .75rem;
}
.contador-painel:empty { display: none; }

/* ===== Nova técnica recolhível ===== */
.tecnica-novo {
    border: 1px solid rgba(255, 255, 255, .1); border-radius: 10px;
    padding: 0 14px; margin: 12px 0; background: rgba(0, 0, 0, .15);
}
.tecnica-novo > summary {
    cursor: pointer; padding: 12px 0; font-weight: 600; letter-spacing: .04em; list-style: none;
}
.tecnica-novo > summary::-webkit-details-marker { display: none; }
.tecnica-novo > summary::before { content: "＋ "; }
.tecnica-novo[open] > summary::before { content: "－ "; }

/* ===== Voltar ao topo ===== */
.voltar-topo {
    position: fixed; right: 16px; bottom: 64px; width: 42px; height: 42px; border-radius: 50%;
    background: rgba(139, 26, 26, .92); color: #fff; border: 1px solid rgba(255, 255, 255, .2);
    font-size: 1.2rem; cursor: pointer; opacity: 0; pointer-events: none; z-index: 900;
    transition: opacity .2s ease, transform .15s ease;
}
.voltar-topo.visivel { opacity: 1; pointer-events: auto; }
.voltar-topo:hover { transform: translateY(-2px); }

/* ===== Itens e Ações ===== */
#itens-conteudo .item-form-linha {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 18px;
}
.item-form-largo       { grid-column: 1 / -1; }
.item-form-meio        { grid-column: span 3; }
.item-form-qtd,
.item-form-dado,
.item-form-valor-fixo  { grid-column: span 2; }
.item-form-grupo {
    margin: 0 0 18px; padding: 14px 16px 0; min-width: 0;
    border: 1px solid rgba(255, 255, 255, .10); border-radius: 10px; background: rgba(0, 0, 0, .15);
}
.item-form-grupo legend { padding: 0 8px; font-size: .78rem; letter-spacing: .08em; text-transform: uppercase; opacity: .75; }
.item-form-grupo .item-form-linha { margin-bottom: 16px !important; }

.item-form-campo { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.item-form-campo label { font-size: .72rem; letter-spacing: .6px; }
.item-form-campo input,
.item-form-campo select { width: 100%; height: 44px; box-sizing: border-box; }

#itens-conteudo input[type="number"] { -moz-appearance: textfield; appearance: textfield; }
#itens-conteudo input[type="number"]::-webkit-outer-spin-button,
#itens-conteudo input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

#itens-conteudo .campo-descricao { margin-bottom: 18px; }
#itens-conteudo .campo-descricao label { display: block; margin-bottom: 8px; }
#itens-conteudo .campo-descricao textarea {
    width: 100%;
    min-height: 90px;
    box-sizing: border-box;
    resize: vertical;
}

.itens-botoes {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 6px;
}
.itens-botoes button { width: 100%; margin: 0; }

.rolar-preview-resultado { margin-top: 12px; }
.rolar-preview-resultado:empty { display: none; }

/* ===== Itens adicionados ===== */
#lista-itens { display: flex; flex-direction: column; gap: 12px; margin-top: 10px; }

#lista-itens .item-card {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 14px 16px;
    background: rgba(0, 0, 0, .25);
    border: 1px solid rgba(255, 255, 255, .10);
    border-radius: 10px;
}

#lista-itens .item-card-topo {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px 10px;
}
#lista-itens .item-card-nome {
    flex: 1 1 auto;
    min-width: 0;
    font-size: 1.05rem;
    font-weight: 600;
    line-height: 1.3;
    overflow-wrap: anywhere;
}
#lista-itens .item-tag { flex: none; }
#lista-itens .item-card-botoes { display: flex; gap: 6px; margin-left: auto; flex: none; }
#lista-itens .botao-remover-item,
#lista-itens .botao-editar-item {
    flex: none;
    width: auto;
    margin: 0;
    padding: 4px 10px;
    font-size: .75rem;
}
#lista-itens .botao-editar-item {
    background: transparent; border: 1px solid rgba(255, 255, 255, .25); border-radius: 6px; color: var(--tinta);
    font-family: var(--fonte-titulo); letter-spacing: .5px; text-transform: uppercase; font-weight: 600; cursor: pointer;
}
#lista-itens .botao-editar-item:hover { background: rgba(255, 255, 255, .08); }
#itens-conteudo .campo-descricao { flex-direction: column; align-items: stretch; }
#itens-conteudo .campo-descricao label { width: auto; white-space: nowrap; }
#lista-itens .item-card.editando { border-color: rgba(220, 70, 70, .8); box-shadow: 0 0 0 1px rgba(220, 70, 70, .4); }

/* características do item (Dano, Crítico, Tipo de dano...) em grade de rótulo/valor */
#lista-itens .item-card-dados {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 10px 16px;
    margin: 0; padding-top: 10px; border-top: 1px solid rgba(255, 255, 255, .08);
}
#lista-itens .item-card-dados dt { font-size: .7rem; letter-spacing: .06em; text-transform: uppercase; opacity: .6; }
#lista-itens .item-card-dados dd { margin: 2px 0 0; font-size: .95rem; font-weight: 600; overflow-wrap: anywhere; }
#lista-itens .item-card-efeito { padding-top: 8px; border-top: 1px solid rgba(255, 255, 255, .08); }
#lista-itens .item-card-efeito p { margin: 4px 0 0; font-size: .92rem; line-height: 1.55; white-space: pre-line; overflow-wrap: anywhere; }
#lista-itens .item-card-descricao { white-space: pre-line; overflow-wrap: anywhere; }

#lista-itens .item-card-dano { font-size: .95rem; }
#lista-itens .item-rotulo,
#lista-itens .item-resultado-rotulo {
    margin-right: 6px;
    font-size: .72rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    opacity: .6;
}
#lista-itens .item-dano-valor { font-weight: 600; font-variant-numeric: tabular-nums; }

#lista-itens .item-card-descricao,
#lista-itens .item-card-modificacoes {
    padding-top: 8px;
    font-size: .9rem;
    line-height: 1.5;
    opacity: .85;
    border-top: 1px solid rgba(255, 255, 255, .08);
}

#lista-itens .item-card-rodape {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding-top: 10px;
    border-top: 1px solid rgba(255, 255, 255, .08);
}
#lista-itens .botao-rolar-item { flex: none; margin: 0; }
#lista-itens .item-card-resultado {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
#lista-itens .item-card-resultado:empty { display: none; }
#lista-itens .item-resultado-total {
    font-size: 1.5rem;
    line-height: 1.1;
    color: #ff6b6b;
    font-variant-numeric: tabular-nums;
}
#lista-itens .item-resultado-detalhe {
    max-height: 4.2em;
    overflow-y: auto;
    font-size: .8rem;
    line-height: 1.4;
    color: rgba(255, 255, 255, .65);
    overflow-wrap: anywhere;
}

@media (max-width: 520px) {
    #lista-itens .item-card-rodape { flex-direction: column; }
    #lista-itens .botao-rolar-item { width: 100%; }
}

/* ===== Botões dos popups (Maldição e Re-roll) ===== */
.btn-popup-card {
    display: flex !important;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    width: 60%;
    min-width: 0;
    margin: 6px auto;
    padding: 16px 11px;
    gap: 8px;
    box-sizing: border-box;
    background: #1f1212;
    border: 1px solid var(--linha, #444);
    border-radius: 14px;
    transition: .3s;
}

/* ===== Ações finais da ficha ===== */
.ficha-acoes {
    display: flex;
    gap: 12px;
    margin-top: 28px;
    padding-top: 20px;
    border-top: 1px solid var(--linha, #444);
}
.ficha-acoes .button { flex: 1; margin: 0; min-height: 48px; }
.ficha-acoes .button[type="submit"] { flex: 2; }
.ficha-acoes .button-secundario {
    background: transparent;
    border: 1px dashed var(--linha, #444);
    color: var(--tinta-fraca, #aaa);
}
.ficha-acoes .button-secundario:hover { color: var(--tinta, #eee); }

@media (max-width: 560px) {
    #itens-conteudo .item-form-linha { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .item-form-meio                       { grid-column: 1 / -1; }
    .item-form-qtd, .item-form-dado       { grid-column: span 1; }
    .item-form-valor-fixo                 { grid-column: 1 / -1; }
    .itens-botoes { grid-template-columns: 1fr; }
    .ficha-acoes { flex-direction: column-reverse; }
}

/* ===== Técnicas ===== */
#tecnicas-card { max-height: 85vh !important; overflow-y: auto; padding-bottom: 20px; }
.tecnica-form { display: flex; flex-direction: column; gap: 12px; margin: 16px 0; }
/* O .campo global coloca rótulo e input lado a lado; aqui empilhamos (rótulo em cima, campo embaixo, largura total) */
.tecnica-form .campo {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 6px;
    width: 100%;
    min-width: 0;
    margin: 0;
    box-sizing: border-box;
}
.tecnica-form .campo > label {
    width: auto;
    min-width: 0;
    margin: 0;
    text-align: left;
    white-space: normal;
}
.tecnica-form .campo input,
.tecnica-form .campo textarea {
    width: 100% !important;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}
.tecnica-form .campo input { height: 44px; }
.tecnica-form .campo textarea { min-height: 80px; max-height: 240px; resize: vertical; overflow-y: auto; }
/* tira as setinhas do number, que cortavam o valor */
.tecnica-form input[type="number"] { -moz-appearance: textfield; appearance: textfield; }
.tecnica-form input[type="number"]::-webkit-outer-spin-button,
.tecnica-form input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.tecnica-form-linha { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; align-items: end; }
.tecnica-aprim-linha { display: grid; grid-template-columns: minmax(0, 1fr) 90px 34px; gap: 8px; margin-bottom: 8px; align-items: center; }
.tecnica-aprim-linha input[type="text"], .tecnica-aprim-linha input[type="number"] { height: 40px; box-sizing: border-box; width: 100%; min-width: 0; }
.tecnica-aprim-linha .aprim-acum { grid-column: 1 / -1; display: inline-flex; align-items: center; gap: 6px; margin: 0; width: auto; font-size: .82rem; opacity: .85; cursor: pointer; }
.tecnica-aprim-linha .aprim-acum input { width: 16px; height: 16px; margin: 0; accent-color: #8b1a1a; }

/* aprimoramentos: personalizados + gerais, com quantidade (acumulativos) e usos grátis */
.aprim-bloco .aprim-gerais-titulo { margin: 10px 0 2px; font-size: .72rem; letter-spacing: .06em; text-transform: uppercase; opacity: .6; }
.tecnica-aprim-item.aprim-linha { flex-wrap: wrap; row-gap: 4px; cursor: default; }
.aprim-linha .aprim-nome { flex: 1 1 150px; min-width: 0; display: flex; align-items: center; gap: 8px; margin: 0; width: auto; overflow-wrap: anywhere; cursor: pointer; }
.aprim-linha .tec-aprim-custo { margin-left: 0; font-size: .82rem; }
.aprim-passo { display: inline-flex; align-items: center; gap: 4px; font-size: .78rem; white-space: nowrap; }
.aprim-passo b { min-width: 22px; text-align: center; font-variant-numeric: tabular-nums; }
.aprim-passo .aprim-btn {
    width: 28px; height: 28px; padding: 0; line-height: 1; font-size: 1rem; cursor: pointer;
    background: rgba(0, 0, 0, .35); color: inherit; border: 1px solid rgba(255, 255, 255, .14); border-radius: 6px;
}
.aprim-passo .aprim-btn:hover:not(:disabled) { border-color: rgba(220, 70, 70, .8); background: rgba(139, 26, 26, .55); }
.aprim-passo .aprim-btn:disabled { opacity: .35; cursor: not-allowed; }
.aprim-passo.aprim-gratis { opacity: .9; color: #ffb4b4; }
.aprim-gratis-label input { width: 60px; }
.aprim-usados { font-size: .75rem; opacity: .7; }
@media (max-width: 560px) { .aprim-linha .aprim-nome { flex-basis: 100%; } }
#tec-add-aprimoramento, #adicionar-tecnica { width: 100%; }
#lista-tecnicas { display: flex; flex-direction: column; gap: 14px; margin-top: 18px; }
.tecnica-card { padding: 14px 16px; background: rgba(0,0,0,.25); border: 1px solid rgba(255,255,255,.10); border-radius: 10px; display: flex; flex-direction: column; gap: 8px; }
.tecnica-card h4 { margin: 0; font-size: 1.1rem; overflow-wrap: anywhere; }
.tecnica-card dl { margin: 0; display: grid; gap: 6px; }
.tecnica-card dt { font-size: .72rem; letter-spacing: .06em; text-transform: uppercase; opacity: .6; }
.tecnica-card dd { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; }
.tecnica-aprim-item { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-top: 1px solid rgba(255,255,255,.08); }
.tecnica-aprim-item span.tec-aprim-custo { margin-left: auto; font-weight: 600; white-space: nowrap; }
/* checkbox própria dos aprimoramentos (o controle nativo não combina com o tema escuro) */
.tecnica-aprim-item { cursor: pointer; }
.tecnica-aprim-item .tec-aprim-check {
    appearance: none; -webkit-appearance: none;
    flex: 0 0 auto; width: 20px; height: 20px; margin: 0;
    display: inline-grid; place-content: center;
    border: 2px solid rgba(255, 255, 255, .35); border-radius: 5px;
    background: rgba(0, 0, 0, .35); cursor: pointer;
    transition: background .15s ease, border-color .15s ease, box-shadow .15s ease;
}
.tecnica-aprim-item .tec-aprim-check::before {
    content: ""; width: 11px; height: 11px; background: #fff;
    clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
    transform: scale(0); transition: transform .12s ease;
}
.tecnica-aprim-item .tec-aprim-check:hover { border-color: rgba(220, 70, 70, .8); }
.tecnica-aprim-item .tec-aprim-check:checked { background: #8b1a1a; border-color: #d24a4a; box-shadow: 0 0 0 3px rgba(179, 32, 31, .22); }
.tecnica-aprim-item .tec-aprim-check:checked::before { transform: scale(1); }
.tecnica-aprim-item .tec-aprim-check:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
.tecnica-aprim-item:has(.tec-aprim-check:checked) .tec-aprim-custo { color: #ff9b9b; }
.tecnica-rodape { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding-top: 10px; border-top: 1px solid rgba(255,255,255,.08); }
.tecnica-total { margin-right: auto; font-weight: 600; }
.tecnica-eficiencia { display: inline-flex; align-items: center; gap: 6px; font-size: .85rem; white-space: nowrap; }
.tecnica-eficiencia input { width: 72px; height: 34px; box-sizing: border-box; text-align: center; -moz-appearance: textfield; appearance: textfield; }
@media (max-width: 560px) { .tecnica-form-linha { grid-template-columns: 1fr; } }

/* ===== Descanso curto / longo ===== */
.descanso-barra { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin: 6px 0 12px; width: 100%; }
.descanso-barra .button { flex: 1 1 180px; margin: 0 !important; }
.inspiracao-caixa {
    display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%;
    margin: 4px 0 12px; padding: 8px 12px; border-radius: 10px;
    border: 1px solid rgba(234, 179, 8, .45); background: rgba(234, 179, 8, .08);
}
.inspiracao-controle { display: flex; align-items: center; gap: 10px; }
.inspiracao-btn {
    width: 34px; height: 34px; border-radius: 50%; border: 1px solid rgba(234, 179, 8, .6);
    background: rgba(0, 0, 0, .25); color: #facc15; font-size: 1.2rem; line-height: 1; cursor: pointer;
}
.inspiracao-btn:hover:not(:disabled) { background: rgba(234, 179, 8, .2); }
.inspiracao-btn:disabled { opacity: .4; cursor: default; }
.inspiracao-valor { min-width: 2ch; text-align: center; font-size: 1.3rem; font-weight: 700; color: #facc15; }

/* ===== Pontos de habilidade disponíveis (modo "ficha já existente") ===== */
.pontos-livre {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 8px;
    margin: 4px auto 12px; padding: 8px 12px; max-width: 520px;
    border: 1px dashed rgba(220, 70, 70, .5); border-radius: 10px; background: rgba(139, 26, 26, .12); font-size: .85rem;
}
.pontos-livre label { margin: 0; width: auto; white-space: nowrap; }
.pontos-livre input { width: 96px; text-align: center; }
.pontos-livre .button { min-height: 38px; padding: 0 14px; }
.pontos-livre small { flex-basis: 100%; opacity: .7; line-height: 1.3; }

/* ===== Mods ajustados à mão (modo "ficha já existente") ===== */
/* mods ajustados à mão ficam com a mesma aparência dos automáticos; só o cursor muda no modo livre */
#form-ficha .mod.mod-editavel { cursor: text; }

/* ===== Painéis flutuantes redimensionáveis (Talentos, Técnicas, Anotações, Maldições) ===== */
/* a altura máxima desconta a posição do topo (120px no desktop, 70px no celular), para o fim do painel
   e a alça de redimensionar ficarem sempre dentro da tela */
#form-ficha .card-rolagem.redimensionavel {
    resize: both; overflow: auto;
    min-width: 280px; min-height: 240px;
    max-width: 96vw; max-height: calc(100vh - 136px) !important;
}
#form-ficha .card-talentos.redimensionavel { right: auto; width: clamp(380px, 38vw, 640px); }
/* na pilha da esquerda os painéis já abrem grandes e crescem com o conteúdo: só a coluna rola (sem rolagem dentro de
   rolagem). Quem arrastar o canto para mudar a altura passa a ter rolagem própria naquele painel. */
#paineis-pilha > .card-rolagem.redimensionavel { min-height: min(58vh, 560px); max-height: none !important; }
@media (max-width: 600px) {
    #form-ficha .card-rolagem.redimensionavel { max-height: calc(100vh - 86px) !important; }
}

/* ===== Leitura dentro dos painéis (Talentos, Técnicas, Rolagem...) ===== */
/* texto maior e mais espaçado; caixas de texto crescem com o conteúdo (ver "autoAltura" no script da pilha) */
#form-ficha #card-rolagem { width: clamp(300px, 22vw, 380px); }
#paineis-pilha > .card-rolagem, #form-ficha #card-rolagem { font-size: 15px; line-height: 1.55; padding: 22px 22px 24px; }
#paineis-pilha textarea {
    font-size: .95rem; line-height: 1.6; min-height: 140px; padding: 10px 12px;
    resize: vertical; overflow-y: auto;
}
#paineis-pilha input[type="text"], #paineis-pilha input[type="number"], #paineis-pilha select { min-height: 40px; font-size: .92rem; }
#paineis-pilha label { font-size: .8rem; letter-spacing: .3px; }
#paineis-pilha h4 { font-size: 1.05rem; line-height: 1.35; overflow-wrap: anywhere; }
#paineis-pilha dl dt { font-size: .74rem; letter-spacing: .6px; text-transform: uppercase; opacity: .7; margin-top: 10px; }
#paineis-pilha dl dd { margin: 2px 0 0; font-size: .95rem; line-height: 1.6; white-space: pre-line; overflow-wrap: anywhere; }
#paineis-pilha button { min-height: 36px; }
#paineis-pilha .botao-fechar { min-height: 0; }

/* ===== Aprimoramentos: linhas claras e aba de guardados no painel Técnicas ===== */
#tecnicas-card[data-aba-atual="aprimoramentos"] > #pastas-tecnicas,
#tecnicas-card[data-aba-atual="aprimoramentos"] > #tecnica-novo,
#tecnicas-card[data-aba-atual="aprimoramentos"] > #lista-tecnicas,
#tecnicas-card[data-aba-atual="aprimoramentos"] > #tecnicas-vazio { display: none !important; }
.aprim-ajuda { font-size: .82rem; line-height: 1.5; opacity: .75; margin: 2px 0 8px; }
.aprim-linha { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px 12px; padding: 8px 6px; border-radius: 8px; }
.aprim-linha.aprim-em-uso { background: rgba(220, 70, 70, .10); }
.aprim-linha .aprim-nome { display: flex; align-items: flex-start; gap: 8px; flex: 1 1 200px; min-width: 0; }
.aprim-linha .aprim-nome > span { display: flex; flex-direction: column; gap: 2px; overflow-wrap: anywhere; }
.aprim-linha .aprim-unit { font-size: .74rem; opacity: .65; }
.aprim-linha .aprim-controles { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-left: auto; }
.aprim-linha .aprim-passo { display: inline-flex; align-items: center; gap: 4px; }
.aprim-linha .aprim-passo-rotulo { font-size: .7rem; letter-spacing: .05em; text-transform: uppercase; opacity: .7; }
.aprim-linha .aprim-subtotal { min-width: 3.2em; text-align: right; font-weight: 700; font-variant-numeric: tabular-nums; }
.aprim-gerais-titulo { margin-top: 10px; padding-top: 8px; border-top: 1px solid rgba(255, 255, 255, .08); font-size: .74rem; letter-spacing: .06em; text-transform: uppercase; opacity: .7; }
.aprim-guardados #aprim-guardados-lista { display: flex; flex-direction: column; gap: 10px; margin-bottom: 14px; }
.aprim-guardado { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px 10px; padding: 10px 12px; border: 1px solid rgba(255, 255, 255, .10); border-radius: 10px; background: rgba(0, 0, 0, .2); }
.aprim-guardado input[type="text"] { width: 100%; box-sizing: border-box; }
.aprim-guardado .aprim-guardado-linha { grid-column: 1 / -1; }
.aprim-guardado-linha { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) auto; gap: 8px; align-items: end; }
.aprim-guardado-linha select, .aprim-guardado-linha input { width: 100%; box-sizing: border-box; }
.aprim-guardado-novo { display: flex; flex-direction: column; gap: 10px; padding: 12px; border: 1px dashed rgba(255, 255, 255, .2); border-radius: 10px; }
.aprim-guardado-novo h4 { margin: 0; }
.aprim-guardado-novo .aprim-guardado-linha { grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); }
.tecnica-card.editando { border-color: rgba(220, 70, 70, .8) !important; }

/* ===== Ficha empurrada para o lado quando há painel aberto (telas largas) ===== */
/* o script da pilha mede os painéis e grava --empurra-esq / --empurra-dir no .container da ficha */
.container.ficha-empurrada { transition: padding .2s ease; box-sizing: border-box;
    padding-left: var(--empurra-esq, 0px); padding-right: var(--empurra-dir, 0px); }
.container.ficha-empurrada .ficha-card { min-width: 0; }

/* ===== Artes marciais ===== */
.am-abas { display: flex; flex-wrap: wrap; gap: 6px; margin: 4px 0 10px; }
.am-aba { min-height: 34px; padding: 0 10px; font-size: .82rem; white-space: nowrap; }
.am-aba .am-aba-nivel { opacity: .75; font-size: .75rem; margin-left: 4px; }
.am-corpo { display: flex; flex-direction: column; gap: 12px; }
.am-linha { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.am-linha label { font-size: .85rem; }
.am-linha input[type=number] { width: 90px; }
.am-xp { background: rgba(0, 0, 0, .25); border: 1px solid rgba(255, 255, 255, .10); border-radius: 10px; padding: 10px 12px; display: flex; flex-direction: column; gap: 8px; }
.am-barra { position: relative; height: 18px; border-radius: 999px; background: rgba(255, 255, 255, .08); border: 1px solid rgba(255, 255, 255, .12); overflow: hidden; }
.am-barra-fill { position: absolute; inset: 0 auto 0 0; width: 0; background: linear-gradient(90deg, #6e1414, #b62525); transition: width .2s; }
.am-barra-texto { position: relative; display: block; text-align: center; font-size: .78rem; line-height: 16px; }
.am-xp-regra { font-size: .75rem; opacity: .7; margin: 0; overflow-wrap: anywhere; }
.am-subir[disabled] { opacity: .4; cursor: default; }
.am-talentos { display: flex; flex-direction: column; gap: 8px; }
.am-talentos-topo { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.am-talentos-topo h4 { margin: 0; font-size: .95rem; }
.am-talentos-resumo { font-size: .8rem; opacity: .75; }
.am-talento { background: #1c1010; border: 1px solid rgba(255, 255, 255, .10); border-radius: 10px; padding: 8px 10px; display: grid; grid-template-columns: minmax(0, 1fr) 90px; gap: 6px 8px; }
.am-talento.ativo { border-color: rgba(220, 70, 70, .75); }
.am-talento label { display: flex; flex-direction: column; gap: 2px; font-size: .75rem; opacity: .9; min-width: 0; }
.am-talento label.larga { grid-column: 1 / -1; }
.am-talento input, .am-talento textarea { width: 100%; box-sizing: border-box; min-width: 0; }
.am-talento-acoes { grid-column: 1 / -1; display: flex; gap: 8px; flex-wrap: wrap; }

/* Lista de talentos do livro (config/talentos_livro.php): pesquisar e adicionar já preenchido */
.livro-tal { background: rgba(0, 0, 0, .25); border: 1px solid rgba(255, 255, 255, .10); border-radius: 10px; padding: 0 12px; margin-top: 10px; }
.livro-tal > summary { cursor: pointer; list-style: none; display: flex; flex-wrap: wrap; align-items: center; gap: 2px 8px; min-height: 42px; font-weight: 600; }
.livro-tal > summary::-webkit-details-marker { display: none; }
.livro-tal > summary::before { content: "▸"; opacity: .7; font-size: .8rem; }
.livro-tal[open] > summary::before { content: "▾"; }
.livro-tal .prop-qtd { opacity: .65; font-size: .78rem; font-weight: 400; margin-left: auto; }
.livro-tal-busca { width: 100%; height: 40px; box-sizing: border-box; margin-bottom: 6px; }
.livro-tal-lista { max-height: 45vh; overflow-y: auto; margin: 0 -4px 10px; padding: 0 4px; }
.livro-tal-item .livro-tal-desc { white-space: pre-line; cursor: pointer; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.livro-tal-item.aberto .livro-tal-desc { display: block; }
.livro-tal-item.adicionado { background: rgba(70, 160, 90, .12); }

/* ===== Pilha dos painéis da esquerda: abertos ficam um embaixo do outro, na ordem em que foram abertos ===== */
/* a coluna não captura cliques no espaço vazio; só os painéis abertos recebem o mouse */
#paineis-pilha {
    position: fixed; left: 40px; top: 100px; bottom: 16px; z-index: 900;
    display: flex; flex-direction: column; align-items: flex-start; gap: 14px;
    width: max-content; max-width: calc(100vw - 80px);
    overflow-y: auto; overscroll-behavior: contain;
    pointer-events: none;
}
#paineis-pilha:not(:has(> .card-rolagem.ativo)) { display: none; }
#paineis-pilha > .card-rolagem {
    position: relative; left: auto; right: auto; top: auto; z-index: auto;
    flex-shrink: 0; margin: 0;
    max-width: calc(100vw - 80px);
    pointer-events: auto;
}
#paineis-pilha > .card-rolagem:not(.ativo) { display: none; }
#paineis-pilha > .card-rolagem.ativo { animation: painel-entrar .2s ease; }
@keyframes painel-entrar { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: none; } }
@media (max-width: 700px) {
    #paineis-pilha { left: 12px; top: 70px; max-width: calc(100vw - 24px); }
    #paineis-pilha > .card-rolagem { max-width: calc(100vw - 24px); }
}

/* ===== Somente leitura (mestre vendo a ficha de um jogador) ===== */
.ficha-somente-leitura {
    display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 14px;
    margin: 0 0 14px; padding: 10px 14px; font-size: .92rem;
    border: 1px solid rgba(220, 70, 70, .5); border-radius: 10px; background: rgba(139, 26, 26, .14);
}
.ficha-somente-leitura .button { min-height: 38px; padding: 0 16px; margin: 0; }
#form-ficha.somente-leitura .modo-livre,
#form-ficha.somente-leitura .ficha-acoes,
#form-ficha.somente-leitura .descanso-barra,
#form-ficha.somente-leitura .pontos-livre,
#form-ficha.somente-leitura .botao-editar-tecnica,
#form-ficha.somente-leitura .botao-editar-item,
#form-ficha.somente-leitura .aprim-guardado-apagar { display: none !important; }
#form-ficha.somente-leitura input:disabled,
#form-ficha.somente-leitura select:disabled,
#form-ficha.somente-leitura textarea:disabled,
#form-ficha.somente-leitura button:disabled { opacity: .85; cursor: default; }

/* ===== Modo "ficha já existente" (sem verificação de pontos) ===== */
.modo-livre {
    display: flex; align-items: center; gap: 10px; margin: 0 0 14px; padding: 10px 14px;
    border: 1px dashed rgba(220, 70, 70, .5); border-radius: 10px; background: rgba(0, 0, 0, .2);
    cursor: pointer; font-size: .9rem; line-height: 1.3; text-align: left;
}
.modo-livre input[type="checkbox"] {
    appearance: none; -webkit-appearance: none;
    flex: 0 0 auto; width: 22px; height: 22px; margin: 0;
    display: inline-grid; place-content: center;
    border: 2px solid rgba(255, 255, 255, .35); border-radius: 6px;
    background: rgba(0, 0, 0, .35); cursor: pointer;
    transition: background .15s ease, border-color .15s ease, box-shadow .15s ease;
}
.modo-livre input[type="checkbox"]::before {
    content: ""; width: 12px; height: 12px; background: #fff;
    clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
    transform: scale(0); transition: transform .12s ease;
}
.modo-livre:hover input[type="checkbox"] { border-color: rgba(220, 70, 70, .8); }
.modo-livre input[type="checkbox"]:checked { background: #8b1a1a; border-color: #d24a4a; box-shadow: 0 0 0 3px rgba(179, 32, 31, .22); }
.modo-livre input[type="checkbox"]:checked::before { transform: scale(1); }
.modo-livre input[type="checkbox"]:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
.modo-livre.ativo { border-style: solid; background: rgba(139, 26, 26, .18); }
.modo-livre small { display: block; opacity: .7; font-size: .78rem; }
</style>
<div class="container">
    <div class="card ficha-card">

        <div class="ficha-header">
            <span class="selo">△</span>
            <h1>{{ $somenteLeitura ? 'Ficha do Agente' : ($editando ? 'Editar Ficha do Agente' : 'Criar Ficha do Agente') }}</h1>
            <p class="ficha-subtitulo">Registro Confidencial</p>
        </div>
        @if ($somenteLeitura)
            <div class="ficha-somente-leitura">
                <a href="{{ $voltarUrl ?? route('campanhas.index') }}" class="button">← Voltar</a>
                <span>Ficha de <strong>{{ $donoNome ?? 'jogador' }}</strong> · somente leitura</span>
            </div>
        @endif

        <hr>

    <form id="form-ficha" action="{{ $somenteLeitura ? '#' : ($editando ? route('personagens.update', $personagem->id) : route('fichafeita.store')) }}" method="POST" @if ($somenteLeitura) class="somente-leitura" data-somente-leitura="1" @endif>
        @csrf
        @if($editando)
            @method('PUT')
        @endif
        <div class="ficha-condicoes-topo" id="ficha-condicoes-topo" role="status" hidden></div>

        {{-- Modo "ficha já existente": permite digitar tudo sem o controle de pontos. Não é salvo no banco (fica lembrado no navegador). --}}
        <label class="modo-livre" title="Desliga a verificação de pontos de atributos, habilidades e nível. Útil para registrar uma ficha que já existe no papel.">
            <input type="checkbox" id="modo-livre">
            <span><strong>Ficha já existente</strong> — preencher sem verificação de pontos
                <small>Atributos, habilidades, BE, sorte e nível aceitam qualquer valor. Os pontos continuam sendo exibidos, mas não bloqueiam.</small></span>
        </label>

        @include('partials.anotacoes')
        @include('partials.dinheiro')
        @include('partials.ama')
        
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
                <input type="hidden" id="niveis_profissao" name="niveis_profissao" value="">
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
                    <input id="be" name="be" type="number" style="width:40%" value="{{ old('be', $personagem->be ?? '') }}" placeholder="Digite o BE do agente" min="0" title="Pode ser ajustado à mão; não mexe nos pontos já gastos em habilidades">
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
              {{-- Abas: Vitais (conteúdo original), Armaduras e Resistências. O pai (id vitais-abas) guarda a aba no localStorage. --}}
              <div id="vitais-abas">
                <div class="abas-painel" role="tablist">
                    <button type="button" class="aba-painel ativo" data-aba="vitais">Vitais</button>
                    <button type="button" class="aba-painel" data-aba="armaduras">Armaduras</button>
                    <button type="button" class="aba-painel" data-aba="resistencias">Resistências</button>
                </div>
              <div class="aba-conteudo ativo" data-aba="vitais">

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

                {{-- Inspiração: contador gastável; − gasta uma, + ganha uma (nunca abaixo de zero) --}}
                <div class="inspiracao-caixa">
                    <span class="barra-nome">Inspiração</span>
                    <div class="inspiracao-controle">
                        <button type="button" class="inspiracao-btn" data-insp="-1" aria-label="Gastar uma inspiração" title="Gastar uma">−</button>
                        <span class="inspiracao-valor" id="inspiracao-valor" aria-live="polite">{{ (int) ($personagem->inspiracao ?? 0) }}</span>
                        <button type="button" class="inspiracao-btn" data-insp="1" aria-label="Ganhar uma inspiração" title="Ganhar uma">+</button>
                    </div>
                </div>
                <input type="hidden" id="inspiracao" name="inspiracao" value="{{ (int) ($personagem->inspiracao ?? 0) }}">
                <script>
                (function(){
                    // − / + da Inspiração: grava no campo oculto e dispara "input" para o salvamento automático
                    const campo = document.getElementById("inspiracao");
                    const valor = document.getElementById("inspiracao-valor");
                    const botoes = document.querySelectorAll(".inspiracao-btn");
                    const leitura = {{ $somenteLeitura ? 'true' : 'false' }};
                    function mostrar(n){
                        valor.textContent = n;
                        botoes.forEach(b => { b.disabled = leitura || (b.dataset.insp === "-1" && n <= 0) || (b.dataset.insp === "1" && n >= 999); });
                    }
                    botoes.forEach(b => b.addEventListener("click", function(){
                        if(leitura) return;
                        const n = Math.max(0, Math.min(999, (parseInt(campo.value, 10) || 0) + parseInt(b.dataset.insp, 10)));
                        campo.value = n;
                        mostrar(n);
                        campo.dispatchEvent(new Event("input", { bubbles: true }));
                    }));
                    mostrar(parseInt(campo.value, 10) || 0);
                })();
                </script>

                {{-- Descanso: recupera Vida, Sanidade e Aura conforme as regras rápidas de descanso --}}
                <div class="descanso-barra">
                    <button type="button" class="button descanso-btn" id="descanso-curto" title="1–2 horas: Vida +1, Sanidade +1d2, Aura +1d20"><x-icone n="lua"/> Descanso curto</button>
                    <button type="button" class="button descanso-btn" id="descanso-longo" title="6–8 horas: Vida +1d3, Sanidade +1d4, Aura +4d20"><x-icone n="cama"/> Descanso longo</button>
                </div>
            
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

              <div class="aba-conteudo" data-aba="armaduras">
                <div class="arm-natural">
                    <label for="armadura-natural">Armadura natural</label>
                    <input type="number" id="armadura-natural" min="0" step="1" value="0" title="Reduz impacto, cortante e perfurante (ex.: propriedade Duro)">
                    <small class="arm-dica">reduz impacto, cortante e perfurante</small>
                </div>
                <div class="arm-lista" id="armadura-lista"></div>
                <button type="button" class="botao-adicionar-talento" id="armadura-nova">+ Nova peça</button>
                <p class="arm-aviso" id="armadura-aviso" style="display:none"></p>
                <div class="arm-resumo" id="armadura-resumo"></div>
                <div class="arm-rolar">
                    <button type="button" class="button" id="armadura-rolar-local">Rolar local (d100)</button>
                    <span id="armadura-rolar-res" class="arm-rolar-res"></span>
                </div>
                <div class="arm-dano">
                    <h4>Aplicar dano</h4>
                    <div class="arm-dano-linhas" id="armadura-dano-linhas"></div>
                    <button type="button" class="arm-mini" id="armadura-dano-mais">+ Tipo de dano</button>
                    <label class="arm-check"><input type="checkbox" id="armadura-dano-usar" checked> Usar resistências e imunidades</label>
                    <div class="arm-log" id="armadura-log"></div>
                    <button type="button" class="button" id="armadura-aplicar-dano">Aplicar dano</button>
                </div>
              </div>

              <div class="aba-conteudo" data-aba="resistencias">
                <h4 class="arm-grupo-titulo">Resistências (metade do dano)</h4>
                <div class="arm-chips" id="armadura-chips-res"></div>
                <h4 class="arm-grupo-titulo">Imunidades (dano zero)</h4>
                <div class="arm-chips" id="armadura-chips-imu"></div>
                <h4 class="arm-grupo-titulo">Fraquezas (dano dobrado)</h4>
                <div class="arm-chips" id="armadura-chips-fra"></div>
                <h4 class="arm-grupo-titulo">Condições</h4>
                <p class="arm-dica">Marque as condições em que o personagem está agora (aparecem também no topo da ficha).</p>
                <div class="arm-chips" id="armadura-chips-cond"></div>
                <label class="arm-grupo-titulo" for="armadura-cond-especiais">Condições especiais</label>
                <textarea id="armadura-cond-especiais" class="arm-cond-especiais" maxlength="1000" rows="3" placeholder="Ex.: maldito pelo Nomeador até o fim da cena; -10 em testes de força"></textarea>
              </div>
              <input type="hidden" id="armaduras" name="armaduras" value="">
              </div>
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
                <div class="pontos-extras">
                    <label for="pontos-extras-atributos" title="Pontos de atributo ganhos de fontes externas">Pontos extras</label>
                    <input id="pontos-extras-atributos" name="pontos_extras_atributos" type="text" inputmode="numeric"
                           placeholder="0" value="{{ $extraAtributos ?: '' }}">
                </div>
                <div class="atributos-grid">
                    <div class="atributo-card">
                        <span class="atributo-nome">Força</span>
                        <input type="text" class="atributo-valor" placeholder="0" id="forca" name="forca" value="{{ old('forca', $personagem->forca ?? '') }}">
                        <span class="atributo-nome">Mod</span>
                        <input type="text" class="mod" placeholder="0" id="mod-forca" name="mod_forca" value="{{ $modsManuais['forca'] ?? (isset($personagem->forca) ? $modDe((int)$personagem->forca) : '') }}" readonly title="Marque “Ficha já existente” para ajustar o mod à mão">
                    </div>
                    <div class="atributo-card" id="card-constituicao">
                        <span class="atributo-nome">Constituição</span>
                        <input type="text" class="atributo-valor" placeholder="0" id="constituicao" name="constituicao" value="{{ old('constituicao', $personagem->constituicao ?? '') }}">
                        <span class="atributo-nome">Mod</span>
                        <input type="text" class="mod" placeholder="0" id="mod-constituicao" name="mod_constituicao" value="{{ $modsManuais['constituicao'] ?? (isset($personagem->constituicao) ? $modDe((int)$personagem->constituicao) : '') }}" readonly title="Marque “Ficha já existente” para ajustar o mod à mão">
                    </div>
                    <div class="atributo-card">
                        <span class="atributo-nome">Destreza</span>
                        <input type="text" class="atributo-valor" placeholder="0" id="destreza" name="destreza" value="{{ old('destreza', $personagem->destreza ?? '') }}">
                        <span class="atributo-nome">Mod</span>
                        <input type="text" class="mod" placeholder="0" id="mod-destreza" name="mod_destreza" value="{{ $modsManuais['destreza'] ?? (isset($personagem->destreza) ? $modDe((int)$personagem->destreza) : '') }}" readonly title="Marque “Ficha já existente” para ajustar o mod à mão">
                    </div>
                    <div class="atributo-card">
                        <span class="atributo-nome">Conhecimento</span>
                        <input type="text" class="atributo-valor" placeholder="0" id="conhecimento" name="conhecimento" value="{{ old('conhecimento', $personagem->conhecimento ?? '') }}">
                        <span class="atributo-nome">Mod</span>
                        <input type="text" class="mod" placeholder="0" id="mod-conhecimento" name="mod_conhecimento" value="{{ $modsManuais['conhecimento'] ?? (isset($personagem->conhecimento) ? $modDe((int)$personagem->conhecimento) : '') }}" readonly title="Marque “Ficha já existente” para ajustar o mod à mão">
                    </div>
                    <div class="atributo-card">
                        <span class="atributo-nome">Carisma</span>
                        <input type="text" class="atributo-valor" placeholder="0" id="carisma" name="carisma" value="{{ old('carisma', $personagem->carisma ?? '') }}">
                        <span class="atributo-nome">Mod</span>
                        <input type="text" class="mod" placeholder="0" id="mod-carisma" name="mod_carisma" value="{{ $modsManuais['carisma'] ?? (isset($personagem->carisma) ? $modDe((int)$personagem->carisma) : '') }}" readonly title="Marque “Ficha já existente” para ajustar o mod à mão">
                    </div>
                    <div class="atributo-card">
                        <span class="atributo-nome">Percepção</span>
                        <input type="text" class="atributo-valor" placeholder="0" id="percepcao" name="percepcao" value="{{ old('percepcao', $personagem->percepcao ?? '') }}">
                        <span class="atributo-nome">Mod</span>
                        <input type="text" class="mod" placeholder="0" id="mod-percepcao" name="mod_percepcao" value="{{ $modsManuais['percepcao'] ?? (isset($personagem->percepcao) ? $modDe((int)$personagem->percepcao) : '') }}" readonly title="Marque “Ficha já existente” para ajustar o mod à mão">
                    </div>
                    <div class="atributo-card">
                        <span class="atributo-nome">Alma</span>
                        <input type="text" class="atributo-valor" placeholder="0" id="alma" name="alma" value="{{ old('alma', $personagem->alma ?? '') }}">
                        <span class="atributo-nome">Mod</span>
                        <input type="text" class="mod" placeholder="0" id="mod-alma" name="mod_alma" value="{{ $modsManuais['alma'] ?? (isset($personagem->alma) ? $modDe((int)$personagem->alma) : '') }}" readonly title="Marque “Ficha já existente” para ajustar o mod à mão">
                    </div>
                    <div class="atributo-card">
                        <span class="atributo-nome" id="nome-sorte" style="{{ isset($personagem->sorte) ? 'color: var(--tinta-fraca);' : 'color: rgba(189, 3, 3, 0.658);' }}">Sorte</span>
                        <input type="text" id="sorte" name="sorte" class="atributo-valor" placeholder="0" value="{{ old('sorte', $personagem->sorte ?? '') }}" title="Role ou digite a Sorte">
                    </div>
                    
                    <button class="button" style="width: 30%" id="espalhar-pontos" hidden>
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

            <div class="acessos-grade">
            <div class="ficha-header" id="maldicao-div">
                <button class="button" style="margin-top:8px;" id="maldicao-adicao">
                    Maldição
                </button>
            </div>

            <div class="ficha-header" id="acessos-div">
                <button type="button" class="button" style="margin-top:8px;" id="tecnicas-abrir">
                    Técnicas <span class="contador-painel" id="cont-tecnicas"></span>
                </button>
                <button type="button" class="button" style="margin-top:8px;" id="talentos-mundanos">
                    Talentos Mundanos <span class="contador-painel" id="cont-talentos"></span>
                </button>
                <button type="button" class="button" style="margin-top:8px;" id="talentos-arte-abrir" title="Técnicas de Hexomancia e de Fisiomancia">
                    Técnicas de arte <span class="contador-painel" id="cont-talentos-arte"></span>
                </button>
                <button type="button" class="button" style="margin-top:8px;" id="artes-marciais-abrir" title="Níveis, experiência e talentos de cada arte marcial">
                    Artes Marciais <span class="contador-painel" id="cont-artes-marciais"></span>
                </button>
                <button type="button" class="button" style="margin-top:8px;" id="anotacoes-abrir">
                    Anotações
                </button>
                <button type="button" class="button" style="margin-top:8px;" id="dinheiro-abrir" title="Dinheiro do personagem">
                    <x-icone n="moeda"/> Dinheiro
                </button>
                <button type="button" class="button" style="margin-top:8px;" id="ama-abrir" title="AMA: estudos (matérias, aulas, Currículo e Talentos de Grade), patente, salário e exércitos">
                    <x-icone n="capelo"/> AMA: estudos, patente e exércitos
                </button>
            </div>

            <div class="ficha-header" id="rolagem-setor-conteudo">
                <button class="button" id="rolarD100">
                    Rolagem
                </button>
            </div>
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

                <div class="historico-rolagens" id="historico-rolagens">
                    <div class="historico-rolagens-cab">
                        <strong>Histórico de rolagens</strong>
                        <button type="button" class="arm-mini" id="historico-rolagens-limpar" title="Apaga todo o histórico desta ficha neste aparelho">Limpar</button>
                    </div>
                    <ol class="historico-rolagens-lista" id="historico-rolagens-lista"></ol>
                    <p class="historico-rolagens-vazio" id="historico-rolagens-vazio">Nenhuma rolagem ainda. Dados, dano de itens, testes de habilidade e dano recebido aparecem aqui.</p>
                    <button type="button" class="arm-mini" id="historico-rolagens-mais" hidden>Ver mais</button>
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
                    <input type="hidden" id="pontos-habilidade-ajuste" name="pontos_habilidade_ajuste" value="{{ $ajusteHabilidades }}">
                    <input type="hidden" id="mods-atributos" name="mods_atributos" value="{{ json_encode((object) $modsManuais) }}">
                    {{-- Só no modo "ficha já existente": define direto quantos pontos ficam disponíveis (ajusta os pontos extras) --}}
                    <div class="pontos-livre" id="pontos-livre" hidden>
                        <label for="pontos-livre-valor">Definir pontos disponíveis</label>
                        <input id="pontos-livre-valor" type="number" inputmode="numeric" placeholder="0">
                        <button type="button" class="button" id="pontos-livre-aplicar">Aplicar</button>
                        <small>O valor digitado vira o saldo de "Pontos a distribuir". A diferença fica salva como ajuste da ficha e não mexe nos pontos extras.</small>
                    </div>
                    <input type="hidden" id="habilidades-extras" name="habilidades_extras" value="{{ json_encode((object) $habilidadesExtras) }}">
                    <p class="hab-extras-dica">Use o <b>+</b> ao lado de cada habilidade para valores extras livres (talentos, itens, bênçãos...): cada um com a origem, sem gastar pontos.</p>
                    <div class="pontos-extras">
                        <label for="pontos-extras-habilidades" title="Pontos de habilidade ganhos de fontes externas">Pontos extras</label>
                        <input id="pontos-extras-habilidades" name="pontos_extras_habilidades" type="text" inputmode="numeric"
                               placeholder="0" value="{{ $extraHabilidades ?: '' }}">
                    </div>
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
                {{-- Estrutura do item: nome, descrição, categoria, dano e as características de arma/armadura, depois os efeitos --}}
                <div class="item-form-linha">
                    <div class="item-form-campo item-form-largo">
                        <label for="item-nome">Nome do item</label>
                        <input id="item-nome" type="text" maxlength="255" placeholder="Nome do item ou ação">
                    </div>
                </div>

                <div class="campo campo-descricao">
                    <label for="item-descricao">Descrição</label>
                    <textarea id="item-descricao" placeholder="Descreva o item ou ação"></textarea>
                </div>

                <div class="item-form-linha">
                    <div class="item-form-campo item-form-largo">
                        <label for="item-categoria">Categoria</label>
                        <select id="item-categoria">
                            <option>Selecione</option>
                            <option value="Arma">Arma</option>
                            <option value="Armadura">Armadura</option>
                            <option value="Ataque">Ataque</option>
                            <option value="Consumível">Consumível</option>
                            <option value="Equipamento">Equipamento</option>
                            <option value="Habilidade Amaldiçoada">Habilidade Amaldiçoada</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>
                </div>

                <fieldset class="item-form-grupo">
                    <legend>Dano</legend>
                    <div class="item-form-linha">
                        <div class="item-form-campo item-form-qtd">
                            <label for="item-qtd-dados">Nº de dados</label>
                            <input id="item-qtd-dados" type="number" min="0" placeholder="1">
                        </div>
                        <div class="item-form-campo item-form-dado">
                            <label for="item-dado">Dado</label>
                            <select id="item-dado">
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
                            <input id="item-valor-fixo" type="number" placeholder="0">
                        </div>
                        <div class="item-form-campo item-form-meio">
                            <label for="item-critico">Crítico</label>
                            <input id="item-critico" type="text" maxlength="100" placeholder="Ex.: 19–20, ×2">
                        </div>
                        <div class="item-form-campo item-form-meio">
                            <label for="item-tipo-dano">Tipo de dano</label>
                            <input id="item-tipo-dano" type="text" maxlength="100" list="item-tipos-dano" placeholder="Ex.: cortante">
                            <datalist id="item-tipos-dano">
                                @foreach (['cortante', 'perfurante', 'impacto', 'explosivo', 'flamejante', 'congelante', 'eletrizante', 'tóxico', 'amaldiçoado', 'queda'] as $tipoDano)
                                    <option value="{{ $tipoDano }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="item-form-campo item-form-largo">
                            <label for="item-condicao">Condição especial</label>
                            <input id="item-condicao" type="text" maxlength="255" placeholder="Ex.: sangramento, atordoado por 1 rodada">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="item-form-grupo">
                    <legend>Uso e porte</legend>
                    <div class="item-form-linha">
                        <div class="item-form-campo item-form-meio">
                            <label for="item-tamanho">Tamanho</label>
                            <input id="item-tamanho" type="text" maxlength="100" placeholder="Ex.: médio">
                        </div>
                        <div class="item-form-campo item-form-meio">
                            <label for="item-acao-uso">Ação de uso</label>
                            <input id="item-acao-uso" type="text" maxlength="100" placeholder="Ex.: ação padrão">
                        </div>
                        <div class="item-form-campo item-form-meio">
                            <label for="item-alcance-armadura">Alcance da Armadura</label>
                            <input id="item-alcance-armadura" type="text" maxlength="100" placeholder="Ex.: 1–40">
                        </div>
                        <div class="item-form-campo item-form-meio">
                            <label for="item-incremento-defesa">Incremento de defesa</label>
                            <input id="item-incremento-defesa" type="text" maxlength="100" placeholder="Ex.: +2">
                        </div>
                        <div class="item-form-campo item-form-meio">
                            <label for="item-penalidade-destreza">Penalidade de destreza</label>
                            <input id="item-penalidade-destreza" type="text" maxlength="100" placeholder="Ex.: −5">
                        </div>
                        <div class="item-form-campo item-form-meio">
                            <label for="item-peso">Peso</label>
                            <input id="item-peso" type="text" maxlength="100" placeholder="Ex.: 2 kg">
                        </div>
                    </div>
                </fieldset>

                <div class="campo campo-descricao">
                    <label for="item-efeitos-mundanos">Efeitos mundanos</label>
                    <textarea id="item-efeitos-mundanos" placeholder="Bônus, penalidades e efeitos comuns do item"></textarea>
                </div>

                <div class="campo campo-descricao">
                    <label for="item-efeitos-amaldicoados">Efeitos amaldiçoados</label>
                    <textarea id="item-efeitos-amaldicoados" placeholder="Efeitos ligados à maldição, à Aura ou a técnicas"></textarea>
                </div>

                <div class="itens-botoes">
                    <button class="botao-adicionar-talento" type="button" id="adicionar-item">
                        + Adicionar Item
                    </button>
                    <button class="botao-rolar-preview" type="button" id="rolar-preview-item" title="Rola o dano configurado acima, sem precisar adicionar o item">
                        <x-icone n="dados"/> Rolar Dano
                    </button>
                </div>

                <p class="rolar-preview-resultado" id="rolar-preview-resultado"></p>

                <div class="itens-adicionados-titulo" id="itens-adicionados-titulo" style="display:none">
                    <h3 class="secao-titulo">Itens Adicionados</h3>
                </div>
                <input type="hidden" id="itens" name="itens" value="">
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

            <div class="ficha-acoes">
                <button class="button button-secundario" type="button" id="preencher-teste">Ficha Teste</button>
                <button class="button" type="submit">{{ $editando ? 'Salvar Alterações' : 'Salvar Ficha' }}</button>
            </div>

        </div>

        <div class="card card-rolagem card-talentos" id="talentos-mundanos-card">
            <button type="button" class="botao-fechar" id="fechar-talentos" title="Fechar (Esc)" aria-label="Fechar">✕</button>

            <div class="botoes-grid" style="text-align:center">
                <h1>Talentos Mundanos</h1>
            </div>

            <input type="hidden" id="talentos" name="talentos" value="">
            <div class="pastas-barra" id="pastas-talentos"></div>

            <button type="button" class="botao-adicionar-talento" id="adicionar-talento">
                + Adicionar Talento
            </button>
            @unless ($somenteLeitura)
                <div id="livro-talentos-mundano"></div>
            @endunless

            <div class="lista-talentos" id="lista-talentos"></div>
            <p class="vazio-lista" id="talentos-vazio" style="display:none"></p>
        </div>

        {{-- Técnicas de arte: Hexomancia e Fisiomancia em abas. Mesma mecânica dos Talentos Mundanos (custo de Aura, ativar, pastas);
             todos ficam no mesmo JSON "talentos", separados pelo campo "tipo". --}}
        <div class="card card-rolagem card-talentos" id="talentos-arte-card">
            <button type="button" class="botao-fechar" id="fechar-talentos-arte" title="Fechar (Esc)" aria-label="Fechar">✕</button>

            <div class="botoes-grid" style="text-align:center">
                <h1>Técnicas de arte</h1>
            </div>

            <div class="abas-painel" role="tablist">
                <button type="button" class="aba-painel ativo" data-aba="hexomancia">Hexomancia <span class="contador-painel" id="cont-talentos-hexomancia"></span></button>
                <button type="button" class="aba-painel" data-aba="fisiomancia">Fisiomancia <span class="contador-painel" id="cont-talentos-fisiomancia"></span></button>
            </div>

            <div class="aba-conteudo ativo" data-aba="hexomancia">
                <div class="pastas-barra" id="pastas-talentos-hexomancia"></div>
                <button type="button" class="botao-adicionar-talento" id="adicionar-talento-hexomancia">+ Adicionar técnica</button>
                <div class="lista-talentos" id="lista-talentos-hexomancia"></div>
                <p class="vazio-lista" id="talentos-hexomancia-vazio" style="display:none"></p>
            </div>

            {{-- Fisiomancia tem sub-abas: as técnicas e as propriedades fisiomânticas (catálogo em resources/data/propriedades_fisiomantes.json
                 e propriedades salvas na ficha, coluna propriedades_fisiomantes). O pai das sub-abas é este bloco (id usado no localStorage). --}}
            <div class="aba-conteudo" data-aba="fisiomancia" id="arte-fisio-subabas">
                <div class="abas-painel abas-internas" role="tablist">
                    <button type="button" class="aba-painel ativo" data-aba="tecnicas">Técnicas</button>
                    <button type="button" class="aba-painel" data-aba="propriedades">Propriedades fisiomânticas <span class="contador-painel" id="cont-propriedades"></span></button>
                </div>

                <div class="aba-conteudo ativo" data-aba="tecnicas">
                    <div class="pastas-barra" id="pastas-talentos-fisiomancia"></div>
                    <button type="button" class="botao-adicionar-talento" id="adicionar-talento-fisiomancia">+ Adicionar técnica</button>
                    <div class="lista-talentos" id="lista-talentos-fisiomancia"></div>
                    <p class="vazio-lista" id="talentos-fisiomancia-vazio" style="display:none"></p>
                </div>

                <div class="aba-conteudo propriedades-fisio" data-aba="propriedades" id="propriedades-fisio">
                    <h3 class="propriedades-titulo">Propriedades fisiomânticas</h3>
                    <input type="hidden" id="propriedades-fisiomantes" name="propriedades_fisiomantes" value="">

                    <h5 class="maldicao-subtitulo">Minhas propriedades</h5>
                    <div id="prop-minhas-lista"></div>
                    <p class="vazio-lista" id="prop-minhas-vazio">Nenhuma propriedade salva. Pesquise no catálogo e clique em “Salvar”.</p>

                    <h5 class="maldicao-subtitulo">Catálogo</h5>
                    <div class="prop-busca-linha">
                        <input type="search" id="prop-busca" class="prop-busca" placeholder="Pesquisar propriedade, material ou efeito…" autocomplete="off">
                        <select id="prop-grupo" class="prop-grupo"><option value="">Todos os grupos</option></select>
                    </div>
                    <p class="prop-grupo-descricao" id="prop-grupo-descricao"></p>
                    <div id="prop-catalogo"></div>
                    <p class="vazio-lista" id="prop-catalogo-vazio">Digite o nome de uma propriedade, material ou efeito para pesquisar.</p>

                    <details class="tecnica-novo" id="prop-personalizada">
                        <summary>Propriedade personalizada</summary>
                        <div class="tecnica-form">
                            <div class="tecnica-form-linha">
                                <div class="campo">
                                    <label for="prop-pers-nome">Nome</label>
                                    <input id="prop-pers-nome" type="text" placeholder="Nome da propriedade">
                                </div>
                                <div class="campo">
                                    <label for="prop-pers-fonte">Fonte</label>
                                    <input id="prop-pers-fonte" type="text" placeholder="Material, ser ou situação">
                                </div>
                            </div>
                            <div class="tecnica-form-linha">
                                <div class="campo">
                                    <label for="prop-pers-ameaca">Ameaça</label>
                                    <input id="prop-pers-ameaca" type="number" placeholder="0">
                                </div>
                            </div>
                            <div class="campo">
                                <label for="prop-pers-descricao">Descrição</label>
                                <textarea id="prop-pers-descricao" placeholder="Efeito da propriedade"></textarea>
                            </div>
                            <button type="button" class="botao-adicionar-talento" id="prop-pers-salvar">+ Salvar propriedade</button>
                        </div>
                    </details>
                </div>
            </div>
        </div>

        {{-- Artes marciais: nível, barra de experiência de arte e talentos customizados de cada arte (JSON "artes_marciais") --}}
        <div class="card card-rolagem card-talentos" id="artes-marciais-card">
            <button type="button" class="botao-fechar" id="fechar-artes-marciais" title="Fechar (Esc)" aria-label="Fechar">✕</button>

            <div class="botoes-grid" style="text-align:center">
                <h1>Artes Marciais</h1>
            </div>

            <input type="hidden" id="artes_marciais" name="artes_marciais" value="">
            <div class="am-abas" id="am-abas" role="tablist"></div>
            <div class="am-corpo" id="am-corpo"></div>
            @unless ($somenteLeitura)
                <div id="am-livro"></div>
            @endunless
        </div>

        <div class="card card-rolagem" id="card-rolagem">
            <button class="botao-fechar" id="x" title="Fechar (Esc)" aria-label="Fechar">✕</button>
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

                <hr>

                <div class="titulo-rolagem">
                    <h3><p id="resultado">?</p></h3>
                </div>
            </div>
        </div>

        <input type="hidden" id="tecnicas" name="tecnicas" value="">
        <input type="hidden" id="pastas" name="pastas" value="">

        <div class="card card-rolagem card-talentos" id="tecnicas-card">
            <button type="button" class="botao-fechar" id="fechar-tecnicas" title="Fechar (Esc)" aria-label="Fechar">✕</button>

            <div class="botoes-grid" style="text-align:center">
                <h1>Técnicas</h1>
            </div>

            <div class="abas-painel" role="tablist">
                <button type="button" class="aba-painel ativo" data-aba="hexomancia">Técnicas de Hexomancia <span class="contador-painel" id="cont-tecnicas-hexomancia"></span></button>
                <button type="button" class="aba-painel" data-aba="fisiomancia">Técnicas de Fisiomancia <span class="contador-painel" id="cont-tecnicas-fisiomancia"></span></button>
                <button type="button" class="aba-painel" data-aba="aprimoramentos">Aprimoramentos <span class="contador-painel" id="cont-aprim-guardados"></span></button>
            </div>

            {{-- Aprimoramentos guardados: os secretos que o mestre manda, usáveis em qualquer técnica (e nas técnicas de arte) --}}
            <input type="hidden" id="aprimoramentos-guardados" name="aprimoramentos" value="">
            <div class="aba-conteudo aprim-guardados" data-aba="aprimoramentos">
                <p class="aprim-ajuda">
                    Os <b>gerais (coringa)</b> já aparecem em todas as técnicas. Aqui você guarda os seus, como os
                    <b>secretos</b> que o mestre mandar: eles também passam a aparecer em qualquer técnica, para aplicar com − e +.
                    Aprimoramento que só serve para uma técnica se cria nela mesma (Nova técnica ou Editar).
                </p>
                <div id="aprim-guardados-lista"></div>
                <p class="vazio-lista" id="aprim-guardados-vazio">Nenhum aprimoramento guardado ainda.</p>
                @unless ($somenteLeitura)
                <div class="aprim-guardado-novo">
                    <h4>Guardar aprimoramento</h4>
                    <div class="campo">
                        <label for="aprim-novo-nome">Nome</label>
                        <input id="aprim-novo-nome" type="text" maxlength="200" placeholder="Ex.: Lâmina oculta (+1 dado de corte)">
                    </div>
                    <div class="aprim-guardado-linha">
                        <div class="campo">
                            <label for="aprim-novo-tipo">Custo</label>
                            <select id="aprim-novo-tipo">
                                <option value="fixo">Valor fixo</option>
                                <option value="original">Custo original da técnica</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="aprim-novo-custo">Aura por uso</label>
                            <input id="aprim-novo-custo" type="number" min="0" placeholder="0">
                        </div>
                    </div>
                    <label class="aprim-acum"><input type="checkbox" id="aprim-novo-acum" checked> Acumulativo (pode ser aplicado várias vezes)</label>
                    <button type="button" class="botao-adicionar-talento" id="aprim-novo-guardar">+ Guardar aprimoramento</button>
                </div>
                @endunless
            </div>

            <div class="pastas-barra" id="pastas-tecnicas"></div>

            <details class="tecnica-novo" id="tecnica-novo" open>
            <summary>Nova técnica</summary>
            <div class="tecnica-form">
                <div class="campo">
                    <label for="tec-nome">Nome</label>
                    <input id="tec-nome" type="text" placeholder="Nome da técnica">
                </div>
                <div class="campo">
                    <label for="tec-descricao">Descrição</label>
                    <textarea id="tec-descricao" placeholder="Descrição da técnica"></textarea>
                </div>
                <div class="campo">
                    <label for="tec-logica">Lógica de obtenção</label>
                    <textarea id="tec-logica" placeholder="Como a técnica é obtida"></textarea>
                </div>
                <div class="campo">
                    <label for="tec-pasta">Pasta</label>
                    <select id="tec-pasta"></select>
                </div>
                <div class="tecnica-form-linha">
                    <div class="campo">
                        <label for="tec-nivel" id="tec-nivel-rotulo">Nível de maldição requisitado</label>
                        <input id="tec-nivel" type="number" min="0" placeholder="0">
                    </div>
                    <div class="campo">
                        <label for="tec-custo">Custo (Aura)</label>
                        <input id="tec-custo" type="number" min="0" placeholder="0">
                    </div>
                </div>
                <div class="campo">
                    <label for="tec-eficiencia" title="Aura abatida do custo base da técnica. Não afeta o custo dos aprimoramentos.">Eficiência (Aura abatida do custo base)</label>
                    <input id="tec-eficiencia" type="number" min="0" placeholder="0">
                </div>
                <div class="campo">
                    <label for="tec-processo">Processo</label>
                    <textarea id="tec-processo" placeholder="Processo para executar a técnica"></textarea>
                </div>
                <div class="campo">
                    <label for="tec-efeito">Efeito</label>
                    <textarea id="tec-efeito" placeholder="Efeito da técnica"></textarea>
                </div>

                <div class="campo">
                    <label>Aprimoramentos</label>
                    <div id="tec-aprimoramentos-form"></div>
                    <button type="button" class="button" id="tec-add-aprimoramento">+ Aprimoramento</button>
                </div>

                <button type="button" class="botao-adicionar-talento" id="adicionar-tecnica">+ Adicionar Técnica</button>
                <button type="button" class="button button-secundario" id="cancelar-edicao-tecnica" hidden>Cancelar edição</button>
            </div>
            </details>

            <div id="lista-tecnicas"></div>
            <p class="vazio-lista" id="tecnicas-vazio" style="display:none"></p>
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

                    <button class="atributo-card btn-popup-card" id="re-roll-vida">
                        Re-rolar seus Pontos de Vida
                    </button>

                    <button class="atributo-card btn-popup-card" id="re-roll-sanidade">
                        Re-rolar seus Pontos de Sanidade
                    </button>

                    <button class="atributo-card btn-popup-card" id="re-roll-sorte">
                        Re-rolar seus Pontos de Sorte
                    </button>

                    <button class="atributo-card btn-popup-card" id="re-roll-aura">
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
    <button type="button" class="botao-fechar" id="fechar-maldicao-card" title="Fechar (Esc)" aria-label="Fechar">✕</button>

    <div class="botoes-grid" style="text-align:center">
        <h1>Maldições</h1>
    </div>

    <div class="maldicao-config">
        <div class="campo">
            <label for="nivel-hexomante">Nível de Hexomante</label>
            <input id="nivel-hexomante" name="nivel_hexomante" type="number" min="0" placeholder="Nível" value="{{ old('nivel_hexomante', $personagem->nivel_hexomante ?? 0) }}">
        </div>
        <div class="campo">
            <label for="ameaca">Ameaça de Maldição</label>
            <input id="ameaca" name="ameaca" type="number" min="0" placeholder="Ameaça" value="{{ old('ameaca', $personagem->ameaca ?? 0) }}">
        </div>
    </div>

        <div class="maldicao-seletor">
            <div class="campo">
                <label for="maldicao-catalogo">Maldição</label>
                <select id="maldicao-catalogo"></select>
            </div>
            <p class="maldicao-previa" id="maldicao-previa"></p>
            @auth
                @unless (\App\Support\AcessoMaldicoes::podeVerRestritas(auth()->user()))
                    <p class="maldicao-previa"><a href="{{ route('maldicoes.index') }}">Tem a chave do mestre? Use-a no catálogo de maldições.</a></p>
                @endunless
            @endauth

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
<script>
// ==================== Talentos do livro ====================
// Catálogo de config/talentos_livro.php, baixado só quando alguém abre a lista. montar() cria um bloco
// recolhível com pesquisa; clicar em "+ Adicionar" chama aoEscolher(talento) e o painel cria o talento já preenchido.
window.LivroTalentos = (function(){
    const URL_CATALOGO = @json(route('livro.talentos'));
    let dados = null;
    let carregando = null;

    function carregar(){
        if(dados) return Promise.resolve(dados);
        if(!carregando){
            carregando = fetch(URL_CATALOGO, { headers: { Accept: "application/json" } })
                .then(function(r){ if(!r.ok) throw new Error("HTTP " + r.status); return r.json(); })
                .then(function(d){ dados = d; return d; })
                .catch(function(e){ carregando = null; throw e; });
        }
        return carregando;
    }

    function esc(s){ return String(s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c])); }
    function simples(s){ return String(s || "").normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase(); }

    // Descrição que vai para a ficha: o texto do livro com o requisito no fim
    function descricaoCompleta(t){ return t.descricao + (t.requisito ? "\n\nRequisito: " + t.requisito : ""); }

    function montar(el, grupo, aoEscolher){
        el.innerHTML =
            '<details class="livro-tal"><summary>' + icone('livro') + ' Lista do livro <span class="prop-qtd"></span></summary>' +
                '<input type="search" class="livro-tal-busca" placeholder="Pesquisar nome, requisito ou efeito…" autocomplete="off">' +
                '<div class="livro-tal-lista"><p class="vazio-lista">Carregando…</p></div>' +
            '</details>';
        const det = el.querySelector("details");
        const busca = el.querySelector(".livro-tal-busca");
        const lista = el.querySelector(".livro-tal-lista");
        const qtd = el.querySelector(".prop-qtd");
        let achados = [];

        function desenhar(){
            if(!dados) return;
            const todos = dados.talentos[grupo] || [];
            const q = simples(busca.value.trim());
            achados = todos.filter(t => !q || simples(t.nome + " " + (t.requisito || "") + " " + t.descricao).includes(q));
            // quem tem o termo no nome vem primeiro
            if(q) achados.sort((a, b) => (simples(b.nome).includes(q) ? 1 : 0) - (simples(a.nome).includes(q) ? 1 : 0));
            qtd.textContent = (dados.grupos[grupo] || "") + " · " + (q ? achados.length + " de " : "") + todos.length;
            lista.innerHTML = achados.length ? achados.map(function(t, i){
                return '<div class="prop-item livro-tal-item" data-i="' + i + '">' +
                    '<div class="prop-item-cab"><strong>' + esc(t.nome) + '</strong>' +
                        (t.custo !== null ? '<span class="prop-ameaca">' + t.custo + ' Aura</span>' : '') +
                        '<button type="button" class="button livro-tal-add">+ Adicionar</button></div>' +
                    (t.requisito ? '<p class="prop-ref">Requisito: ' + esc(t.requisito) + '</p>' : '') +
                    '<p class="livro-tal-desc" title="Clique para ver tudo">' + esc(t.descricao) + '</p>' +
                '</div>';
            }).join("") : '<p class="vazio-lista">Nenhum talento do livro com esse termo.</p>';
        }

        det.addEventListener("toggle", function(){
            if(!det.open) return;
            carregar().then(desenhar).catch(function(){
                lista.innerHTML = '<p class="vazio-lista">Não foi possível carregar a lista do livro.</p>';
            });
            busca.focus();
        });
        busca.addEventListener("input", desenhar);
        lista.addEventListener("click", function(e){
            const item = e.target.closest(".livro-tal-item");
            if(!item) return;
            const t = achados[Number(item.dataset.i)];
            if(e.target.closest(".livro-tal-add")){
                if(!t) return;
                aoEscolher(t);
                item.classList.add("adicionado");
                const b = e.target.closest(".livro-tal-add");
                b.textContent = "✓ Adicionado";
                setTimeout(function(){ b.textContent = "+ Adicionar"; item.classList.remove("adicionado"); }, 1500);
            } else if(e.target.closest(".livro-tal-desc")){
                item.classList.toggle("aberto");
            }
        });

        return {
            // Troca o grupo mostrado (aba de arte marcial), mantendo a pesquisa e o bloco aberto
            grupo: function(g){ grupo = g; if(dados) desenhar(); }
        };
    }

    return { carregar: carregar, montar: montar, descricaoCompleta: descricaoCompleta };
})();

// ==================== Pastas (talentos, técnicas, anotações) + atalhos ====================
window.PastasFicha = (function(){
    const TIPOS = ["talentos", "tecnicas", "anotacoes", "talentos_hexomancia", "talentos_fisiomancia"];
    const salvo = @json($pastasSalvas);
    const estado = {};
    TIPOS.forEach(function(t){
        estado[t] = Array.isArray(salvo[t]) ? salvo[t].filter(n => typeof n === "string" && n.trim() !== "") : [];
    });
    const filtro = {};
    const cfg = {};
    const modo = {};
    const confirmarExcl = {};

    function esc(t){
        return String(t == null ? "" : t).replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function usos(tipo){ return cfg[tipo] ? cfg[tipo].usadas() : []; }

    function nomes(tipo){
        const vistos = new Set();
        const out = [];
        estado[tipo].concat(usos(tipo).filter(n => n)).forEach(function(n){
            if(!vistos.has(n)){ vistos.add(n); out.push(n); }
        });
        return out.sort((a, b) => a.localeCompare(b, "pt-BR"));
    }

    function visivel(tipo, pasta){
        const f = filtro[tipo] || "__todas__";
        if(f === "__todas__") return true;
        if(f === "__sem__") return !pasta;
        return pasta === f;
    }

    function filtroAtual(tipo){
        const f = filtro[tipo] || "__todas__";
        return (f === "__todas__" || f === "__sem__") ? "" : f;
    }

    function opcoes(tipo, atual){
        let lista = nomes(tipo);
        if(atual && !lista.includes(atual)) lista = lista.concat([atual]);
        return '<option value="">Sem pasta</option>' + lista.map(function(n){
            return '<option value="' + esc(n) + '"' + (n === atual ? " selected" : "") + ">" + esc(n) + "</option>";
        }).join("");
    }

    function chip(valor, rotulo, qtd, ativo, ico){
        return '<button type="button" class="pasta-chip' + (ativo === valor ? " ativo" : "") +
            '" data-pasta="' + esc(valor) + '">' + (ico ? icone(ico) + " " : "") + esc(rotulo) +
            ' <span class="pasta-qtd">' + qtd + "</span></button>";
    }

    function render(tipo){
        const c = cfg[tipo];
        if(!c) return;
        const lista = nomes(tipo);
        const f = filtro[tipo] || "__todas__";
        if(f !== "__todas__" && f !== "__sem__" && !lista.includes(f)) filtro[tipo] = "__todas__";
        const ativo = filtro[tipo] || "__todas__";
        const us = usos(tipo);

        let h = '<div class="pastas-chips">';
        h += chip("__todas__", "Todas", us.length, ativo);
        h += chip("__sem__", "Sem pasta", us.filter(n => !n).length, ativo);
        lista.forEach(function(n){
            h += chip(n, n, us.filter(x => x === n).length, ativo, "pasta");
        });
        h += '<button type="button" class="pasta-chip pasta-nova" data-acao="nova">＋ Nova pasta</button></div>';

        if(ativo !== "__todas__" && ativo !== "__sem__"){
            const conf = !!confirmarExcl[tipo];
            h += '<div class="pastas-acoes">' +
                '<button type="button" class="pasta-link" data-acao="renomear">Renomear pasta</button>' +
                '<button type="button" class="pasta-link perigo' + (conf ? " confirmar" : "") + '" data-acao="excluir">' +
                (conf ? "Clique de novo para excluir (os itens ficam sem pasta)" : "Excluir pasta") + "</button></div>";
        }

        const m = modo[tipo];
        if(m){
            h += '<div class="pasta-editor"><input type="text" class="pasta-editor-input" maxlength="60" placeholder="Nome da pasta" value="' +
                esc(m.tipo === "renomear" ? m.alvo : "") + '">' +
                '<button type="button" class="button" data-acao="salvar-pasta">Salvar</button>' +
                '<button type="button" class="pasta-link" data-acao="cancelar-pasta">Cancelar</button></div>' +
                '<p class="pasta-erro" style="display:none"></p>';
        }

        c.barra.innerHTML = h;
        if(m){
            const inp = c.barra.querySelector(".pasta-editor-input");
            inp.focus();
            inp.select();
        }
    }

    function avisarMudanca(){
        const campo = document.getElementById("pastas");
        if(campo) campo.dispatchEvent(new Event("input", { bubbles: true }));
    }

    function mudou(tipo){
        const c = cfg[tipo];
        if(c.aoMudarPastas) c.aoMudarPastas();
        render(tipo);
        if(c.aoFiltrar) c.aoFiltrar();
        avisarMudanca();
    }

    function salvarPasta(tipo){
        const c = cfg[tipo];
        const m = modo[tipo];
        const inp = c.barra.querySelector(".pasta-editor-input");
        const erro = c.barra.querySelector(".pasta-erro");
        const nome = inp.value.trim();
        const falhar = function(msg){ erro.textContent = msg; erro.style.display = "block"; inp.focus(); };

        if(nome === "") return falhar("Digite um nome para a pasta.");
        if(nome === "__todas__" || nome === "__sem__") return falhar("Esse nome não pode ser usado.");
        const repetido = nomes(tipo).some(n => n.toLowerCase() === nome.toLowerCase() && !(m.tipo === "renomear" && n === m.alvo));
        if(repetido) return falhar("Já existe uma pasta com esse nome.");

        if(m.tipo === "nova"){
            estado[tipo].push(nome);
        } else {
            const i = estado[tipo].indexOf(m.alvo);
            if(i >= 0) estado[tipo][i] = nome; else estado[tipo].push(nome);
            if(c.aoRenomear) c.aoRenomear(m.alvo, nome);
        }
        filtro[tipo] = nome;
        modo[tipo] = null;
        mudou(tipo);
    }

    function registrar(tipo, config){
        cfg[tipo] = config;
        config.barra.addEventListener("click", function(e){
            const btn = e.target.closest("button");
            if(!btn) return;

            if(btn.dataset.pasta !== undefined){
                filtro[tipo] = btn.dataset.pasta;
                confirmarExcl[tipo] = false;
                modo[tipo] = null;
                render(tipo);
                if(config.aoFiltrar) config.aoFiltrar();
                return;
            }

            const acao = btn.dataset.acao;
            if(acao === "nova"){ modo[tipo] = { tipo: "nova" }; render(tipo); }
            else if(acao === "renomear"){ modo[tipo] = { tipo: "renomear", alvo: filtro[tipo] }; render(tipo); }
            else if(acao === "cancelar-pasta"){ modo[tipo] = null; render(tipo); }
            else if(acao === "salvar-pasta"){ salvarPasta(tipo); }
            else if(acao === "excluir"){
                if(!confirmarExcl[tipo]){
                    confirmarExcl[tipo] = true;
                    render(tipo);
                    setTimeout(function(){
                        if(confirmarExcl[tipo]){ confirmarExcl[tipo] = false; render(tipo); }
                    }, 4000);
                    return;
                }
                const alvo = filtro[tipo];
                estado[tipo] = estado[tipo].filter(n => n !== alvo);
                if(config.aoExcluir) config.aoExcluir(alvo);
                filtro[tipo] = "__todas__";
                confirmarExcl[tipo] = false;
                mudou(tipo);
            }
        });

        config.barra.addEventListener("keydown", function(e){
            if(!e.target.classList.contains("pasta-editor-input")) return;
            if(e.key === "Enter"){ e.preventDefault(); salvarPasta(tipo); }
            if(e.key === "Escape"){ e.preventDefault(); e.stopPropagation(); modo[tipo] = null; render(tipo); }
        });

        render(tipo);
    }

    function json(){
        const out = {};
        TIPOS.forEach(function(t){ out[t] = nomes(t); });
        return JSON.stringify(out);
    }

    return { registrar: registrar, opcoes: opcoes, visivel: visivel, filtroAtual: filtroAtual,
             atualizar: render, json: json };
})();

// Esc fecha o painel aberto; botão "voltar ao topo"
(function(){
    document.addEventListener("keydown", function(e){
        if(e.key !== "Escape") return;
        // fecha o aberto mais recentemente (na pilha da esquerda a ordem fica em style.order)
        const abertos = [...document.querySelectorAll("#form-ficha .card-rolagem.ativo")];
        abertos.sort((a, b) => (Number(a.style.order) || 0) - (Number(b.style.order) || 0));
        if(abertos.length) abertos[abertos.length - 1].classList.remove("ativo");
    });

    const topo = document.createElement("button");
    topo.type = "button";
    topo.className = "voltar-topo";
    topo.title = "Voltar ao topo";
    topo.setAttribute("aria-label", "Voltar ao topo");
    topo.textContent = "↑";
    topo.addEventListener("click", function(){ window.scrollTo({ top: 0, behavior: "smooth" }); });
    document.body.appendChild(topo);
    window.addEventListener("scroll", function(){
        topo.classList.toggle("visivel", window.scrollY > 600);
    }, { passive: true });
})();

// Abas dentro dos painéis (.abas-painel > .aba-painel[data-aba]; conteúdos em .aba-conteudo[data-aba] filhos diretos do painel).
// A aba escolhida fica lembrada no navegador; o painel recebe o evento "aba-mudou".
(function(){
    document.querySelectorAll(".abas-painel").forEach(function(abas){
        const painel = abas.parentElement;
        function ativar(nome, lembrar){
            const b = abas.querySelector('.aba-painel[data-aba="' + nome + '"]');
            if(!b) return;
            abas.querySelectorAll(".aba-painel").forEach(x => x.classList.toggle("ativo", x === b));
            painel.querySelectorAll(":scope > .aba-conteudo").forEach(c => c.classList.toggle("ativo", c.dataset.aba === nome));
            painel.dataset.abaAtual = nome; // o CSS usa para esconder o que não é da aba (ex.: Técnicas > Aprimoramentos)
            if(lembrar){ try { localStorage.setItem("ficha-aba:" + painel.id, nome); } catch(e) {} }
            painel.dispatchEvent(new CustomEvent("aba-mudou", { detail: nome }));
        }
        abas.addEventListener("click", function(e){
            const b = e.target.closest(".aba-painel");
            if(b) ativar(b.dataset.aba, true);
        });
        try {
            const salva = localStorage.getItem("ficha-aba:" + painel.id);
            if(salva) ativar(salva, false);
        } catch(e) {}
    });
})();

// Painéis flutuantes redimensionáveis: arraste o canto inferior direito; o tamanho fica lembrado no navegador
(function(){
    ["talentos-mundanos-card", "talentos-arte-card", "tecnicas-card", "anotacoes-card", "maldicao-card", "artes-marciais-card", "dinheiro-card", "ama-card"].forEach(function(id){
        const card = document.getElementById(id);
        if(!card) return;
        card.classList.add("redimensionavel");
        // chave nova (v2): os tamanhos guardados antes eram do padrão menor e deixariam o painel pequeno de novo
        const chave = "ficha-painel-tamanho-v2:" + id;
        try {
            const salvo = JSON.parse(localStorage.getItem(chave) || "null");
            if(salvo && salvo.w) card.style.width = salvo.w + "px";
            if(salvo && salvo.h) card.style.height = salvo.h + "px"; // só se a pessoa mudou a altura
        } catch(e) {}
        let antesW = 0, antesH = 0;
        card.addEventListener("mousedown", function(){ antesW = card.offsetWidth; antesH = card.offsetHeight; });
        card.addEventListener("mouseup", function(){
            const w = Math.round(card.offsetWidth), h = Math.round(card.offsetHeight);
            if(!antesW || (w === antesW && h === antesH)) return; // não foi um redimensionamento
            let salvo = {};
            try { salvo = JSON.parse(localStorage.getItem(chave) || "null") || {}; } catch(e) {}
            if(w !== antesW) salvo.w = w;
            if(h !== antesH) salvo.h = h;
            // altura não mexida fica automática (cresce com o conteúdo, sem rolagem dentro do painel)
            if(h === antesH && !salvo.h) card.style.height = "";
            try { localStorage.setItem(chave, JSON.stringify(salvo)); } catch(e) {}
        });
    });
})();

// Pilha dos painéis da esquerda: em vez de abrirem todos no mesmo ponto (um cobrindo o outro),
// os abertos ficam numa coluna, o mais recente embaixo. Os painéis continuam dentro do #form-ficha (os campos são enviados).
(function(){
    const form = document.getElementById("form-ficha");
    if(!form) return;
    const ids = ["anotacoes-card", "talentos-mundanos-card", "talentos-arte-card", "tecnicas-card", "maldicao-card", "artes-marciais-card", "dinheiro-card", "ama-card"];
    const cards = ids.map(id => document.getElementById(id)).filter(Boolean);
    if(!cards.length) return;

    const pilha = document.createElement("div");
    pilha.id = "paineis-pilha";
    form.appendChild(pilha);

    let ordem = 0;
    cards.forEach(function(card){
        pilha.appendChild(card);
        let estava = card.classList.contains("ativo");
        if(estava) card.style.order = ++ordem;
        new MutationObserver(function(){
            const agora = card.classList.contains("ativo");
            if(agora && !estava){
                card.style.order = ++ordem;
                requestAnimationFrame(() => card.scrollIntoView({ block: "nearest", behavior: "smooth" }));
            }
            estava = agora;
        }).observe(card, { attributes: true, attributeFilter: ["class"] });
    });

    // Empurra a ficha para o lado dos painéis abertos (pilha à esquerda, Rolagem à direita), para nada ficar por baixo deles.
    // Só em telas largas: se a ficha ficaria estreita demais (menos de MIN px), a Rolagem volta a ficar por cima e, depois, a pilha.
    const container = form.closest(".container");
    const rolagem = document.getElementById("card-rolagem");
    const MIN = 520, FOLGA = 24;
    function empurrar(){
        if(!container) return;
        const vw = document.documentElement.clientWidth;
        const caixa = el => el.getBoundingClientRect();
        let esq = 0, dir = 0;
        if(cards.some(c => c.classList.contains("ativo"))) esq = Math.round(caixa(pilha).right) + FOLGA;
        if(rolagem && rolagem.classList.contains("ativo")) dir = Math.round(vw - caixa(rolagem).left) + FOLGA;
        if(vw - esq - dir < MIN) dir = 0;
        if(vw - esq < MIN) esq = 0;
        container.classList.toggle("ficha-empurrada", esq > 0 || dir > 0);
        container.style.setProperty("--empurra-esq", esq + "px");
        container.style.setProperty("--empurra-dir", dir + "px");
    }
    let quadro = 0;
    const agendar = () => { cancelAnimationFrame(quadro); quadro = requestAnimationFrame(empurrar); };
    [...cards, rolagem].filter(Boolean).forEach(c => new MutationObserver(agendar).observe(c, { attributes: true, attributeFilter: ["class"] }));
    if(window.ResizeObserver){ const ro = new ResizeObserver(agendar); ro.observe(pilha); if(rolagem) ro.observe(rolagem); }
    window.addEventListener("resize", agendar);
    agendar();

    // Caixas de texto dos painéis crescem com o conteúdo (até 60% da tela), em vez de mostrar só três linhas com rolagem
    function autoAltura(t){
        if(!t.offsetParent) return; // painel fechado: mede quando abrir
        t.style.height = "auto";
        t.style.height = Math.min(t.scrollHeight + 2, Math.round(window.innerHeight * .6)) + "px";
    }
    const todas = raiz => raiz.querySelectorAll("textarea").forEach(autoAltura);
    pilha.addEventListener("input", e => { if(e.target.tagName === "TEXTAREA") autoAltura(e.target); });
    let quadroTexto = 0;
    new MutationObserver(function(){
        cancelAnimationFrame(quadroTexto);
        quadroTexto = requestAnimationFrame(() => todas(pilha));
    }).observe(pilha, { childList: true, subtree: true, attributes: true, attributeFilter: ["class"] });
    if(rolagem) rolagem.addEventListener("input", e => { if(e.target.tagName === "TEXTAREA") autoAltura(e.target); });
})();
</script>
@endsection

@section('script')
<script>
    // ==================== Aprimoramentos (compartilhado: painel Técnicas e Técnicas de arte) ====================
    // Item = { custo, eficiencia, aprimoramentos:[{texto,custo,acumulativo,qtd,gratis,ativo}], gerais:{dano:{qtd,gratis},...}, gratis_disponiveis }
    // Custo total = max(0, custo - eficiência) + Σ custoUnitário × max(0, qtd - grátis). A eficiência não afeta os aprimoramentos.
    // O trecho entre os marcadores é puro (sem DOM) e é carregado por deploy/tests/aprimoramentos.test.js.
    /*APRIM-INICIO*/
    const Aprimoramentos = {
        somenteLeitura: false,
        GERAIS: [
            { chave: "dano", nome: "+1 dado de dano/cura ou vantagem −5", tipo: "fixo", custo: 9 },
            { chave: "acao", nome: "Ação de uso −1", tipo: "original" },
            { chave: "alvos", nome: "+1 alvo", tipo: "original" },
            { chave: "alcance", nome: "Alcance +3 m", tipo: "fixo", custo: 9 }
        ],
        // Aprimoramentos guardados pelo jogador (aba "Aprimoramentos" do painel Técnicas): valem em qualquer técnica,
        // como os gerais. Cada um vira { chave: "s_<id>", nome, tipo, custo, acumulativo, guardado: true }.
        guardados: [],
        definirGuardados: function(lista){
            const self = this;
            this.guardados = (Array.isArray(lista) ? lista : []).filter(a => a && a.id && a.nome).map(function(a){
                return { chave: "s_" + a.id, nome: String(a.nome), tipo: a.tipo === "original" ? "original" : "fixo",
                         custo: self.nat(a.custo), acumulativo: a.acumulativo !== false, guardado: true };
            });
        },
        // Os 4 gerais do sistema e depois os guardados
        todos: function(){
            return this.GERAIS.concat(this.guardados);
        },
        def: function(chave){
            return this.todos().find(d => d.chave === chave) || null;
        },
        nat: function(v, max){
            let n = parseInt(v, 10);
            if(isNaN(n) || n < 0) n = 0;
            return max === undefined ? n : Math.min(n, max);
        },
        esc: function(t){
            return String(t == null ? "" : t).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
        },
        // Registros antigos (só "ativo") viram qtd 1/0; não acumulativo vai de 0 a 1; grátis nunca passa de qtd
        normalizarLista: function(lista){
            const self = this;
            return (Array.isArray(lista) ? lista : []).filter(a => a && typeof a === "object").map(function(a){
                const acum = !!a.acumulativo;
                let qtd = (a.qtd === undefined || a.qtd === null || a.qtd === "") ? (a.ativo ? 1 : 0) : self.nat(a.qtd, 99);
                if(!acum) qtd = Math.min(qtd, 1);
                return {
                    texto: String(a.texto == null ? "" : a.texto),
                    custo: self.nat(a.custo),
                    acumulativo: acum,
                    qtd: qtd,
                    gratis: self.nat(a.gratis, qtd),
                    ativo: qtd > 0
                };
            });
        },
        normalizarGerais: function(g){
            const res = {};
            const self = this;
            this.todos().forEach(function(def){
                const o = (g && typeof g === "object" && g[def.chave] && typeof g[def.chave] === "object") ? g[def.chave] : {};
                const qtd = self.nat(o.qtd, def.acumulativo === false ? 1 : 99);
                res[def.chave] = { qtd: qtd, gratis: self.nat(o.gratis, qtd) };
            });
            return res;
        },
        // Só as chaves com qtd > 0 vão para o JSON salvo
        serializarGerais: function(g){
            const res = {};
            const n = this.normalizarGerais(g);
            this.todos().forEach(function(def){
                if(n[def.chave].qtd > 0) res[def.chave] = { qtd: n[def.chave].qtd, gratis: n[def.chave].gratis };
            });
            return res;
        },
        // Referências (na ordem de exibição) a tudo que pode receber grátis
        entradas: function(item){
            const lista = item.aprimoramentos.slice();
            this.todos().forEach(def => lista.push(item.gerais[def.chave]));
            return lista;
        },
        usadosGratis: function(item){
            return this.entradas(item).reduce((s, r) => s + Math.min(r.gratis, r.qtd), 0);
        },
        // A soma de grátis não pode passar de gratis_disponiveis: tira dos últimos primeiro
        limitarGratis: function(item){
            const disp = item.gratis_disponiveis;
            let excesso = this.usadosGratis(item) - disp;
            const lista = this.entradas(item);
            for(let i = lista.length - 1; i >= 0 && excesso > 0; i--){
                const tira = Math.min(lista[i].gratis, excesso);
                lista[i].gratis -= tira;
                excesso -= tira;
            }
            return item;
        },
        // Normaliza o item no lugar (idempotente)
        normalizarItem: function(item){
            item.custo = this.nat(item.custo);
            item.eficiencia = this.nat(item.eficiencia);
            item.aprimoramentos = this.normalizarLista(item.aprimoramentos);
            item.gerais = this.normalizarGerais(item.gerais);
            item.gratis_disponiveis = this.nat(item.gratis_disponiveis, 99);
            return this.limitarGratis(item);
        },
        custoBase: function(item){
            return Math.max(0, this.nat(item.custo) - this.nat(item.eficiencia));
        },
        // Soma dos aprimoramentos (personalizados e gerais); "original" custa o custo digitado, sem eficiência
        custoAprimoramentos: function(item){
            const original = this.nat(item.custo);
            let total = 0;
            this.normalizarLista(item.aprimoramentos).forEach(function(a){
                total += a.custo * Math.max(0, a.qtd - a.gratis);
            });
            const g = this.normalizarGerais(item.gerais);
            this.todos().forEach(function(def){
                const unit = def.tipo === "fixo" ? def.custo : original;
                total += unit * Math.max(0, g[def.chave].qtd - g[def.chave].gratis);
            });
            return total;
        },
        // Técnica de arte acima do nível de maldição do personagem: +5 de Aura por nível que falta
        penalidadeNivel: function(item){
            if(item.nivel_atual === undefined) return 0;
            return 5 * Math.max(0, this.nat(item.nivel_req) - this.nat(item.nivel_atual));
        },
        custoTotal: function(item){
            return this.custoBase(item) + this.custoAprimoramentos(item) + this.penalidadeNivel(item);
        },
        textoCusto: function(item){
            const base = this.custoBase(item);
            const ef = this.nat(item.eficiencia);
            const ap = this.custoAprimoramentos(item);
            const gr = this.usadosGratis(this.normalizarItem({
                custo: item.custo, eficiencia: item.eficiencia, aprimoramentos: item.aprimoramentos,
                gerais: item.gerais, gratis_disponiveis: 99
            }));
            let s = "Custo: " + this.nat(item.custo);
            if(ef > 0) s += " − " + ef + " de eficiência = " + base;
            if(ap > 0 || gr > 0) s += " + " + ap + " de aprimoramentos" + (gr > 0 ? " (" + gr + (gr === 1 ? " grátis)" : " grátis)") : "") + " = " + (base + ap);
            const pen = this.penalidadeNivel(item);
            if(pen > 0) s += " + " + pen + " (nível de maldição " + this.nat(item.nivel_atual) + ", requer " + this.nat(item.nivel_req) + ") = " + this.custoTotal(item);
            return s + " Aura" + (pen === 0 && this.nat(item.nivel_req) > 0 ? " · requer nível " + this.nat(item.nivel_req) : "");
        },
        textoUsados: function(item){
            return "usados " + this.usadosGratis(item) + " de " + item.gratis_disponiveis;
        },

        // ---- HTML (strings) ----
        passo: function(acao, ap, k, valor, podeMenos, podeMais, rotulo){
            const ro = this.somenteLeitura ? " disabled" : "";
            const dados = ' data-ap="' + ap + '" data-k="' + this.esc(k) + '"';
            return '<span class="aprim-passo' + (rotulo === "grátis" ? " aprim-gratis" : "") + '"' + (rotulo === "grátis" ? ' title="Usos grátis (não cobram Aura)"' : "") + ">" +
                (rotulo ? '<small class="aprim-passo-rotulo">' + rotulo + "</small> " : "") +
                '<button type="button" class="aprim-btn" data-acao="' + acao + 'menos"' + dados + (podeMenos && !ro ? "" : " disabled") + ' aria-label="Diminuir">−</button>' +
                "<b>" + valor + "</b>" +
                '<button type="button" class="aprim-btn" data-acao="' + acao + 'mais"' + dados + (podeMais && !ro ? "" : " disabled") + ' aria-label="Aumentar">+</button>' +
                "</span>";
        },
        // Uma linha: nome e quanto custa cada uso; à direita "Usos − n +" (ou a caixa, se não acumula), "Grátis − n +"
        // (só quando a técnica tem aprimoramentos grátis) e o subtotal em Aura. Linha em uso fica destacada.
        linha: function(item, ref, ap, k, nome, unit, acum){
            const disp = item.gratis_disponiveis;
            const ro = this.somenteLeitura ? " disabled" : "";
            const dados = ' data-ap="' + ap + '" data-k="' + this.esc(k) + '"';
            const pagos = Math.max(0, ref.qtd - ref.gratis);
            let h = '<div class="tecnica-aprim-item aprim-linha' + (ref.qtd > 0 ? " aprim-em-uso" : "") + '">';
            h += '<span class="aprim-nome">';
            if(!acum) h += '<input type="checkbox" class="tec-aprim-check" data-acao="alternar"' + dados + (ref.qtd > 0 ? " checked" : "") + ro + ' aria-label="Usar">';
            h += "<span>" + this.esc(nome) + '<small class="aprim-unit">' + unit + " Aura" + (acum ? " por uso" : "") + "</small></span></span>";
            h += '<span class="aprim-controles">';
            if(acum) h += this.passo("", ap, k, ref.qtd, ref.qtd > 0, ref.qtd < 99, "Usos");
            if(disp > 0 && ref.qtd > 0){
                h += this.passo("g", ap, k, ref.gratis, ref.gratis > 0, ref.gratis < ref.qtd && this.usadosGratis(item) < disp, "grátis");
            }
            h += '<span class="aprim-subtotal" title="Aura deste aprimoramento nesta técnica">' + (ref.qtd > 0 ? "= " + (unit * pagos) : "") + "</span>";
            return h + "</span></div>";
        },
        html: function(item){
            const self = this;
            const original = this.nat(item.custo);
            const unitDe = def => def.tipo === "fixo" ? self.nat(def.custo) : original;
            let h = '<h5 class="maldicao-subtitulo">Aprimoramentos</h5>';
            h += '<p class="aprim-ajuda">Use <b>−</b> e <b>+</b> para aplicar cada aprimoramento quantas vezes quiser; o custo total aparece embaixo.' +
                 (item.gratis_disponiveis > 0 ? " Marque em <b>grátis</b> quantos usos não cobram Aura." : "") + "</p>";
            if(item.aprimoramentos.length){
                h += '<div class="aprim-gerais-titulo">Só desta técnica</div>';
                item.aprimoramentos.forEach(function(a, i){
                    h += self.linha(item, a, "p", i, a.texto, self.nat(a.custo), a.acumulativo);
                });
            }
            h += '<div class="aprim-gerais-titulo">Gerais (coringa) · valem em qualquer técnica</div>';
            this.GERAIS.forEach(function(def){
                h += self.linha(item, item.gerais[def.chave], "g", def.chave, def.nome + (def.tipo === "original" ? " (custo original)" : ""), unitDe(def), true);
            });
            if(this.guardados.length){
                h += '<div class="aprim-gerais-titulo">Guardados (secretos) · valem em qualquer técnica</div>';
                this.guardados.forEach(function(def){
                    h += self.linha(item, item.gerais[def.chave], "g", def.chave, def.nome + (def.tipo === "original" ? " (custo original)" : ""), unitDe(def), def.acumulativo !== false);
                });
            }
            return h;
        },
        // Campo "Aprimoramentos grátis" do rodapé (ao lado da Eficiência)
        rodapeHtml: function(item){
            return '<label class="tecnica-eficiencia aprim-gratis-label" title="Quantas aplicações de aprimoramento esta técnica tem de graça">' + icone('presente') + ' Aprimoramentos grátis ' +
                '<input type="number" min="0" max="99" class="aprim-gratis-disp" value="' + item.gratis_disponiveis + '"> ' +
                '<span class="aprim-usados">' + this.textoUsados(item) + "</span></label>";
        },
        // Linha de criação de um aprimoramento personalizado (formulário "Nova técnica" e edição das técnicas de arte)
        linhaEdicaoHtml: function(){
            return '<input type="text" class="tec-aprim-texto" placeholder="Aprimoramento">' +
                '<input type="number" min="0" class="tec-aprim-custo-input" placeholder="Custo">' +
                '<button type="button" class="vital-btn tec-aprim-remover" title="Remover">✕</button>' +
                '<label class="aprim-acum" title="Pode ser aplicado várias vezes"><input type="checkbox" class="tec-aprim-acum"> Acumulativo</label>';
        },
        // ---- Interação ----
        // Aplica o evento (click nos botões −/+, change nas caixas, input no campo de grátis) ao item. Retorna true se mudou algo.
        tratar: function(e, item){
            if(this.somenteLeitura) return false;
            const t = e.target;
            if(!t || !t.classList) return false;
            if(t.classList.contains("aprim-gratis-disp")){
                if(e.type !== "input") return false;
                item.gratis_disponiveis = this.nat(t.value, 99);
                this.limitarGratis(item);
                return true;
            }
            const el = t.closest ? t.closest("[data-acao]") : null;
            if(!el) return false;
            if(el.tagName === "BUTTON" ? e.type !== "click" : e.type !== "change") return false;
            const ref = el.dataset.ap === "g" ? item.gerais[el.dataset.k] : item.aprimoramentos[Number(el.dataset.k)];
            if(!ref) return false;
            const acum = el.dataset.ap === "g" ? (this.def(el.dataset.k) || {}).acumulativo !== false : ref.acumulativo;
            const acao = el.dataset.acao;
            if(acao === "alternar") ref.qtd = el.checked ? 1 : 0;
            else if(acao === "mais" && acum && ref.qtd < 99) ref.qtd++;
            else if(acao === "menos" && ref.qtd > 0) ref.qtd--;
            else if(acao === "gmais"){
                if(ref.gratis < ref.qtd && this.usadosGratis(item) < item.gratis_disponiveis) ref.gratis++;
            }
            else if(acao === "gmenos" && ref.gratis > 0) ref.gratis--;
            else return false;
            ref.gratis = Math.min(ref.gratis, ref.qtd);
            if("ativo" in ref) ref.ativo = ref.qtd > 0;
            return true;
        },
        // Redesenha só o bloco de aprimoramentos e o "usados X de N" do card (não mexe nos campos digitados)
        redesenhar: function(card, item){
            const bloco = card.querySelector(".aprim-bloco");
            if(bloco) bloco.innerHTML = this.html(item);
            const u = card.querySelector(".aprim-usados");
            if(u) u.textContent = this.textoUsados(item);
        }
    };
    /*APRIM-FIM*/
    Aprimoramentos.somenteLeitura = @json(!empty($somenteLeitura));
    // antes de qualquer técnica ser lida: sem isso, os usos dos guardados (chaves s_...) seriam descartados ao normalizar
    Aprimoramentos.definirGuardados(@json(is_array($personagem->aprimoramentos ?? null) ? $personagem->aprimoramentos : []));

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

    // ---- Modo "ficha já existente": desliga a verificação de pontos (atributos, habilidades, nível) ----
    const modoLivreCheck = document.getElementById("modo-livre");
    const MODO_LIVRE_CHAVE = "ficha-modo-livre:" + @json($editando ? (int) $personagem->id : 'nova');

    // ==================== Histórico de rolagens (por ficha, neste aparelho) ====================
    (function(){
        const CHAVE = "ficha-rolagens:" + @json($editando ? (int) $personagem->id : 'nova');
        const MAX = 100, VISIVEIS = 5;
        const lista = document.getElementById("historico-rolagens-lista");
        const vazio = document.getElementById("historico-rolagens-vazio");
        const mais = document.getElementById("historico-rolagens-mais");
        let tudo = false;
        const ler = () => { try { return JSON.parse(localStorage.getItem(CHAVE)) || []; } catch(e) { return []; } };
        const gravar = l => { try { localStorage.setItem(CHAVE, JSON.stringify(l.slice(0, MAX))); } catch(e) {} };
        const esc = s => String(s == null ? "" : s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
        function desenhar(){
            if(!lista) return;
            const l = ler();
            const ver = tudo ? l : l.slice(0, VISIVEIS);
            lista.innerHTML = ver.map((x, k) => '<li><span class="hr-hora">' + esc(x.h) + '</span>' +
                '<span class="hr-rotulo">' + esc(x.r) + '</span><strong class="hr-total">' + esc(x.v) + '</strong>' +
                (x.d ? '<span class="hr-detalhe">' + esc(x.d) + '</span>' : '') +
                '<button type="button" class="hr-excluir" data-k="' + k + '" title="Excluir esta rolagem" aria-label="Excluir esta rolagem">×</button></li>').join("");
            vazio.hidden = l.length > 0;
            mais.hidden = l.length <= VISIVEIS;
            mais.textContent = tudo ? "Ver menos" : "Ver mais (" + (l.length - VISIVEIS) + ")";
        }
        window.registrarRolagem = function(rotulo, total, detalhe){
            const agora = new Date();
            const l = ler();
            l.unshift({ h: String(agora.getHours()).padStart(2, "0") + ":" + String(agora.getMinutes()).padStart(2, "0"), r: rotulo, v: total, d: detalhe || "" });
            gravar(l);
            desenhar();
        };
        if(lista){
            lista.addEventListener("click", e => {
                const b = e.target.closest(".hr-excluir");
                if(!b) return;
                const l = ler();
                l.splice(Number(b.dataset.k), 1);
                gravar(l);
                desenhar();
            });
            mais.addEventListener("click", () => { tudo = !tudo; desenhar(); });
            document.getElementById("historico-rolagens-limpar").addEventListener("click", () => {
                if(confirm("Apagar todo o histórico de rolagens desta ficha?")){ gravar([]); desenhar(); }
            });
            desenhar();
        }
    })();
    function modoLivre(){ return !!(modoLivreCheck && modoLivreCheck.checked); }
    if(modoLivreCheck){
        try { modoLivreCheck.checked = localStorage.getItem(MODO_LIVRE_CHAVE) === "1"; } catch(e) {}
        const refletirModoLivre = () => {
            modoLivreCheck.closest(".modo-livre").classList.toggle("ativo", modoLivreCheck.checked);
            const pl = document.getElementById("pontos-livre");
            if(pl) pl.hidden = !modoLivreCheck.checked;
            // no modo livre o saldo é definido direto, então o campo "Pontos extras" de habilidades some (seria redundante)
            const extrasHab = document.getElementById("pontos-extras-habilidades");
            const blocoExtras = extrasHab ? extrasHab.closest(".pontos-extras") : null;
            if(blocoExtras) blocoExtras.hidden = modoLivreCheck.checked;
        };
        refletirModoLivre();
        modoLivreCheck.addEventListener("change", function(){
            try { localStorage.setItem(MODO_LIVRE_CHAVE, this.checked ? "1" : "0"); } catch(e) {}
            refletirModoLivre();
        });
    }

    // ---- Modo "ficha já existente": definir direto os pontos de habilidade disponíveis ----
    // O saldo é nível × BE + bônus + extras − gastos; o único termo livre é "pontos extras",
    // então o valor digitado é convertido no extra necessário (e salvo com a ficha).
    const pontosLivreValor = document.getElementById("pontos-livre-valor");
    if(pontosLivreValor){
        pontosLivreValor.addEventListener("focus", function(){
            if(this.value === "") this.value = pontosHabilidadeRestantes;
        });
        const aplicarPontosLivre = function(){
            const alvo = parseInt(pontosLivreValor.value, 10);
            if(isNaN(alvo)){ mostrarPopup("Digite quantos pontos de habilidade devem ficar disponíveis."); return; }
            // O ajuste é a diferença entre o saldo pedido e o saldo calculado (sem ajuste); pode ser negativo
            const bonus = (profissao.value !== "Selecione") ? 30 : 15;
            const base = (Number(nivelMundano.value || 1) * Number(be.value || 0)) + bonus + extraHabilidades - pontosHabilidadeGastos;
            ajusteHabilidades = alvo - base;
            const campoAjuste = document.getElementById("pontos-habilidade-ajuste");
            campoAjuste.value = ajusteHabilidades;
            pontosHabilidadeRestantes = alvo;
            pontosHabilidadeRestantesTexto.textContent = "Pontos a distribuir: " + pontosHabilidadeRestantes;
            campoAjuste.dispatchEvent(new Event("input", { bubbles: true })); // dispara o salvamento automático
            mostrarPopup("Pontos a distribuir definidos em " + alvo + ". O ajuste (" + (ajusteHabilidades >= 0 ? "+" : "") + ajusteHabilidades + ") fica salvo na ficha e não mexe nos pontos extras.");
        };
        document.getElementById("pontos-livre-aplicar").addEventListener("click", aplicarPontosLivre);
        pontosLivreValor.addEventListener("keydown", function(e){ if(e.key === "Enter"){ e.preventDefault(); aplicarPontosLivre(); } });
    }
    const modCarisma = document.getElementById("mod-carisma");

    // ---- Mods de atributo ajustados à mão (modo "ficha já existente") ----
    // Um mod editado deixa de seguir o atributo (ex.: Força 75 com mod 10) até ser apagado ou igualado ao automático.
    // Ganho de vida/aura por nível, BE e mods das habilidades leem o valor que estiver no campo.
    let modsManuais = @json((object) $modsManuais);
    const campoModsManuais = document.getElementById("mods-atributos");
    const MODS_POR_ATRIBUTO = { forca: modForca, constituicao: modConstituicao, destreza: modDestreza, conhecimento: modConhecimento, carisma: modCarisma, percepcao: modPercepcao, alma: modAlma };
    function sincronizarModsManuais(){ if(campoModsManuais) campoModsManuais.value = JSON.stringify(modsManuais); }
    // Regra dos modificadores: primeiro dígito do valor (80 => 8); de 1 a 9 o mod é sempre 1; 0 ou vazio => 0
    function modDeAtributo(valor){
        const v = Number(valor);
        if(isNaN(v)) return 0;
        if(v >= 10) return Math.floor(v / 10);
        return v >= 1 ? 1 : 0;
    }
    function modAutomatico(inputAtributo){ return inputAtributo.value === "" ? "" : String(modDeAtributo(inputAtributo.value)); }
    // Bônus dos atributos nas habilidades cresce com o nível: ×1 (1–5), ×2 (6–10), ×3 (11–15), ×4 (16–20), +1 a cada 5 níveis
    function multiplicadorNivel(){
        const n = Number(nivelMundano.value) || 1;
        return Math.max(1, Math.ceil(n / 5));
    }
    // chamado pelos listeners dos atributos: só recalcula o mod se ele não foi ajustado à mão
    function definirModAuto(atributo, inputAtributo, inputMod){
        if(modsManuais[atributo] !== undefined) return;
        inputMod.value = modAutomatico(inputAtributo);
    }
    function refletirModsEditaveis(){
        const livre = modoLivre();
        Object.values(MODS_POR_ATRIBUTO).forEach(m => { m.readOnly = !livre; m.classList.toggle("mod-editavel", livre); });
    }
    Object.entries(MODS_POR_ATRIBUTO).forEach(([atributo, inputMod]) => {
        inputMod.classList.toggle("mod-manual", modsManuais[atributo] !== undefined);
        inputMod.addEventListener("input", function(){
            if(this.readOnly) return;
            this.value = this.value.replace(/[^\d-]/g, "").replace(/(?!^)-/g, "").slice(0, 3);
            const inputAtributo = document.getElementById(atributo);
            if(this.value === "" || this.value === "-" || this.value === modAutomatico(inputAtributo)){
                delete modsManuais[atributo]; // volta a seguir o atributo
                if(this.value === "" || this.value === "-") this.value = modAutomatico(inputAtributo);
            } else {
                modsManuais[atributo] = Number(this.value);
            }
            this.classList.toggle("mod-manual", modsManuais[atributo] !== undefined);
            sincronizarModsManuais();
            atualizarHabilidadesMod();
        });
    });
    refletirModsEditaveis();
    if(modoLivreCheck) modoLivreCheck.addEventListener("change", refletirModsEditaveis);
    const pontosRestantesTexto = document.getElementById("pontos-restantes-texto");

    // ---- PONTOS EXTRAS (ganhos de fontes externas) ----
    // Somam ao total base de atributos (300) e ao total de pontos de habilidade.
    let extraAtributos   = {{ $extraAtributos }};
    let extraHabilidades = {{ $extraHabilidades }};
    let ajusteHabilidades = {{ $ajusteHabilidades }}; // ajuste manual (modo "ficha já existente")

    // ---- PONTOS DE ATRIBUTOS: total e único ponto de verdade ----
    const PONTOS_BASE_ATRIBUTOS = 300;
    let PONTOS_TOTAL_ATRIBUTOS = PONTOS_BASE_ATRIBUTOS + extraAtributos;
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

    // ---- Distribuição de níveis por profissão ----
    // O estado vive em um array (um item por nível) espelhado num campo oculto
    // (#niveis_profissao) que vai junto com o formulário ao salvar.
    const PROFISSOES = ["Criminoso","Lutador","Intelectual","Comunicador","Construtor"];
    const niveisProfissaoInput = document.getElementById("niveis_profissao");
    let niveisProfissao = @json($niveisProfissaoSalvos);
    if(!Array.isArray(niveisProfissao)) niveisProfissao = [];

    // Ajusta o array à quantidade de níveis atual e atualiza o campo oculto
    function sincronizarNiveisProfissao(){
        const qtd = Math.max(1, Number(nivelMundano.value) || 1);
        const padrao = PROFISSOES.includes(profissao.value) ? profissao.value : PROFISSOES[0];
        niveisProfissao = niveisProfissao.slice(0, qtd);
        while(niveisProfissao.length < qtd) niveisProfissao.push(padrao);
        niveisProfissaoInput.value = JSON.stringify(niveisProfissao);
    }

    function renderizarDistribuicaoNiveis(){
        sincronizarNiveisProfissao();
        camposDistribuicaoNiveis.innerHTML = "";
        niveisProfissao.forEach((prof, i) => {
            const campo = document.createElement("div");
            campo.className = "campo";

            const label = document.createElement("label");
            label.textContent = "Nível " + (i + 1);

            const select = document.createElement("select");
            select.style.marginTop = "5px";
            PROFISSOES.forEach(p => {
                const o = new Option(p, p);
                o.selected = (p === prof);
                select.add(o);
            });
            select.addEventListener("change", () => {
                niveisProfissao[i] = select.value;
                sincronizarNiveisProfissao();
            });

            campo.append(label, select);
            camposDistribuicaoNiveis.appendChild(campo);
        });
    }

    distribuirNiveis.addEventListener("click", function(){
        renderizarDistribuicaoNiveis();
        distribuirNiveisPopup.classList.add("ativo");
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

    /* ---------- Valores extras das habilidades ----------
       Cada habilidade ganha um botão "+N": abre um painel com a lista de origens (de onde vem cada ponto extra), em que o
       jogador soma, tira, renomeia, apaga e adiciona. Não gastam pontos nem têm limite; entram nas rolagens. */
    (function(){
        const campo = document.getElementById("habilidades-extras");
        if(!campo) return;
        let extras = {};
        try { extras = JSON.parse(campo.value || "{}") || {}; } catch(e) { extras = {}; }
        if(Array.isArray(extras)) extras = {};
        const somente = {{ $somenteLeitura ? 'true' : 'false' }};
        const escH = s => String(s == null ? "" : s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
        const soma = chave => (extras[chave] || []).reduce((t, e) => t + (parseInt(e.valor, 10) || 0), 0);
        window.extraHabilidade = input => input ? soma(input.name) : 0;
        const salvar = () => { campo.value = JSON.stringify(extras); };
        let aberto = null; // { chave, botao, painel }
        function rotulo(botao, chave){
            const n = soma(chave), q = (extras[chave] || []).length;
            botao.textContent = n ? (n > 0 ? "+" : "") + n : "+";
            botao.classList.toggle("tem", q > 0);
            botao.title = q ? (extras[chave] || []).map(e => (e.origem || "sem origem") + ": " + ((e.valor > 0 ? "+" : "") + (parseInt(e.valor, 10) || 0))).join("\n") : "Adicionar valores extras a esta habilidade";
        }
        function desenhar(){
            if(!aberto) return;
            const { chave, painel } = aberto, lista = extras[chave] || [];
            painel.innerHTML = '<div class="hab-extras-cab"><strong>Extras de ' + escH(aberto.nome) + '</strong><span>Total: <b>' + ((soma(chave) > 0 ? "+" : "") + soma(chave)) + '</b></span></div>' +
                (lista.length ? lista.map((e, i) =>
                    '<div class="hab-extras-item" data-i="' + i + '">' +
                        '<input type="text" class="hab-extras-origem" maxlength="80" placeholder="De onde vem? (talento, item, bênção...)" value="' + escH(e.origem) + '"' + (somente ? " disabled" : "") + '>' +
                        '<div class="hab-extras-passo">' + (somente ? "" : '<button type="button" data-passo="-1" aria-label="Tirar 1">−</button>') +
                            '<input type="number" class="hab-extras-valor" value="' + (parseInt(e.valor, 10) || 0) + '"' + (somente ? " disabled" : "") + ' aria-label="Valor">' +
                            (somente ? "" : '<button type="button" data-passo="1" aria-label="Somar 1">+</button>') + '</div>' +
                        (somente ? "" : '<button type="button" class="hab-extras-apagar" data-apagar aria-label="Apagar esta origem">✕</button>') +
                    '</div>').join("") : '<p class="hab-extras-vazio">Nenhum valor extra nesta habilidade.</p>') +
                (somente ? "" : '<div class="hab-extras-acoes"><button type="button" class="button" data-novo>+ Adicionar origem</button><button type="button" class="button" data-fechar>Fechar</button></div>');
        }
        function fechar(){ if(aberto){ aberto.painel.remove(); aberto.botao.classList.remove("aberto"); aberto = null; } }
        document.querySelectorAll(".habilidade-linha .habilidade-valor").forEach(input => {
            const linha = input.closest(".habilidade-linha"), chave = input.name;
            const botao = document.createElement("button");
            botao.type = "button"; botao.className = "hab-extra-botao";
            input.insertAdjacentElement("afterend", botao);
            rotulo(botao, chave);
            botao.addEventListener("click", ev => {
                ev.preventDefault();
                const era = aberto && aberto.chave === chave; fechar(); if(era) return;
                const painel = document.createElement("div"); painel.className = "hab-extras-painel";
                linha.insertAdjacentElement("afterend", painel);
                aberto = { chave, botao, painel, nome: (linha.querySelector(".habilidade-nome") || {}).textContent || chave };
                botao.classList.add("aberto");
                if(!somente && !(extras[chave] || []).length){ extras[chave] = [{ origem: "", valor: 1 }]; salvar(); rotulo(botao, chave); }
                desenhar();
                const o = painel.querySelector(".hab-extras-origem"); if(o && !o.value) o.focus();
                painel.addEventListener("input", e => {
                    const item = e.target.closest(".hab-extras-item"); if(!item) return;
                    const reg = extras[chave][+item.dataset.i];
                    if(e.target.classList.contains("hab-extras-origem")) reg.origem = e.target.value;
                    if(e.target.classList.contains("hab-extras-valor")) { reg.valor = parseInt(e.target.value, 10) || 0; painel.querySelector(".hab-extras-cab b").textContent = (soma(chave) > 0 ? "+" : "") + soma(chave); }
                    salvar(); rotulo(botao, chave);
                });
                painel.addEventListener("click", e => {
                    const item = e.target.closest(".hab-extras-item");
                    if(e.target.closest("[data-passo]") && item){ const reg = extras[chave][+item.dataset.i]; reg.valor = (parseInt(reg.valor, 10) || 0) + (+e.target.closest("[data-passo]").dataset.passo); }
                    else if(e.target.closest("[data-apagar]") && item){ extras[chave].splice(+item.dataset.i, 1); if(!extras[chave].length) delete extras[chave]; }
                    else if(e.target.closest("[data-novo]")){ (extras[chave] = extras[chave] || []).push({ origem: "", valor: 1 }); }
                    else if(e.target.closest("[data-fechar]")){ fechar(); return; }
                    else return;
                    salvar(); rotulo(botao, chave); desenhar();
                    const os = painel.querySelectorAll(".hab-extras-origem"); if(e.target.closest("[data-novo]") && os.length) os[os.length - 1].focus();
                });
            });
        });
        document.addEventListener("keydown", e => { if(e.key === "Escape") fechar(); });
    })();

    // Estado da rolagem aberta. Os listeners do popup são registrados UMA vez, fora do loop:
    // antes, cada abertura acumulava um novo listener e a rolagem somava valores de habilidades antigas.
    let habilidadeRolagem = null; // { input, mod }
    const numRolagem = v => Number(v) || 0;
    function reducaoRolagem(){
        if(!habilidadeRolagem) return 0;
        return numRolagem(habilidadeRolagem.input.value) + numRolagem(habilidadeRolagem.mod()) + numRolagem(vantagensExtras.value)
            + Math.floor(((window.amaBonus || {})[habilidadeRolagem.chave]) || 0) // estudos na AMA
            + (window.extraHabilidade ? window.extraHabilidade(habilidadeRolagem.input) : 0); // valores extras da habilidade
    }
    function atualizarReducaoRolagem(){
        aplicacaoFinal.textContent = reducaoRolagem();
    }
    rolarHabilidadeBotao.forEach(botao =>{
        botao.addEventListener("click", function(){
            const chave = botao.dataset.habilidade;
            const inputHabilidade = document.getElementById(chave);
            // habilidades sem atributo vinculado (ex.: Maldição, Explosivos) rolam com mod 0 em vez de quebrar
            const modHabilidade = () => (vinculoHabilidadeAtributo[chave] ? vinculoHabilidadeAtributo[chave]() : 0) * multiplicadorNivel();
            habilidadeRolagem = { input: inputHabilidade, mod: modHabilidade, chave: chave };
            const m = numRolagem(modHabilidade());
            const extraH = window.extraHabilidade ? window.extraHabilidade(inputHabilidade) : 0;
            valorHabilidadePopup.textContent = (inputHabilidade.value || "0") + (extraH ? " (" + (extraH > 0 ? "+" : "") + extraH + " extra)" : "");
            tituloRolagemHabilidade.textContent = "Rolar: " + (nomesHabilidades[chave] || chave);
            tituloModHabilidade.textContent = (m >= 0 ? "+" : "") + m;
            resultadoNatural.textContent = "?";
            resultadoFinal.textContent = "?";
            atualizarReducaoRolagem();
            rolagemHabilidadeCard.classList.add("ativo");
        });
    });
    vantagensExtras.addEventListener("input", atualizarReducaoRolagem);
    rolarHabilidade.addEventListener("click", function(){
        if(!habilidadeRolagem) return;
        const dadoNatural = rolarDado(100);
        resultadoNatural.textContent = dadoNatural;
        resultadoFinal.textContent = dadoNatural - reducaoRolagem();
        registrarRolagem(tituloRolagemHabilidade.textContent.replace(/^Rolar:\s*/, ""), dadoNatural - reducaoRolagem(), "d100 " + dadoNatural + " − " + reducaoRolagem());
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
        registrarRolagem("Pontos de Vida", resultado, "");
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
        registrarRolagem("Aura", resultado, "");
        rolagemAura.classList.add("usado");
    });


    rolagemSanidade.addEventListener("click", function(){
        let lados = 100;
        let resultado = rolarDado(lados);
        while(resultado<40 || resultado>75){
            resultado = rolarDado(lados);
        }
        resultadoDado.textContent = "Sanidade: " + resultado;
        registrarRolagem("Sanidade", resultado, "");
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
        registrarRolagem("Sorte", resultado, "");
        sorte.value = resultado;
        nomeSorte.style="color: var(--tinta-fraca);";
        rolagemSorte.classList.add("usado");
    });

    // Sorte também pode ser digitada à mão (ex.: ficha já existente)
    sorte.addEventListener("input", function(){
        nomeSorte.style.color = this.value === "" ? "rgba(189, 3, 3, 0.658)" : "var(--tinta-fraca)";
    });

    // ---- Descanso curto / longo: recupera os atributos vitais (regras rápidas de descanso) ----
    // O texto do span é a fonte da verdade; o vitais.js observa a mudança e atualiza barra e campos ocultos.
    function lerVitalSpan(span){
        const p = span.textContent.split("/").map(v => parseInt(v.trim(), 10));
        return { atual: p[0], max: p[1] };
    }
    function recuperarVital(span, nome, ganho){
        const v = lerVitalSpan(span);
        if(isNaN(v.atual) || isNaN(v.max)) return nome + ": não definido";
        const novo = Math.min(v.max, v.atual + ganho);
        span.textContent = novo + " / " + v.max;
        return nome + " +" + (novo - v.atual) + " (" + novo + "/" + v.max + ")";
    }
    function somaDados(qtd, lados){ let s = 0; for(let i = 0; i < qtd; i++) s += rolarDado(lados); return s; }
    function descansar(longo){
        const linhas = [
            recuperarVital(vida, "Vida", longo ? rolarDado(3) : 1),
            recuperarVital(sanidade, "Sanidade", longo ? rolarDado(4) : rolarDado(2)),
            recuperarVital(aura, "Aura", longo ? somaDados(4, 20) : rolarDado(20))
        ];
        mostrarPopup((longo ? "Descanso longo (6–8 h): " : "Descanso curto (1–2 h): ") + linhas.join(", ") +
            ". Também voltam 10 pontos de atributo perdidos (sem sequela permanente): ajuste os atributos à mão.");
    }
    document.getElementById("descanso-curto").addEventListener("click", () => descansar(false));
    document.getElementById("descanso-longo").addEventListener("click", () => descansar(true));
    
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

    // "Espalhar Pontos": o saldo é sempre recalculado a partir dos valores reais dos campos.
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

        pontosHabilidadeRestantes = (Number(nivelMundano.value || 1) * Number(be.value || 0)) + 15 + extraHabilidades + ajusteHabilidades;
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

                // mod do atributo vinculado × fator do nível mundano
                const valorModificador =
                    funcaoModificador() * multiplicadorNivel();

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

    // Cada listener abaixo só valida o novo valor e chama
    // recalcularPontosAtributos(), que recalcula o saldo do zero
    // a partir da soma atual dos 7 campos (sem estado duplicado).

    forca.addEventListener("input", () => {
        const somaOutros =
            Number(destreza.value || 0) + Number(constituicao.value || 0) +
            Number(conhecimento.value || 0) + Number(percepcao.value || 0) +
            Number(alma.value || 0) + Number(carisma.value || 0);

        if(forca.value === ""){
            modForca.value = "";
        } else {
            const valorAtual = Number(forca.value);

            if(!modoLivre() && (somaOutros + valorAtual) > PONTOS_TOTAL_ATRIBUTOS){
                mostrarPopup("Pontos insuficientes! Você tem apenas " + (PONTOS_TOTAL_ATRIBUTOS - somaOutros) + " pontos disponíveis.");
                forca.value = valorForcaTexto;
                definirModAuto("forca", forca, modForca);
                recalcularPontosAtributos();
                atualizarHabilidadesMod();
                return;
            }

            valorForcaTexto = forca.value;
        }

        definirModAuto("forca", forca, modForca);
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

            if(!modoLivre() && (somaOutros + valorAtual) > PONTOS_TOTAL_ATRIBUTOS){
                mostrarPopup("Pontos insuficientes! Você tem apenas " + (PONTOS_TOTAL_ATRIBUTOS - somaOutros) + " pontos disponíveis.");
                destreza.value = valorDestrezaTexto;
                definirModAuto("destreza", destreza, modDestreza);
                recalcularPontosAtributos();
                atualizarHabilidadesMod();
                return;
            }

            valorDestrezaTexto = destreza.value;
        }

        definirModAuto("destreza", destreza, modDestreza);
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

            if(!modoLivre() && (somaOutros + valorAtual) > PONTOS_TOTAL_ATRIBUTOS){
                mostrarPopup("Pontos insuficientes! Você tem apenas " + (PONTOS_TOTAL_ATRIBUTOS - somaOutros) + " pontos disponíveis.");
                constituicao.value = valorConstituicaoTexto;
                definirModAuto("constituicao", constituicao, modConstituicao);
                recalcularPontosAtributos();
                atualizarHabilidadesMod();
                return;
            }

            valorConstituicaoTexto = constituicao.value;
        }

        definirModAuto("constituicao", constituicao, modConstituicao);
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

            if(!modoLivre() && (somaOutros + valorAtual) > PONTOS_TOTAL_ATRIBUTOS){
                mostrarPopup("Pontos insuficientes! Você tem apenas " + (PONTOS_TOTAL_ATRIBUTOS - somaOutros) + " pontos disponíveis.");
                conhecimento.value = valorConhecimentoTexto;
                definirModAuto("conhecimento", conhecimento, modConhecimento);
                if(be.value === "") be.value = modConhecimento.value;
                recalcularPontosAtributos();
                atualizarHabilidadesMod();
                return;
            }

            valorConhecimentoTexto = conhecimento.value;
        }

        const modAntes = modConhecimento.value;
        definirModAuto("conhecimento", conhecimento, modConhecimento);
        // o BE só acompanha o Conhecimento se não foi ajustado à mão (ou está vazio)
        if(be.value === "" || be.value === modAntes) be.value = modConhecimento.value;
        recalcularPontosHabilidade();
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

            if(!modoLivre() && (somaOutros + valorAtual) > PONTOS_TOTAL_ATRIBUTOS){
                mostrarPopup("Pontos insuficientes! Você tem apenas " + (PONTOS_TOTAL_ATRIBUTOS - somaOutros) + " pontos disponíveis.");
                percepcao.value = valorPercepcaoTexto;
                definirModAuto("percepcao", percepcao, modPercepcao);
                recalcularPontosAtributos();
                atualizarHabilidadesMod();
                return;
            }

            valorPercepcaoTexto = percepcao.value;
        }

        definirModAuto("percepcao", percepcao, modPercepcao);
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

            if(!modoLivre() && (somaOutros + valorAtual) > PONTOS_TOTAL_ATRIBUTOS){
                mostrarPopup("Pontos insuficientes! Você tem apenas " + (PONTOS_TOTAL_ATRIBUTOS - somaOutros) + " pontos disponíveis.");
                alma.value = valorAlmaTexto;
                definirModAuto("alma", alma, modAlma);
                recalcularPontosAtributos();
                atualizarHabilidadesMod();
                return;
            }

            valorAlmaTexto = alma.value;
        }

        definirModAuto("alma", alma, modAlma);
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

            if(!modoLivre() && (somaOutros + valorAtual) > PONTOS_TOTAL_ATRIBUTOS){
                mostrarPopup("Pontos insuficientes! Você tem apenas " + (PONTOS_TOTAL_ATRIBUTOS - somaOutros) + " pontos disponíveis.");
                carisma.value = valorCarismaTexto;
                definirModAuto("carisma", carisma, modCarisma);
                recalcularPontosAtributos();
                atualizarHabilidadesMod();
                return;
            }

            valorCarismaTexto = carisma.value;
        }

        definirModAuto("carisma", carisma, modCarisma);
        recalcularPontosAtributos();
        atualizarHabilidadesMod();
    });

    // Porte físico e idade não mexem em mais nada da ficha (nível, XP, habilidades, atributos, BE e vitais ficam como estão):
    // o jogador ajusta à mão o que o porte mudar. Escolher um porte só libera a rolagem de Vida e a Experiência Mundana.
    let primeiraMudanca = porteFisico.value && porteFisico.value !== "Selecione" ? 1 : 0;
    porteFisico.addEventListener("change", function(){
        if(porteFisico.value && porteFisico.value !== "Selecione"){
            primeiraMudanca = 1;
            experienciaMundana.removeAttribute("readonly");
        }
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

    // Um atributo vital conta como "definido" quando foi rolado (botão marcado como "usado")
    // OU quando já tem valores preenchidos (atual e máximo numéricos, máximo > 0),
    // mesmo que tenham sido digitados/ajustados à mão, sem rolagem de dados.
    function vitalDefinido(botaoRolagem, spanId, atualId, maximoId){
        if(botaoRolagem.classList.contains("usado")){
            return true;
        }

        const partes = document.getElementById(spanId).textContent
            .split("/")
            .map(v => parseInt(v.trim(), 10));

        let atual = partes[0];
        let maximo = partes[1];

        // se o texto da barra ainda está como "? / ?", confere os campos ocultos
        if(isNaN(atual) || isNaN(maximo)){
            atual = parseInt(document.getElementById(atualId).value, 10);
            maximo = parseInt(document.getElementById(maximoId).value, 10);
        }

        return !isNaN(atual) && !isNaN(maximo) && maximo > 0;
    }

    nivelMundano.addEventListener("input", () => {

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
            (novoNivel * valorBE) + bonusInicial + extraHabilidades + ajusteHabilidades;

        if (!modoLivre() && novoNivel > nivelAnterior) {

            let faltando = [];

            if (!vitalDefinido(rolagemVida, "vida", "vida_atual", "vida_maxima")) {
                faltando.push("Vida");
            }

            if (!vitalDefinido(rolagemSanidade, "sanidade", "sanidade_atual", "sanidade_maxima")) {
                faltando.push("Sanidade");
            }

            if (!vitalDefinido(rolagemAura, "aura", "aura_atual", "aura_maxima")) {
                faltando.push("Aura");
            }

            if (faltando.length > 0) {

                mostrarPopup(
                    "Você precisa rolar ou preencher " +
                    faltando.join(", ") +
                    " antes de aumentar o Nível Mundano."
                );

                nivelMundano.value = nivelAnterior;

                return;
            }
        }

        if (
            !modoLivre() &&
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

        // A distribuição de níveis acompanha o novo nível mundano
        // (preserva as escolhas já feitas e completa/corta o restante)
        sincronizarNiveisProfissao();

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
    // O bônus inicial de pontos de habilidade é 30 quando o agente já tem uma
    // profissão escolhida (15 base + 15 da profissão) e 15 quando não tem.
    let bonusInicialSalvo = (profissao.value !== "Selecione") ? 30 : 15;
    let pontosHabilidadeRestantes = (Number(nivelMundano.value) * Number(be.value || 0)) + bonusInicialSalvo + extraHabilidades + ajusteHabilidades - pontosHabilidadeGastosSalvos;
    pontosHabilidadeGastos = pontosHabilidadeGastosSalvos;

    // ---- BE editável: recalcula o saldo de habilidades do zero, preservando os pontos já gastos ----
    function recalcularPontosHabilidade(){
        const bonus = (profissao.value !== "Selecione") ? 30 : 15;
        pontosHabilidadeRestantes = (Number(nivelMundano.value || 1) * Number(be.value || 0)) + bonus + extraHabilidades + ajusteHabilidades - pontosHabilidadeGastos;
        pontosHabilidadeRestantesTexto.textContent = "Pontos a distribuir: " + pontosHabilidadeRestantes;
    }
    be.addEventListener("input", function(){
        if(this.value !== "" && Number(this.value) < 0) this.value = 0;
        recalcularPontosHabilidade();
    });
    pontosHabilidadeRestantesTexto.textContent = "Pontos a distribuir: " + pontosHabilidadeRestantes;
    experienciaMundana.value = xpAtual || "";
    atualizarExperienciaTexto();

    // Preenche o campo oculto da distribuição de níveis com o estado inicial
    // (ficha salva ou ficha nova), mesmo que o popup nunca seja aberto.
    sincronizarNiveisProfissao();

    // ---- Campos de pontos extras ----
    const campoExtraAtributos   = document.getElementById("pontos-extras-atributos");
    const campoExtraHabilidades = document.getElementById("pontos-extras-habilidades");

    campoExtraAtributos.addEventListener("input", function(){
        this.value = this.value.replace(/\D/g, "").slice(0, 3);
        const novo = Number(this.value || 0);

        const gasto = (PONTOS_TOTAL_ATRIBUTOS - pontosRestantes);
        if(!modoLivre() && PONTOS_BASE_ATRIBUTOS + novo < gasto){
            mostrarPopup("Você já gastou " + gasto + " pontos em atributos. Reduza algum atributo antes de diminuir os pontos extras.");
            this.value = extraAtributos || "";
            return;
        }

        extraAtributos = novo;
        PONTOS_TOTAL_ATRIBUTOS = PONTOS_BASE_ATRIBUTOS + extraAtributos;
        recalcularPontosAtributos();
    });

    campoExtraHabilidades.addEventListener("input", function(){
        this.value = this.value.replace(/\D/g, "").slice(0, 5);
        const novo = Number(this.value || 0);
        const delta = novo - extraHabilidades;

        if(!modoLivre() && pontosHabilidadeRestantes + delta < 0){
            mostrarPopup("Você já gastou esses pontos em habilidades. Reduza alguma habilidade antes de diminuir os pontos extras.");
            this.value = extraHabilidades || "";
            return;
        }

        extraHabilidades = novo;
        pontosHabilidadeRestantes += delta;
        pontosHabilidadeRestantesTexto.textContent = "Pontos a distribuir: " + pontosHabilidadeRestantes;
    });

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

        "maldicao": () => Number(modAlma.value),
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
        "explosivos": () => Number(modConhecimento.value),

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

    // Ao abrir a ficha, os mods das habilidades já refletem os mods dos atributos
    // (antes só eram calculados quando algum atributo era editado, então ficavam em +0 ao carregar)
    atualizarHabilidadesMod();
    // o fator por nível muda quando o nível mundano muda
    nivelMundano.addEventListener("input", atualizarHabilidadesMod);

    habilidades.forEach(input => {
        input.dataset.anterior = "0";
        input.addEventListener("input", function(){
            this.value = this.value.replace(/\D/g, "").slice(0, 4);
            let anterior = Number(this.dataset.anterior);
            let novoValor = 0;
            pontosHabilidadeRestantes += anterior;
            pontosHabilidadeGastos -= anterior;
            if(this.value === ""){
                this.dataset.anterior = "0";
            }else {
                novoValor = Number(this.value);
            }
            // pontos de habilidade livres: o jogador ajusta como quiser; o contador só mostra o saldo (pode ficar negativo)
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

    //Talentos Mundanos (painel próprio) e Técnicas de arte (Hexomancia / Fisiomancia em abas). Mesma mecânica, listas separadas.
    const talentosMundanosCard = document.getElementById("talentos-mundanos-card");
    const talentosArteCard = document.getElementById("talentos-arte-card");
    document.getElementById("talentos-mundanos").addEventListener("click", () => talentosMundanosCard.classList.add("ativo"));
    document.getElementById("fechar-talentos").addEventListener("click", () => talentosMundanosCard.classList.remove("ativo"));
    document.getElementById("talentos-arte-abrir").addEventListener("click", () => talentosArteCard.classList.add("ativo"));
    document.getElementById("fechar-talentos-arte").addEventListener("click", () => talentosArteCard.classList.remove("ativo"));

    // ---- Talentos Mundanos: custo de Aura, ativação e pastas ----
    // Ativar gasta a Aura na hora. Desativar NÃO devolve a Aura (reativar gasta de novo).
    function gastarAuraTalento(custo){
        if(custo <= 0) return true;
        const auraEl = document.getElementById("aura");
        const partes = auraEl.textContent.split("/").map(v => parseInt(v.trim(), 10));
        const atual = partes[0];
        const maximo = partes[1];
        if(isNaN(atual) || isNaN(maximo)){
            mostrarPopup("Defina sua Aura antes de ativar um talento.");
            return false;
        }
        // Pode ativar mesmo sem Aura suficiente: o valor fica negativo (exibido em vermelho)
        const novo = atual - custo;
        auraEl.textContent = novo + " / " + maximo;
        const campo = document.getElementById("aura_atual");
        campo.value = novo;

        const barra = document.querySelector(".barra-aura");
        if(barra && maximo > 0){
            barra.style.width = Math.max(0, Math.min(100, (novo / maximo) * 100)) + "%";
        }
        campo.dispatchEvent(new Event("input", { bubbles: true }));
        return true;
    }

    // Monta uma lista de talentos (cfg: tipo, pastaTipo, lista, barra, botaoAdicionar, vazio, contador, aoMudar)
    function montarTalentos(cfg){
    const listaTalentos = cfg.lista;
    const adicionarTalentos = cfg.botaoAdicionar;

    function cardsTalentos(){
        return Array.from(listaTalentos.querySelectorAll(".talento-card"));
    }

    // Hexomancia/Fisiomancia (ehArte) ganham eficiência e aprimoramentos; mundano fica como era.
    const ehArte = cfg.tipo !== "mundano";
    const nomeItem = ehArte ? "técnica" : "talento";

    function custoBrutoTalento(card){
        return Math.max(0, parseInt(card.querySelector(".talento-custo").value, 10) || 0);
    }
    function eficienciaTalento(card){
        return ehArte ? Math.max(0, parseInt(card.dataset.eficiencia, 10) || 0) : 0;
    }
    function aprimsTalento(card){
        if(!ehArte) return [];
        try { const a = JSON.parse(card.dataset.aprims || "[]"); return Array.isArray(a) ? a : []; } catch(err){ return []; }
    }
    // Item no formato do objeto compartilhado Aprimoramentos (custo, eficiência, aprimoramentos, gerais, grátis)
    function itemDoCard(card){
        let g = {};
        try { g = JSON.parse(card.dataset.gerais || "{}"); } catch(err){ g = {}; }
        return Aprimoramentos.normalizarItem({
            custo: custoBrutoTalento(card),
            eficiencia: eficienciaTalento(card),
            aprimoramentos: aprimsTalento(card),
            gerais: g,
            gratis_disponiveis: card.dataset.gratisDisp,
            nivel_req: card.dataset.nivelReq,
            nivel_atual: nivelMaldicaoFicha()
        });
    }
    // nível de maldição do personagem (campo "Nível de Hexomante" da aba de maldições)
    function nivelMaldicaoFicha(){
        const campo = document.getElementById("nivel-hexomante");
        return Math.max(0, parseInt(campo ? campo.value : 0, 10) || 0);
    }
    function gravarItemCard(card, item){
        card.dataset.aprims = JSON.stringify(item.aprimoramentos);
        card.dataset.gerais = JSON.stringify(item.gerais);
        card.dataset.gratisDisp = String(item.gratis_disponiveis);
    }
    // custo base efetivo = max(0, custo - eficiência) + aprimoramentos (a eficiência não reduz os aprimoramentos)
    function custoDoTalento(card){
        if(!ehArte) return custoBrutoTalento(card);
        return Aprimoramentos.custoTotal(itemDoCard(card));
    }

    function avisarMudancaTalentos(){
        document.getElementById("talentos").dispatchEvent(new Event("input", { bubbles: true }));
    }

    // Lê os campos do modo de edição para o dataset (eficiência e linhas de aprimoramento)
    function lerEdicaoArte(card){
        if(!ehArte) return;
        card.dataset.eficiencia = String(Math.max(0, parseInt(card.querySelector(".talento-eficiencia-input").value, 10) || 0));
        card.dataset.nivelReq = String(Math.max(0, parseInt(card.querySelector(".talento-nivel-input").value, 10) || 0));
        const lista = [];
        card.querySelectorAll(".talento-aprim-form .tecnica-aprim-linha").forEach(function(l){
            const texto = l.querySelector(".tec-aprim-texto").value.trim();
            if(texto === "") return;
            lista.push({
                texto: texto,
                custo: Math.max(0, parseInt(l.querySelector(".tec-aprim-custo-input").value, 10) || 0),
                acumulativo: l.querySelector(".tec-aprim-acum").checked,
                qtd: parseInt(l.dataset.qtd, 10) || 0,
                gratis: parseInt(l.dataset.gratis, 10) || 0
            });
        });
        card.dataset.aprims = JSON.stringify(Aprimoramentos.normalizarLista(lista));
    }

    function linhaAprimTalento(card, a){
        a = a || {};
        const linha = document.createElement("div");
        linha.className = "tecnica-aprim-linha";
        linha.dataset.qtd = String(a.qtd || 0);
        linha.dataset.gratis = String(a.gratis || 0);
        linha.innerHTML = Aprimoramentos.linhaEdicaoHtml();
        linha.querySelector(".tec-aprim-texto").value = a.texto || "";
        linha.querySelector(".tec-aprim-custo-input").value = (a.custo === undefined || a.custo === null || a.custo === "") ? "" : a.custo;
        linha.querySelector(".tec-aprim-acum").checked = !!a.acumulativo;
        card.querySelector(".talento-aprim-form").appendChild(linha);
    }

    // Depois de confirmado: aprimoramentos personalizados e gerais (checkbox ou − n +), com grátis
    function renderizarAprimsTalento(card){
        const item = itemDoCard(card);
        gravarItemCard(card, item);
        Aprimoramentos.redesenhar(card, item);
    }
    // aba "Aprimoramentos" do painel Técnicas criou, mudou ou apagou um guardado: redesenha as técnicas de arte confirmadas
    if(ehArte){
        document.addEventListener("aprimoramentos-guardados", function(){
            cardsTalentos().filter(c => c.dataset.confirmado === "1").forEach(function(card){
                renderizarAprimsTalento(card);
                atualizarVisualTalento(card);
            });
        });
    }

    function textoCustoTalento(card, ativo){
        const s = ehArte ? Aprimoramentos.textoCusto(itemDoCard(card)) : "Custo: " + custoBrutoTalento(card) + " Aura";
        return s + (ativo ? " · Ativo" : "");
    }

    function atualizarVisualTalento(card){
        const ativo = card.dataset.ativo === "1";
        card.classList.toggle("talento-ativo", ativo);
        card.querySelector(".talento-toggle").textContent = ativo ? "Desativar" : "Ativar";
        card.querySelector(".talento-custo-tag").textContent = textoCustoTalento(card, ativo);
    }

    function atualizarSelectsTalentos(){
        cardsTalentos().forEach(function(card){
            card.querySelector(".pasta-select").innerHTML = PastasFicha.opcoes(cfg.pastaTipo, card.dataset.pasta || "");
        });
    }

    function aplicarFiltroTalentos(){
        let visiveis = 0;
        const cards = cardsTalentos();
        cards.forEach(function(card){
            const ok = card.dataset.confirmado !== "1" || PastasFicha.visivel(cfg.pastaTipo, card.dataset.pasta || "");
            card.style.display = ok ? "" : "none";
            if(ok) visiveis++;
        });
        const vazio = cfg.vazio;
        vazio.style.display = visiveis === 0 ? "block" : "none";
        vazio.textContent = cards.length === 0
            ? (ehArte ? "Você ainda não tem técnicas de arte. Clique em “+ Adicionar técnica” para criar a primeira."
                      : "Você ainda não tem talentos. Clique em “+ Adicionar Talento” para criar o primeiro.")
            : (ehArte ? "Nenhuma técnica nesta pasta." : "Nenhum talento nesta pasta.");
    }

    function depoisDeMudarTalentos(){
        const qtd = cardsTalentos().filter(c => c.dataset.confirmado === "1").length;
        cfg.contador.textContent = qtd > 0 ? qtd : "";
        if(cfg.aoMudar) cfg.aoMudar();
        PastasFicha.atualizar(cfg.pastaTipo);
        aplicarFiltroTalentos();
    }

    PastasFicha.registrar(cfg.pastaTipo, {
        barra: cfg.barra,
        usadas: () => cardsTalentos().filter(c => c.dataset.confirmado === "1").map(c => c.dataset.pasta || ""),
        aoFiltrar: aplicarFiltroTalentos,
        aoRenomear: function(antigo, novo){
            cardsTalentos().forEach(c => { if(c.dataset.pasta === antigo) c.dataset.pasta = novo; });
        },
        aoExcluir: function(nome){
            cardsTalentos().forEach(c => { if(c.dataset.pasta === nome) c.dataset.pasta = ""; });
        },
        aoMudarPastas: atualizarSelectsTalentos
    });

    // O input do título some e o texto digitado vira o cabeçalho; custo vira uma etiqueta
    function confirmarCardTalento(card){
        const titulo = card.querySelector(".talento-titulo");
        const exibido = card.querySelector(".talento-titulo-exibido");
        exibido.textContent = titulo.value.trim();
        exibido.style.display = "block";
        titulo.style.display = "none";

        card.dataset.confirmado = "1";
        card.querySelector(".talento-descricao").readOnly = true;
        card.querySelector(".talento-custo-edicao").style.display = "none";
        if(ehArte){
            lerEdicaoArte(card);
            card.querySelector(".talento-aprim-edicao").style.display = "none";
            card.querySelector(".talento-rodape-arte").style.display = "flex";
            card.querySelector(".talento-eficiencia-card").value = eficienciaTalento(card);
            card.querySelector(".aprim-gratis-disp").value = String(Aprimoramentos.nat(card.dataset.gratisDisp, 99));
            renderizarAprimsTalento(card);
        }
        card.querySelector(".talento-custo-tag").style.display = "inline";
        card.querySelector(".talento-toggle").style.display = "inline-block";
        card.querySelector(".talento-confirmar").style.display = "none";
        atualizarVisualTalento(card);
    }

    function criarCardTalento(t, confirmado, noTopo){
        listaTalentos.insertAdjacentHTML(noTopo ? "afterbegin" : "beforeend", `
            <div class="talento-card">
                <div class="talento-topo">
                    <h4 class="talento-titulo-exibido" style="display:none;"></h4>
                    <input class="talento-titulo" placeholder="Nome ${ehArte ? 'da técnica' : 'do talento'}">
                    <button type="button" class="botao-fechar talento-remover" title="Remover ${nomeItem}" aria-label="Remover ${nomeItem}">✕</button>
                </div>
                <div class="item-pasta">
                    <label><x-icone n="pasta"/> Pasta</label>
                    <select class="pasta-select"></select>
                </div>
                <textarea class="talento-descricao" placeholder="Descrição ${ehArte ? 'da técnica' : 'do talento'}"></textarea>
                <div class="talento-custo-edicao">
                    <label>Custo (Aura)</label>
                    <input type="number" min="0" class="talento-custo" placeholder="0">
                    ${ehArte ? '<label title="Aura abatida do custo base. Não afeta o custo dos aprimoramentos.">Eficiência</label><input type="number" min="0" class="talento-eficiencia-input" placeholder="0">' +
                        '<label title="Abaixo dele, a técnica custa +5 de Aura por nível que falta">Nível de maldição requisitado</label><input type="number" min="0" class="talento-nivel-input" placeholder="0">' : ''}
                </div>
                ${ehArte ? `
                <div class="talento-aprim-edicao">
                    <div class="talento-aprim-form"></div>
                    <button type="button" class="button talento-aprim-add">+ Aprimoramento</button>
                </div>
                <div class="aprim-bloco talento-aprim-view"></div>
                <div class="tecnica-rodape talento-rodape-arte" style="display:none;">
                    <label class="tecnica-eficiencia talento-eficiencia-view" title="Aura abatida do custo base (não afeta aprimoramentos)"><x-icone n="raio"/> Eficiência <input type="number" min="0" class="talento-eficiencia-card" value="0"></label>
                    ${Aprimoramentos.rodapeHtml(Aprimoramentos.normalizarItem({}))}
                </div>` : ''}
                <span class="talento-custo-tag" style="display:none;"></span>
                <div class="talento-acoes">
                    <button type="button" class="talento-toggle" style="display:none;" title="Ativar gasta a Aura na hora; desativar não devolve">Ativar</button>
                    <button class="button talento-confirmar" type="button">Adicionar</button>
                </div>
            </div>
        `);
        const card = noTopo ? listaTalentos.firstElementChild : listaTalentos.lastElementChild;
        card.querySelector(".talento-titulo").value = t.titulo || "";
        card.querySelector(".talento-descricao").value = t.descricao || "";
        card.querySelector(".talento-custo").value = (t.custo === undefined || t.custo === null) ? "" : t.custo;
        card.dataset.ativo = t.ativo ? "1" : "0";
        card.dataset.tipo = cfg.tipo;
        if(ehArte){
            card.querySelector(".talento-eficiencia-input").value = t.eficiencia ? t.eficiencia : "";
            card.querySelector(".talento-nivel-input").value = t.nivel_req ? t.nivel_req : "";
            Aprimoramentos.normalizarLista(t.aprimoramentos).forEach(function(a){
                if(a.texto.trim() !== ""){ a.texto = a.texto.trim(); linhaAprimTalento(card, a); }
            });
            card.dataset.gerais = JSON.stringify(Aprimoramentos.normalizarGerais(t.gerais));
            card.dataset.gratisDisp = String(Aprimoramentos.nat(t.gratis_disponiveis, 99));
            lerEdicaoArte(card);
        }
        card.dataset.pasta = typeof t.pasta === "string" ? t.pasta : PastasFicha.filtroAtual(cfg.pastaTipo);
        card.querySelector(".pasta-select").innerHTML = PastasFicha.opcoes(cfg.pastaTipo, card.dataset.pasta);
        if(confirmado) confirmarCardTalento(card);
        return card;
    }

    if(ehArte){
        const campoNivel = document.getElementById("nivel-hexomante");
        if(campoNivel) campoNivel.addEventListener("input", function(){
            cardsTalentos().filter(c => c.dataset.confirmado === "1").forEach(function(card){
                renderizarAprimsTalento(card);
                atualizarVisualTalento(card);
            });
        });
    }

    adicionarTalentos.addEventListener("click", function(){
        const card = criarCardTalento({}, false, true);
        depoisDeMudarTalentos();
        card.querySelector(".talento-titulo").focus();
    });

    // Aprimoramentos do card confirmado: caixas (change), botões − / + (click) e campo de grátis (input)
    function aplicarAprimoramento(e){
        const card = e.target.closest(".talento-card");
        if(!card || card.dataset.confirmado !== "1") return false;
        const item = itemDoCard(card);
        if(!Aprimoramentos.tratar(e, item)) return false;
        gravarItemCard(card, item);
        Aprimoramentos.redesenhar(card, item);
        atualizarVisualTalento(card);
        avisarMudancaTalentos();
        return true;
    }

    listaTalentos.addEventListener("change", function(e){
        if(ehArte && aplicarAprimoramento(e)) return;
        if(!e.target.classList.contains("pasta-select")) return;
        e.target.closest(".talento-card").dataset.pasta = e.target.value;
        depoisDeMudarTalentos();
    });

    if(ehArte){
        listaTalentos.addEventListener("input", function(e){
            const card = e.target.closest(".talento-card");
            if(!card) return;
            if(aplicarAprimoramento(e)) return;
            if(e.target.classList.contains("talento-eficiencia-card")){
                card.dataset.eficiencia = String(Math.max(0, parseInt(e.target.value, 10) || 0));
                atualizarVisualTalento(card);
                avisarMudancaTalentos();
            } else if(card.dataset.confirmado !== "1" &&
                      (e.target.classList.contains("talento-eficiencia-input") || e.target.closest(".talento-aprim-form"))){
                lerEdicaoArte(card);
            }
        });
    }

    listaTalentos.addEventListener("click", function(e){
        const card = e.target.closest(".talento-card");
        if(!card) return;

        if(ehArte && aplicarAprimoramento(e)) return;
        if(ehArte && e.target.classList.contains("talento-aprim-add")){
            linhaAprimTalento(card, {});
            return;
        }
        if(ehArte && e.target.classList.contains("tec-aprim-remover")){
            e.target.closest(".tecnica-aprim-linha").remove();
            lerEdicaoArte(card);
            avisarMudancaTalentos();
            return;
        }

        if(e.target.classList.contains("botao-fechar")){
            if(card.dataset.confirmado === "1" && !confirm("Remover " + (ehArte ? "esta técnica" : "este talento") + "?")) return;
            card.remove();
            depoisDeMudarTalentos();
            return;
        }

        if(e.target.classList.contains("talento-toggle")){
            if(card.dataset.ativo === "1"){
                card.dataset.ativo = "0";
            } else {
                if(!gastarAuraTalento(custoDoTalento(card))) return;
                card.dataset.ativo = "1";
            }
            atualizarVisualTalento(card);
            return;
        }

        if(e.target.classList.contains("talento-confirmar")){
            if(card.querySelector(".talento-titulo").value.trim() === ""){
                mostrarPopup("Digite um nome para " + (ehArte ? "a técnica" : "o talento") + " antes de adicionar.");
                return;
            }
            confirmarCardTalento(card);
            depoisDeMudarTalentos();
        }
    });

    return {
        carregar: function(t){ criarCardTalento(t, true, false); },
        atualizar: depoisDeMudarTalentos,
        // Talento vindo da lista do livro: entra no topo ainda editável; "Adicionar" confirma como no fluxo normal
        adicionarDoLivro: function(t){
            const card = criarCardTalento(t, false, true);
            depoisDeMudarTalentos();
            avisarMudancaTalentos();
            card.scrollIntoView({ block: "nearest", behavior: "smooth" });
            return card;
        }
    };
    }

    function atualizarContadorTalentosArte(){
        const qtd = talentosArteCard.querySelectorAll('.talento-card[data-confirmado="1"]').length;
        document.getElementById("cont-talentos-arte").textContent = qtd > 0 ? qtd : "";
    }

    const gruposTalentos = {
        mundano: montarTalentos({
            tipo: "mundano", pastaTipo: "talentos",
            lista: document.getElementById("lista-talentos"), barra: document.getElementById("pastas-talentos"),
            botaoAdicionar: document.getElementById("adicionar-talento"), vazio: document.getElementById("talentos-vazio"),
            contador: document.getElementById("cont-talentos")
        }),
        hexomancia: montarTalentos({
            tipo: "hexomancia", pastaTipo: "talentos_hexomancia",
            lista: document.getElementById("lista-talentos-hexomancia"), barra: document.getElementById("pastas-talentos-hexomancia"),
            botaoAdicionar: document.getElementById("adicionar-talento-hexomancia"), vazio: document.getElementById("talentos-hexomancia-vazio"),
            contador: document.getElementById("cont-talentos-hexomancia"), aoMudar: atualizarContadorTalentosArte
        }),
        fisiomancia: montarTalentos({
            tipo: "fisiomancia", pastaTipo: "talentos_fisiomancia",
            lista: document.getElementById("lista-talentos-fisiomancia"), barra: document.getElementById("pastas-talentos-fisiomancia"),
            botaoAdicionar: document.getElementById("adicionar-talento-fisiomancia"), vazio: document.getElementById("talentos-fisiomancia-vazio"),
            contador: document.getElementById("cont-talentos-fisiomancia"), aoMudar: atualizarContadorTalentosArte
        })
    };

    // Recria os talentos salvos já confirmados (com custo, estado ativo, pasta e tipo), cada um na sua lista
    @json($talentosSalvos).forEach(function(t){
        const grupo = (t && gruposTalentos[t.tipo]) || gruposTalentos.mundano;
        grupo.carregar(t);
    });
    Object.values(gruposTalentos).forEach(g => g.atualizar());

    window.fichaAdicionarTalentoMundano = function(t){ gruposTalentos.mundano.adicionarDoLivro(t); };
    const livroMundanos = document.getElementById("livro-talentos-mundano");
    if(livroMundanos){
        LivroTalentos.montar(livroMundanos, "mundano", function(t){
            gruposTalentos.mundano.adicionarDoLivro({
                titulo: t.nome,
                descricao: LivroTalentos.descricaoCompleta(t),
                custo: t.custo === null ? "" : t.custo
            });
        });
    }

    const cardTalento = document.getElementById("card-talento");

    //Outros

    const idade = document.getElementById("idade");

    // A idade não muda mais o porte físico nem o BE: é só o campo (o jogador ajusta à mão).

        //Maldições

    const CATALOGO_MALDICOES = @json(config('maldicoes', []));
    // Maldições que este usuário pode escolher: sem a chave do mestre, as não catalogadas ficam de fora
    const LISTA_MALDICOES = @json(\App\Support\AcessoMaldicoes::lista(auth()->user()));
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
                '<span class="maldicao-trava" title="Nível de Hexomante insuficiente">' + icone('cadeado') + '</span>' +
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
                    (a.dado ? '<span class="maldicao-dado">' + icone('dados') + ' ' + esc(a.dado) + "</span>" : "") +
                "</div>" +
                (a.regra ? '<p class="maldicao-regra">' + a.regra + "</p>" : "") + // HTML confiável vindo do config
                efeitos +
                (a.penalidade ? '<p class="maldicao-penalidade"><strong>Penalidade:</strong> ' + esc(a.penalidade) + "</p>" : "") +
            "</div>";
        }).join("");

        if(bloqueadas > 0){
            h += '<p class="maldicao-bloqueada-aviso">' + icone('cadeado') + ' ' + bloqueadas + " ameaça(s) ainda não liberada(s) neste nível.</p>";
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
        LISTA_MALDICOES.forEach(function(item, i){
            const c = item.slug ? CATALOGO_MALDICOES[item.slug] : null;
            const valor = c ? item.slug : "lista:" + i;
            const extra = c ? c.raridade : item.apelido;
            opcoes += '<option value="' + esc(valor) + '">' + esc(item.nome) + (extra ? " (" + esc(extra) + ")" : "") + "</option>";
        });
        opcoes += '<option value="' + CUSTOM_MALDICAO + '">Outra (personalizada)</option>';
        catalogoSelect.innerHTML = opcoes;
    }

    catalogoSelect.addEventListener("change", function(){
        const v = catalogoSelect.value;
        maldicaoCustom.classList.toggle("visivel", v === CUSTOM_MALDICAO);

        if(v && v.indexOf("lista:") === 0){
            const item = LISTA_MALDICOES[Number(v.slice(6))] || {};
            maldicaoPrevia.textContent = [item.categoria, item.orgao, item.apelido].filter(Boolean).join(" · ") + " · sem página detalhada";
        } else if(v && v !== CUSTOM_MALDICAO){
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
        } else if(escolha.indexOf("lista:") === 0){
            const item = LISTA_MALDICOES[Number(escolha.slice(6))];
            if(!item) return;
            if(maldicoesAdicionadas.some(m => (m.nome || "").toLowerCase() === item.nome.toLowerCase())){
                mostrarPopup("Essa maldição já foi adicionada.");
                return;
            }
            adicionarEntrada({ slug: null, nome: item.nome, ameaca: ameaca });
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
const adicionarItem = document.getElementById("adicionar-item");
const listaItens = document.getElementById("lista-itens");

        const itensAdicionadosTitulo = document.getElementById("itens-adicionados-titulo");

    // Campos de texto do item, na ordem em que aparecem no card: [chave no JSON, rótulo, id do campo no formulário].
    // "modificacoes" é o campo antigo: continua aparecendo nos itens que já tinham e, ao editar, passa para Efeitos mundanos.
    const ITEM_CARACTERISTICAS = [
        ["critico", "Crítico", "item-critico"],
        ["tipo_dano", "Tipo de dano", "item-tipo-dano"],
        ["condicao_especial", "Condição especial", "item-condicao"],
        ["tamanho", "Tamanho", "item-tamanho"],
        ["acao_uso", "Ação de uso", "item-acao-uso"],
        ["alcance_armadura", "Alcance da Armadura", "item-alcance-armadura"],
        ["incremento_defesa", "Incremento de defesa", "item-incremento-defesa"],
        ["penalidade_destreza", "Penalidade de destreza", "item-penalidade-destreza"],
        ["peso", "Peso", "item-peso"],
    ];
    const ITEM_EFEITOS = [
        ["efeitos_mundanos", "Efeitos mundanos", "item-efeitos-mundanos"],
        ["efeitos_amaldicoados", "Efeitos amaldiçoados", "item-efeitos-amaldicoados"],
    ];
    const ITEM_TEXTOS = ITEM_CARACTERISTICAS.concat(ITEM_EFEITOS);
    let itemEditando = null; // card em edição (o botão Adicionar vira Salvar item)

    function escaparHtmlItem(texto){
        return String(texto == null ? "" : texto)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function textoDanoItem(qtdDados, ladosDado, valorFixo){
        if(qtdDados > 0 && ladosDado > 0){
            return qtdDados + "d" + ladosDado + (valorFixo !== 0 ? (valorFixo > 0 ? " + " : " - ") + Math.abs(valorFixo) : "");
        }
        return valorFixo !== 0 ? String(valorFixo) : "";
    }

    // Item limpo (o mesmo formato do JSON salvo); a lista guarda o objeto no card (card._item), lido por sincronizarItens().
    function normalizarItem(item){
        const limpo = {
            nome: String(item.nome || "").trim(),
            categoria: String(item.categoria || "").trim(),
            qtd_dados: Math.max(0, Math.trunc(Number(item.qtd_dados) || 0)),
            lados_dado: Math.max(0, Math.trunc(Number(item.lados_dado) || 0)),
            valor_fixo: Math.trunc(Number(item.valor_fixo) || 0),
            descricao: String(item.descricao || "").trim(),
        };
        ITEM_TEXTOS.forEach(([chave]) => { limpo[chave] = String(item[chave] == null ? "" : item[chave]).trim(); });
        if(item.modificacoes) limpo.modificacoes = String(item.modificacoes).trim();
        return limpo;
    }

    function preencherCardItem(card, item){
        card._item = item;
        // a rolagem lê estes três
        card.dataset.nome = item.nome;
        card.dataset.qtdDados = item.qtd_dados;
        card.dataset.ladosDado = item.lados_dado;
        card.dataset.valorFixo = item.valor_fixo;

        const dano = textoDanoItem(item.qtd_dados, item.lados_dado, item.valor_fixo);
        const linhas = [];
        if(dano) linhas.push(["Dano", dano]);
        ITEM_CARACTERISTICAS.forEach(([chave, rotulo]) => { if(item[chave]) linhas.push([rotulo, item[chave]]); });
        const efeitos = ITEM_EFEITOS.filter(([chave]) => item[chave]);
        if(item.modificacoes) efeitos.push(["modificacoes", "Modificações"]);

        card.innerHTML = `
            <div class="item-card-topo">
                <span class="item-card-nome">${escaparHtmlItem(item.nome)}</span>
                ${item.categoria ? `<span class="item-tag">${escaparHtmlItem(item.categoria)}</span>` : ""}
                <span class="item-card-botoes">
                    <button class="botao-editar-item" type="button" title="Editar item">Editar</button>
                    <button class="botao-remover-item" type="button" title="Remover item">Remover</button>
                </span>
            </div>
            ${item.descricao ? `<div class="item-card-descricao">${escaparHtmlItem(item.descricao)}</div>` : ""}
            ${linhas.length ? `<dl class="item-card-dados">${linhas.map(([r, v]) =>
                `<div><dt>${escaparHtmlItem(r)}</dt><dd>${escaparHtmlItem(v)}</dd></div>`).join("")}</dl>` : ""}
            ${efeitos.map(([chave, rotulo]) =>
                `<div class="item-card-efeito"><span class="item-rotulo">${escaparHtmlItem(rotulo)}</span><p>${escaparHtmlItem(item[chave])}</p></div>`).join("")}
            <div class="item-card-rodape">
                <button class="botao-rolar-item" type="button" ${dano ? "" : "disabled"}>Rolar</button>
                <span class="item-card-resultado"></span>
            </div>
        `;
    }

    // Monta o card de um item (usado ao adicionar e ao recarregar a ficha salva).
    function criarCardItem(item){
        const card = document.createElement("div");
        card.className = "item-card";
        preencherCardItem(card, normalizarItem(item));
        listaItens.appendChild(card);
        itensAdicionadosTitulo.style.display = "block";
        return card;
    }

    // Recria os itens salvos ao abrir a ficha
    @json($itensSalvos).forEach(criarCardItem);

    function limparFormularioItem(){
        itemNome.value = "";
        itemCategoria.value = "Selecione";
        itemQtdDados.value = "";
        itemDado.value = "0";
        itemValorFixo.value = "";
        itemDescricao.value = "";
        ITEM_TEXTOS.forEach(([, , id]) => { document.getElementById(id).value = ""; });
        itemEditando = null;
        adicionarItem.textContent = "+ Adicionar Item";
        listaItens.querySelectorAll(".item-card.editando").forEach(c => c.classList.remove("editando"));
    }

    function lerFormularioItem(){
        const item = {
            nome: itemNome.value,
            categoria: itemCategoria.value === "Selecione" ? "" : itemCategoria.value,
            qtd_dados: itemQtdDados.value,
            lados_dado: itemDado.value,
            valor_fixo: itemValorFixo.value,
            descricao: itemDescricao.value,
        };
        ITEM_TEXTOS.forEach(([chave, , id]) => { item[chave] = document.getElementById(id).value; });
        return normalizarItem(item);
    }

    adicionarItem.addEventListener("click", function(){
        if(itemNome.value.trim() === ""){
            mostrarPopup("Digite um nome para o item antes de adicionar.");
            return;
        }
        const item = lerFormularioItem();
        if(itemEditando && itemEditando.isConnected){
            preencherCardItem(itemEditando, item);
            itemEditando.scrollIntoView({ block: "nearest", behavior: "smooth" });
        } else {
            criarCardItem(item);
        }
        limparFormularioItem();
    });

    // Editar: leva o item de volta ao formulário; ao salvar, o card é trocado no mesmo lugar
    function editarItem(card){
        const item = card._item || {};
        itemNome.value = item.nome || "";
        itemCategoria.value = item.categoria && [...itemCategoria.options].some(o => o.value === item.categoria) ? item.categoria : "Selecione";
        itemQtdDados.value = item.qtd_dados || "";
        itemDado.value = String(item.lados_dado || 0);
        itemValorFixo.value = item.valor_fixo || "";
        itemDescricao.value = item.descricao || "";
        ITEM_TEXTOS.forEach(([chave, , id]) => { document.getElementById(id).value = item[chave] || ""; });
        // o campo antigo "Modificações" passa a ser Efeitos mundanos
        if(item.modificacoes){
            const mundanos = document.getElementById("item-efeitos-mundanos");
            mundanos.value = mundanos.value ? mundanos.value + "\n" + item.modificacoes : item.modificacoes;
        }
        listaItens.querySelectorAll(".item-card.editando").forEach(c => c.classList.remove("editando"));
        card.classList.add("editando");
        itemEditando = card;
        adicionarItem.textContent = "Salvar item";
        itemNome.scrollIntoView({ block: "center", behavior: "smooth" });
        itemNome.focus({ preventScroll: true });
    }

        listaItens.addEventListener("click", function(e){
        if(e.target.classList.contains("botao-editar-item")){
            editarItem(e.target.closest(".item-card"));
            return;
        }
        if(e.target.classList.contains("botao-remover-item")){
            const card = e.target.closest(".item-card");
            if(card === itemEditando) limparFormularioItem();
            card.remove();
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

            const detalheTexto = detalhes.length > 0
                ? detalhes.join(" + ") + (valorFixo !== 0 ? " " + (valorFixo > 0 ? "+ " : "- ") + Math.abs(valorFixo) : "")
                : "";

            registrarRolagem("Dano: " + (card.dataset.nome || "item"), total, detalheTexto);
            resultadoSpan.innerHTML =
                '<span class="item-resultado-rotulo">Resultado</span>' +
                '<strong class="item-resultado-total">' + total + '</strong>' +
                (detalheTexto ? '<span class="item-resultado-detalhe">' + detalheTexto + '</span>' : '');
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
    registrarRolagem("Dados " + qtd + "d" + lados, total, resultados.join(" + "));

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
    registrarRolagem("Dano (prévia do item)", total, textoDetalhe);
});

document.querySelectorAll("form button:not([type])").forEach(function(botao){
    botao.setAttribute("type", "button");
});

// Enter dentro de um campo NÃO envia a ficha (só o botão "Salvar" envia).
// Textareas continuam aceitando Enter para quebrar linha.
document.getElementById("form-ficha").addEventListener("keydown", function(e){
    if(e.key !== "Enter") return;
    const alvo = e.target;
    if(alvo.tagName === "TEXTAREA" || alvo.tagName === "BUTTON") return;
    if(alvo.tagName === "INPUT" && alvo.type === "submit") return;
    e.preventDefault();
});

function sincronizarTalentos(){
    const lista = [];
    document.querySelectorAll(".lista-talentos .talento-card").forEach(function(card){
        const titulo = card.querySelector(".talento-titulo").value.trim();
        if(titulo === "") return;
        const item = {
            tipo: card.dataset.tipo || "mundano",
            titulo: titulo,
            descricao: card.querySelector(".talento-descricao").value,
            custo: Math.max(0, parseInt(card.querySelector(".talento-custo").value, 10) || 0),
            ativo: card.dataset.ativo === "1",
            pasta: card.dataset.pasta || ""
        };
        if(item.tipo !== "mundano"){
            let aprims = [];
            try { aprims = JSON.parse(card.dataset.aprims || "[]"); } catch(err){ aprims = []; }
            let gerais = {};
            try { gerais = JSON.parse(card.dataset.gerais || "{}"); } catch(err){ gerais = {}; }
            const norm = Aprimoramentos.normalizarItem({
                custo: item.custo,
                eficiencia: card.dataset.eficiencia,
                aprimoramentos: aprims,
                gerais: gerais,
                gratis_disponiveis: card.dataset.gratisDisp
            });
            item.eficiencia = norm.eficiencia;
            item.aprimoramentos = norm.aprimoramentos;
            item.gerais = Aprimoramentos.serializarGerais(norm.gerais);
            item.gratis_disponiveis = norm.gratis_disponiveis;
            item.nivel_req = Math.max(0, parseInt(card.dataset.nivelReq, 10) || 0);
        }
        lista.push(item);
    });
    document.getElementById("talentos").value = JSON.stringify(lista);
}

function sincronizarItens(){
    const lista = [];
    // cada card guarda o item já limpo (normalizarItem); os campos vazios não vão no JSON
    document.querySelectorAll("#lista-itens .item-card").forEach(function(card){
        const item = Object.assign({}, card._item || {});
        Object.keys(item).forEach(function(k){ if(item[k] === "" && k !== "nome") delete item[k]; });
        lista.push(item);
    });
    document.getElementById("itens").value = JSON.stringify(lista);
}

function prepararFichaParaSalvar(){
    document.getElementById("pastas").value = PastasFicha.json();
    sincronizarItens();
    sincronizarTalentos();
    sincronizarTecnicas();
    sincronizarNiveisProfissao();

    const sync = (spanId, atualId, maximoId) => {
        const partes = document.getElementById(spanId).textContent.split("/").map(v => v.trim());
        document.getElementById(atualId).value = partes[0] !== "?" ? partes[0] : "";
        document.getElementById(maximoId).value = partes[1] !== "?" ? partes[1] : "";
    };
    sync("vida", "vida_atual", "vida_maxima");
    sync("sanidade", "sanidade_atual", "sanidade_maxima");
        sync("aura", "aura_atual", "aura_maxima");
}

document.getElementById("form-ficha").addEventListener("submit", prepararFichaParaSalvar);

// ==================== Salvamento automático ====================
(function(){
    if(!{{ $editando ? 'true' : 'false' }}) return;
    if({{ $somenteLeitura ? 'true' : 'false' }}) return; // somente leitura: nada é salvo

    const form = document.getElementById("form-ficha");
    const ATRASO = 1000; // ms após a última alteração

    let timer = null;
    let timerAviso = null;
    let salvando = false;
    let pendente = false;
    let sujo = false;

    // ---- Indicador de status ----
    const estilo = document.createElement("style");
    estilo.textContent = `
        #autosave-status {
            position: fixed;
            right: 16px;
            bottom: calc(16px + env(safe-area-inset-bottom, 0px));
            z-index: 1000;
            max-width: 280px;
            padding: 6px 12px;
            font-size: .8rem;
            line-height: 1.3;
            color: #fff;
            background: rgba(0, 0, 0, .78);
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 999px;
            opacity: 0;
            transform: translateY(6px);
            pointer-events: none;
            transition: opacity .2s, transform .2s;
        }
        #autosave-status.visivel { opacity: 1; transform: none; }
        #autosave-status.ok { border-color: rgba(95, 167, 119, .7); }
        #autosave-status.erro { border-color: rgba(220, 70, 70, .8); border-radius: 10px; }
    `;
    document.head.appendChild(estilo);

    const aviso = document.createElement("div");
    aviso.id = "autosave-status";
    aviso.setAttribute("role", "status");
    aviso.setAttribute("aria-live", "polite");
    document.body.appendChild(aviso);

    function mostrar(texto, classe, esconderEm){
        clearTimeout(timerAviso);
        aviso.textContent = texto;
        aviso.className = "visivel " + (classe || "");
        if(esconderEm){
            timerAviso = setTimeout(() => { aviso.classList.remove("visivel"); }, esconderEm);
        }
    }

    // ---- Salvamento ----
    function agendar(ms){
        sujo = true;
        clearTimeout(timer);
        timer = setTimeout(() => salvar(), ms || ATRASO);
    }

    async function salvar(opcoes){
        clearTimeout(timer);
        if(salvando){ pendente = true; return; }

        salvando = true;
        pendente = false;
        sujo = false;
        mostrar("Salvando…", "");

        try{
            prepararFichaParaSalvar();

            const resp = await fetch(form.action, {
                method: "POST",
                body: new FormData(form), // já inclui _token e _method=PUT
                headers: {
                    "Accept": "application/json",
                    "X-Requested-With": "XMLHttpRequest"
                },
                credentials: "same-origin",
                redirect: "manual",
                keepalive: !!(opcoes && opcoes.keepalive)
            });

            if(resp.ok || resp.type === "opaqueredirect"){
                mostrar("Salvo ✓", "ok", 2000);
            } else if(resp.status === 422){
                const json = await resp.json().catch(() => null);
                const primeiro = json && json.errors ? Object.values(json.errors)[0][0] : "dados inválidos.";
                mostrar("Não salvo: " + primeiro, "erro", 6000);
            } else if(resp.status === 419){
                mostrar("Sessão expirada. Recarregue a página.", "erro");
            } else {
                mostrar("Erro ao salvar (" + resp.status + ").", "erro", 6000);
            }
        } catch(erro){
            mostrar("Sem conexão. Tentando novamente…", "erro");
            agendar(5000);
        } finally {
            salvando = false;
            if(pendente) agendar(300);
        }
    }

    // ---- Gatilhos ----
    form.addEventListener("input", () => agendar());
    form.addEventListener("change", () => agendar());

    // Botões que alteram a ficha (adicionar/remover itens, talentos etc.)
    form.addEventListener("click", function(e){
        const botao = e.target.closest("button");
        if(!botao || botao.type === "submit") return;
        if(botao.id === "preencher-teste") return;
        if(/rolar/i.test(botao.className)) return;
        agendar();
    });

    // Envio manual cancela o autosave pendente
    form.addEventListener("submit", function(){
        clearTimeout(timer);
        sujo = false;
    });

    // Ao sair da página com alteração pendente, salva na hora
    function descarregar(){
        if(sujo && !salvando) salvar({ keepalive: true });
    }
    window.addEventListener("pagehide", descarregar);
    document.addEventListener("visibilitychange", function(){
        if(document.visibilityState === "hidden") descarregar();
    });
})();

// ==================== Técnicas ====================
let tecnicas = @json($tecnicasSalvas);
if(!Array.isArray(tecnicas)) tecnicas = [];
let proximoIdTecnica = 1;
tecnicas.forEach(t => { t.id = proximoIdTecnica++; t.aprimoramentos = t.aprimoramentos || []; t.eficiencia = t.eficiencia || 0; Aprimoramentos.normalizarItem(t); t.pasta = typeof t.pasta === "string" ? t.pasta : ""; t.eficiencia = Math.max(0, parseInt(t.eficiencia, 10) || 0); t.tipo = t.tipo === "fisiomancia" ? "fisiomancia" : "hexomancia"; });

const tecnicasCard = document.getElementById("tecnicas-card");
const listaTecnicas = document.getElementById("lista-tecnicas");
const tecAprimForm = document.getElementById("tec-aprimoramentos-form");

document.getElementById("tecnicas-abrir").addEventListener("click", () => tecnicasCard.classList.add("ativo"));
document.getElementById("fechar-tecnicas").addEventListener("click", () => tecnicasCard.classList.remove("ativo"));

// Abas: Hexomancia (técnicas de maldição) e Fisiomancia (técnicas + propriedades fisiomantes). Cada técnica guarda o "tipo".
function abaTecnicas(){
    const b = tecnicasCard.querySelector(".aba-painel.ativo");
    return b && b.dataset.aba === "fisiomancia" ? "fisiomancia" : "hexomancia";
}
function rotuloNivelTecnica(tipo){
    return tipo === "fisiomancia" ? "Nível de arte requisitado" : "Nível de maldição requisitado";
}
tecnicasCard.addEventListener("aba-mudou", function(){ renderizarTecnicas(); });

function sincronizarTecnicas(){
    // gerais: só as chaves com qtd > 0 vão no JSON
    document.getElementById("tecnicas").value = JSON.stringify(tecnicas.map(t => Object.assign({}, t, { gerais: Aprimoramentos.serializarGerais(t.gerais) })));
}

// Eficiência: Aura abatida do custo base (nunca abaixo de 0); os aprimoramentos não são afetados
function custoBaseTecnica(t){
    return Aprimoramentos.custoBase(t);
}
function custoTotalTecnica(t){
    return Aprimoramentos.custoTotal(t);
}

// ---- linhas de aprimoramento no formulário ----
function adicionarLinhaAprimoramento(){
    const linha = document.createElement("div");
    linha.className = "tecnica-aprim-linha";
    linha.innerHTML = Aprimoramentos.linhaEdicaoHtml();
    tecAprimForm.appendChild(linha);
}

document.getElementById("tec-add-aprimoramento").addEventListener("click", adicionarLinhaAprimoramento);

tecAprimForm.addEventListener("click", function(e){
    if(e.target.classList.contains("tec-aprim-remover")){
        e.target.closest(".tecnica-aprim-linha").remove();
    }
});

// ---- adicionar técnica ----
document.getElementById("adicionar-tecnica").addEventListener("click", function(){
    const nome = document.getElementById("tec-nome").value.trim();
    if(nome === ""){
        mostrarPopup("Digite um nome para a técnica antes de adicionar.");
        return;
    }

    // aprimoramentos só desta técnica; na edição, cada linha guarda os usos que já tinha (data-qtd / data-gratis)
    const aprimoramentos = [];
    tecAprimForm.querySelectorAll(".tecnica-aprim-linha").forEach(function(l){
        const texto = l.querySelector(".tec-aprim-texto").value.trim();
        const custo = Math.max(0, parseInt(l.querySelector(".tec-aprim-custo-input").value, 10) || 0);
        if(texto !== "" || custo > 0){
            const qtd = parseInt(l.dataset.qtd, 10) || 0;
            aprimoramentos.push({ texto: texto, custo: custo, acumulativo: l.querySelector(".tec-aprim-acum").checked,
                                  qtd: qtd, gratis: parseInt(l.dataset.gratis, 10) || 0, ativo: qtd > 0 });
        }
    });

    const campos = {
        nome: nome,
        descricao: document.getElementById("tec-descricao").value.trim(),
        logica: document.getElementById("tec-logica").value.trim(),
        nivel: Math.max(0, parseInt(document.getElementById("tec-nivel").value, 10) || 0),
        custo: Math.max(0, parseInt(document.getElementById("tec-custo").value, 10) || 0),
        eficiencia: Math.max(0, parseInt(document.getElementById("tec-eficiencia").value, 10) || 0),
        processo: document.getElementById("tec-processo").value.trim(),
        efeito: document.getElementById("tec-efeito").value.trim(),
        pasta: document.getElementById("tec-pasta").value,
        aprimoramentos: aprimoramentos
    };

    if(tecnicaEditando && tecnicas.includes(tecnicaEditando)){
        // edição: troca os dados no lugar, mantendo o tipo, os usos dos gerais/guardados e os grátis
        Object.assign(tecnicaEditando, campos);
        Aprimoramentos.normalizarItem(tecnicaEditando);
    } else {
        tecnicas.push(Aprimoramentos.normalizarItem(Object.assign({
            id: proximoIdTecnica++, tipo: abaTecnicas(), gerais: {}, gratis_disponiveis: 0
        }, campos)));
    }

    limparFormularioTecnica();
    renderizarTecnicas();
    document.getElementById("tecnica-novo").open = false;
});

// ---- editar técnica: leva tudo de volta ao formulário (inclusive os aprimoramentos só desta técnica) ----
let tecnicaEditando = null;
function limparFormularioTecnica(){
    ["tec-nome","tec-descricao","tec-logica","tec-nivel","tec-custo","tec-eficiencia","tec-processo","tec-efeito"]
        .forEach(id => document.getElementById(id).value = "");
    tecAprimForm.innerHTML = "";
    tecnicaEditando = null;
    document.getElementById("adicionar-tecnica").textContent = "+ Adicionar Técnica";
    document.getElementById("cancelar-edicao-tecnica").hidden = true;
    document.querySelector("#tecnica-novo > summary").textContent = "Nova técnica";
    listaTecnicas.querySelectorAll(".tecnica-card.editando").forEach(c => c.classList.remove("editando"));
}
function editarTecnica(t){
    limparFormularioTecnica();
    tecnicaEditando = t;
    document.getElementById("tec-nome").value = t.nome || "";
    document.getElementById("tec-descricao").value = t.descricao || "";
    document.getElementById("tec-logica").value = t.logica || "";
    document.getElementById("tec-nivel").value = t.nivel || "";
    document.getElementById("tec-custo").value = t.custo || "";
    document.getElementById("tec-eficiencia").value = t.eficiencia || "";
    document.getElementById("tec-processo").value = t.processo || "";
    document.getElementById("tec-efeito").value = t.efeito || "";
    document.getElementById("tec-pasta").innerHTML = PastasFicha.opcoes("tecnicas", t.pasta || "");
    (t.aprimoramentos || []).forEach(function(a){
        adicionarLinhaAprimoramento();
        const l = tecAprimForm.lastElementChild;
        l.querySelector(".tec-aprim-texto").value = a.texto || "";
        l.querySelector(".tec-aprim-custo-input").value = a.custo || "";
        l.querySelector(".tec-aprim-acum").checked = !!a.acumulativo;
        l.dataset.qtd = String(a.qtd || 0);
        l.dataset.gratis = String(a.gratis || 0);
    });
    document.getElementById("adicionar-tecnica").textContent = "Salvar técnica";
    document.getElementById("cancelar-edicao-tecnica").hidden = false;
    const det = document.getElementById("tecnica-novo");
    det.querySelector("summary").textContent = "Editando: " + (t.nome || "técnica");
    det.open = true;
    const card = listaTecnicas.querySelector('.tecnica-card[data-id="' + t.id + '"]');
    if(card) card.classList.add("editando");
    det.scrollIntoView({ block: "start", behavior: "smooth" });
    document.getElementById("tec-nome").focus({ preventScroll: true });
}
document.getElementById("cancelar-edicao-tecnica").addEventListener("click", function(){
    limparFormularioTecnica();
    atualizarSelectNovaTecnica();
});

// ---- renderização da lista ----
function dadoTecnica(rotulo, valor){
    return valor ? "<div><dt>" + esc(rotulo) + "</dt><dd>" + esc(valor) + "</dd></div>" : "";
}

// ---- pastas das técnicas ----
function atualizarSelectNovaTecnica(){
    const sel = document.getElementById("tec-pasta");
    sel.innerHTML = PastasFicha.opcoes("tecnicas", PastasFicha.filtroAtual("tecnicas"));
}

PastasFicha.registrar("tecnicas", {
    barra: document.getElementById("pastas-tecnicas"),
    usadas: () => tecnicas.map(t => t.pasta || ""),
    aoFiltrar: function(){ atualizarSelectNovaTecnica(); renderizarTecnicas(); },
    aoRenomear: function(antigo, novo){ tecnicas.forEach(t => { if(t.pasta === antigo) t.pasta = novo; }); },
    aoExcluir: function(nome){ tecnicas.forEach(t => { if(t.pasta === nome) t.pasta = ""; }); },
    aoMudarPastas: function(){ atualizarSelectNovaTecnica(); }
});

function renderizarTecnicas(){
    const aba = abaTecnicas();
    document.getElementById("tec-nivel-rotulo").textContent = rotuloNivelTecnica(aba);
    const daAba = tecnicas.filter(t => (t.tipo || "hexomancia") === aba);
    const visiveis = daAba.filter(t => PastasFicha.visivel("tecnicas", t.pasta || ""));

    listaTecnicas.innerHTML = visiveis.map(function(t){
        return '<div class="tecnica-card" data-id="' + t.id + '">' +
            "<h4>" + esc(t.nome) + "</h4>" +
            '<div class="item-pasta"><label>' + icone('pasta') + ' Pasta</label><select class="pasta-select">' +
                PastasFicha.opcoes("tecnicas", t.pasta || "") + "</select></div>" +
            "<dl>" +
                dadoTecnica("Descrição", t.descricao) +
                dadoTecnica("Lógica de obtenção", t.logica) +
                dadoTecnica(rotuloNivelTecnica(t.tipo), String(t.nivel)) +
                dadoTecnica("Processo", t.processo) +
                dadoTecnica("Efeito", t.efeito) +
            "</dl>" +
            '<div class="aprim-bloco">' + Aprimoramentos.html(t) + "</div>" +
            '<div class="tecnica-rodape">' +
                '<span class="tecnica-total tec-total-texto">' + esc(Aprimoramentos.textoCusto(t)) + "</span>" +
                '<label class="tecnica-eficiencia" title="Aura abatida do custo base (não afeta aprimoramentos)">' + icone('raio') + ' Eficiência ' +
                    '<input type="number" min="0" class="tec-eficiencia-card" value="' + (Number(t.eficiencia) || 0) + '"></label>' +
                Aprimoramentos.rodapeHtml(t) +
                '<button type="button" class="button botao-conjurar-tecnica">Conjurar</button>' +
                '<button type="button" class="button button-secundario botao-editar-tecnica">Editar</button>' +
                '<button type="button" class="botao-remover-item botao-remover-tecnica">Remover</button>' +
            "</div>" +
        "</div>";
    }).join("");

    const vazio = document.getElementById("tecnicas-vazio");
    vazio.style.display = visiveis.length === 0 ? "block" : "none";
    vazio.textContent = daAba.length === 0
        ? "Nenhuma técnica de " + (aba === "fisiomancia" ? "Fisiomancia" : "Hexomancia") + " ainda. Abra “Nova técnica” acima para criar a primeira."
        : "Nenhuma técnica nesta pasta.";

    document.getElementById("cont-tecnicas").textContent = tecnicas.length > 0 ? tecnicas.length : "";
    ["hexomancia", "fisiomancia"].forEach(function(tipo){
        const n = tecnicas.filter(t => (t.tipo || "hexomancia") === tipo).length;
        document.getElementById("cont-tecnicas-" + tipo).textContent = n > 0 ? n : "";
    });
    PastasFicha.atualizar("tecnicas");
    sincronizarTecnicas();
}

// ---- mover técnica de pasta ----
listaTecnicas.addEventListener("change", function(e){
    if(!e.target.classList.contains("pasta-select")) return;
    const card = e.target.closest(".tecnica-card");
    const t = tecnicas.find(x => String(x.id) === card.dataset.id);
    if(!t) return;
    t.pasta = e.target.value;
    renderizarTecnicas();
});

// ---- aprimoramentos (caixas, − / +, grátis) e campo "Aprimoramentos grátis" ----
function aplicarAprimoramentoTecnica(e){
    const card = e.target.closest(".tecnica-card");
    if(!card) return;
    const t = tecnicas.find(x => String(x.id) === card.dataset.id);
    if(!t || !Aprimoramentos.tratar(e, t)) return;
    Aprimoramentos.redesenhar(card, t);
    card.querySelector(".tec-total-texto").textContent = Aprimoramentos.textoCusto(t);
    sincronizarTecnicas();
}
["click", "change", "input"].forEach(ev => listaTecnicas.addEventListener(ev, aplicarAprimoramentoTecnica));

// ==================== Aprimoramentos guardados (aba "Aprimoramentos" do painel Técnicas) ====================
// Lista do jogador (coluna "aprimoramentos"): aparecem em todas as técnicas e técnicas de arte, como os gerais.
// Criar, mudar ou apagar redesenha as técnicas; os usos de um guardado apagado somem das técnicas.
let aprimGuardados = Aprimoramentos.guardados.map(d => ({ id: d.chave.slice(2), nome: d.nome, tipo: d.tipo, custo: d.custo, acumulativo: d.acumulativo !== false }));
const listaGuardados = document.getElementById("aprim-guardados-lista");
const campoGuardados = document.getElementById("aprimoramentos-guardados");

function renderizarGuardados(){
    const ro = Aprimoramentos.somenteLeitura ? " disabled" : "";
    listaGuardados.innerHTML = aprimGuardados.map(function(a){
        return '<div class="aprim-guardado" data-id="' + esc(a.id) + '">' +
            '<input type="text" class="aprim-g-nome" maxlength="200" value="' + esc(a.nome) + '" aria-label="Nome"' + ro + ">" +
            '<button type="button" class="botao-remover-item aprim-guardado-apagar" title="Apagar este aprimoramento de todas as técnicas">Apagar</button>' +
            '<div class="aprim-guardado-linha">' +
                '<select class="aprim-g-tipo" aria-label="Tipo de custo"' + ro + ">" +
                    '<option value="fixo"' + (a.tipo === "fixo" ? " selected" : "") + ">Valor fixo</option>" +
                    '<option value="original"' + (a.tipo === "original" ? " selected" : "") + ">Custo original da técnica</option>" +
                "</select>" +
                '<input type="number" min="0" class="aprim-g-custo" value="' + (a.custo || 0) + '" aria-label="Aura por uso"' + (a.tipo === "original" ? " disabled" : ro) + ">" +
                '<label class="aprim-acum"><input type="checkbox" class="aprim-g-acum"' + (a.acumulativo ? " checked" : "") + ro + "> Acumulativo</label>" +
            "</div>" +
        "</div>";
    }).join("");
    document.getElementById("aprim-guardados-vazio").style.display = aprimGuardados.length ? "none" : "block";
    document.getElementById("cont-aprim-guardados").textContent = aprimGuardados.length || "";
    campoGuardados.value = JSON.stringify(aprimGuardados);
}

function aplicarGuardados(){
    Aprimoramentos.definirGuardados(aprimGuardados);
    tecnicas.forEach(t => Aprimoramentos.normalizarItem(t)); // ganha a chave dos novos, perde a dos apagados
    renderizarGuardados();
    renderizarTecnicas();
    document.dispatchEvent(new Event("aprimoramentos-guardados"));
    campoGuardados.dispatchEvent(new Event("input", { bubbles: true })); // salvamento automático
}

const btnGuardar = document.getElementById("aprim-novo-guardar");
if(btnGuardar) btnGuardar.addEventListener("click", function(){
    const nome = document.getElementById("aprim-novo-nome").value.trim();
    if(nome === ""){ mostrarPopup("Dê um nome ao aprimoramento antes de guardar."); return; }
    if(aprimGuardados.length >= 50){ mostrarPopup("Limite de 50 aprimoramentos guardados."); return; }
    const tipo = document.getElementById("aprim-novo-tipo").value === "original" ? "original" : "fixo";
    aprimGuardados.push({
        id: (Date.now().toString(36) + Math.random().toString(36).slice(2, 5)).slice(0, 20),
        nome: nome, tipo: tipo,
        custo: tipo === "fixo" ? Math.max(0, parseInt(document.getElementById("aprim-novo-custo").value, 10) || 0) : 0,
        acumulativo: document.getElementById("aprim-novo-acum").checked
    });
    document.getElementById("aprim-novo-nome").value = "";
    document.getElementById("aprim-novo-custo").value = "";
    aplicarGuardados();
});
const tipoNovo = document.getElementById("aprim-novo-tipo");
if(tipoNovo) tipoNovo.addEventListener("change", function(){
    document.getElementById("aprim-novo-custo").disabled = tipoNovo.value === "original";
});

listaGuardados.addEventListener("change", function(e){
    const box = e.target.closest(".aprim-guardado");
    const a = box && aprimGuardados.find(x => x.id === box.dataset.id);
    if(!a) return;
    if(e.target.classList.contains("aprim-g-nome")){
        const nome = e.target.value.trim();
        if(nome) a.nome = nome; else e.target.value = a.nome;
    }
    if(e.target.classList.contains("aprim-g-tipo")) a.tipo = e.target.value === "original" ? "original" : "fixo";
    if(e.target.classList.contains("aprim-g-custo")) a.custo = Math.max(0, parseInt(e.target.value, 10) || 0);
    if(e.target.classList.contains("aprim-g-acum")) a.acumulativo = e.target.checked;
    aplicarGuardados();
});
listaGuardados.addEventListener("click", function(e){
    if(!e.target.classList.contains("aprim-guardado-apagar")) return;
    const box = e.target.closest(".aprim-guardado");
    const a = aprimGuardados.find(x => x.id === box.dataset.id);
    if(!a || !confirm("Apagar “" + a.nome + "”? Ele sai de todas as técnicas.")) return;
    aprimGuardados = aprimGuardados.filter(x => x !== a);
    aplicarGuardados();
});
renderizarGuardados();

// ---- eficiência ajustada direto no card ----
listaTecnicas.addEventListener("input", function(e){
    if(!e.target.classList.contains("tec-eficiencia-card")) return;
    const card = e.target.closest(".tecnica-card");
    const t = tecnicas.find(x => String(x.id) === card.dataset.id);
    if(!t) return;
    t.eficiencia = Math.max(0, parseInt(e.target.value, 10) || 0);
    card.querySelector(".tec-total-texto").textContent = Aprimoramentos.textoCusto(t);
    sincronizarTecnicas();
});

// ---- conjurar / remover ----
listaTecnicas.addEventListener("click", function(e){
    const card = e.target.closest(".tecnica-card");
    if(!card) return;
    const t = tecnicas.find(x => String(x.id) === card.dataset.id);
    if(!t) return;

    if(e.target.classList.contains("botao-editar-tecnica")){
        editarTecnica(t);
        return;
    }
    if(e.target.classList.contains("botao-remover-tecnica")){
        if(t === tecnicaEditando) limparFormularioTecnica();
        tecnicas = tecnicas.filter(x => x !== t);
        renderizarTecnicas();
        return;
    }

    if(e.target.classList.contains("botao-conjurar-tecnica")){
        const req = Number(t.nivel) || 0;
        if(req > Number(nivelHexomanteInput.value || 0)){
            mostrarPopup((t.tipo === "fisiomancia" ? "Nível de arte insuficiente." : "Nível de Hexomante insuficiente.") + " Esta técnica exige nível " + req + ".");
            return;
        }

        const partes = aura.textContent.split("/").map(v => parseInt(v.trim(), 10));
        let atual = partes[0];
        const maximo = partes[1];
        if(isNaN(atual) || isNaN(maximo)){
            mostrarPopup("Defina sua Aura antes de conjurar uma técnica.");
            return;
        }

        // Pode conjurar mesmo sem Aura suficiente: o valor fica negativo (exibido em vermelho)
        const custo = custoTotalTecnica(t);
        atual -= custo;
        aura.textContent = atual + " / " + maximo;
        document.getElementById("aura_atual").value = atual;

        const barraAura = document.querySelector(".barra-aura");
        if(barraAura && maximo > 0){
            barraAura.style.width = Math.max(0, Math.min(100, (atual / maximo) * 100)) + "%";
        }

        mostrarPopup(t.nome + " conjurada! -" + custo + " de Aura (restam " + atual + ")" + (atual < 0 ? " — Aura negativa!" : "."));
    }
});

document.getElementById("tecnica-novo").open = tecnicas.length === 0;
atualizarSelectNovaTecnica();
renderizarTecnicas();

// ==================== Propriedades fisiomânticas (Técnicas de arte > Fisiomancia > sub-aba) ====================
// Catálogo completo em resources/data/propriedades_fisiomantes.json; as propriedades salvas na ficha vão no JSON
// "propriedades_fisiomantes" (nome, fonte, ameaça, descrição e anotação). Pesquisa ignora acentos e maiúsculas.
(function(){
    const catalogo = @json($catalogoPropriedades);
    const fontes = Array.isArray(catalogo.fontes) ? catalogo.fontes : [];
    const grupos = (catalogo.grupos && typeof catalogo.grupos === "object") ? catalogo.grupos : {};
    let minhas = @json($propriedadesSalvas);
    if(!Array.isArray(minhas)) minhas = [];
    minhas = minhas.filter(p => p && typeof p.nome === "string" && p.nome.trim() !== "").map(p => ({
        nome: p.nome,
        fonte: p.fonte || "",
        ameaca: (p.ameaca === null || p.ameaca === undefined || p.ameaca === "") ? null : Number(p.ameaca),
        descricao: p.descricao || "",
        anotacao: p.anotacao || ""
    }));

    const campo = document.getElementById("propriedades-fisiomantes");
    const listaMinhas = document.getElementById("prop-minhas-lista");
    const vazioMinhas = document.getElementById("prop-minhas-vazio");
    const busca = document.getElementById("prop-busca");
    const selGrupo = document.getElementById("prop-grupo");
    const descGrupo = document.getElementById("prop-grupo-descricao");
    const cat = document.getElementById("prop-catalogo");
    const vazioCat = document.getElementById("prop-catalogo-vazio");
    if(!campo || !cat) return;

    const escP = t => String(t == null ? "" : t).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    const norm = s => String(s == null ? "" : s).normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();
    const temAmeaca = p => p.ameaca !== null && p.ameaca !== undefined && p.ameaca !== "";

    // "Duro 2" em Pedras não repete o texto: a descrição vem da primeira fonte que a descreve (Metal)
    const porNome = {};
    fontes.forEach(f => (f.propriedades || []).forEach(p => {
        const k = norm(p.nome).replace(/\s+inverso$/, "");
        if(p.descricao && !/^consulte/i.test(p.descricao) && !porNome[k]) porNome[k] = { fonte: f.nome, texto: p.descricao };
    }));
    function textoDe(p){
        if(p.descricao) return { texto: p.descricao, ref: "" };
        const r = porNome[norm(p.nome).replace(/\s+inverso$/, "")];
        return r ? { texto: r.texto, ref: "Descrição de " + r.fonte + (/inverso$/i.test(p.nome) ? " (aqui com reversão)" : "") } : { texto: "", ref: "" };
    }

    function sincronizar(avisar){
        campo.value = JSON.stringify(minhas);
        document.getElementById("cont-propriedades").textContent = minhas.length > 0 ? minhas.length : "";
        if(avisar) campo.dispatchEvent(new Event("input", { bubbles: true }));
    }

    function renderMinhas(){
        listaMinhas.innerHTML = minhas.map(function(p, i){
            return '<div class="prop-card" data-i="' + i + '">' +
                '<div class="prop-item-cab"><strong>' + escP(p.nome) + '</strong>' +
                    (p.fonte ? '<span class="prop-ameaca">' + escP(p.fonte) + '</span>' : '') +
                    '<button type="button" class="botao-remover-item prop-remover">Remover</button></div>' +
                (p.descricao ? '<p>' + escP(p.descricao) + '</p>' : '') +
                '<div class="prop-card-linha">' +
                    '<label>Ameaça <input type="number" class="prop-ameaca-input" value="' + (p.ameaca === null ? '' : escP(p.ameaca)) + '" placeholder="—"></label>' +
                    '<input type="text" class="prop-anotacao-input" maxlength="2000" placeholder="Anotação (alvo, duração, origem…)" value="' + escP(p.anotacao) + '">' +
                '</div></div>';
        }).join("");
        vazioMinhas.style.display = minhas.length === 0 ? "block" : "none";
    }

    function renderGrupos(){
        Object.keys(grupos).forEach(function(k){
            const o = document.createElement("option");
            o.value = k;
            o.textContent = grupos[k].nome || k;
            selGrupo.appendChild(o);
        });
    }

    function renderCatalogo(){
        const termo = norm(busca.value.trim());
        const grupo = selGrupo.value;
        descGrupo.textContent = grupo && grupos[grupo] ? (grupos[grupo].descricao || "") : "";
        // O catálogo só mostra resultados de uma pesquisa (2+ letras); o grupo apenas filtra esses resultados
        if(termo.length < 2){
            cat.innerHTML = "";
            vazioCat.textContent = "Digite o nome de uma propriedade, material ou efeito para pesquisar.";
            vazioCat.style.display = "block";
            return;
        }
        vazioCat.textContent = "Nenhuma propriedade encontrada.";
        let total = 0;
        cat.innerHTML = fontes.map(function(f, fi){
            if(grupo && f.categoria !== grupo) return "";
            const fonteBate = termo !== "" && norm(f.nome).includes(termo);
            const props = (f.propriedades || []).map((p, pi) => ({ p: p, pi: pi })).filter(function(x){
                if(termo === "" || fonteBate) return true;
                const d = textoDe(x.p);
                return norm(x.p.nome).includes(termo) || norm(d.texto).includes(termo) || norm(x.p.ganho).includes(termo);
            });
            if(props.length === 0) return "";
            total += props.length;
            const aberto = termo !== "" || f.categoria === "pessoal" || f.categoria === "variavel";
            return '<details class="prop-fonte"' + (aberto ? " open" : "") + '><summary>' + escP(f.nome) +
                ' <span class="prop-qtd">' + props.length + (props.length === 1 ? " propriedade" : " propriedades") + '</span></summary>' +
                (f.descricao ? '<p class="prop-fonte-descricao">' + escP(f.descricao) + '</p>' : '') +
                props.map(function(x){
                    const d = textoDe(x.p);
                    return '<div class="prop-item"><div class="prop-item-cab"><strong>' + escP(x.p.nome) + '</strong>' +
                        (temAmeaca(x.p) ? '<span class="prop-ameaca">Ameaça ' + escP(x.p.ameaca) + '</span>' : '') +
                        '<button type="button" class="button prop-salvar" data-f="' + fi + '" data-p="' + x.pi + '">Salvar</button></div>' +
                        (x.p.ganho ? '<p><em>Ganho de ameaça:</em> ' + escP(x.p.ganho) + '</p>' : '') +
                        (d.texto ? '<p>' + escP(d.texto) + '</p>' : '') +
                        (d.ref ? '<p class="prop-ref">' + escP(d.ref) + '</p>' : '') +
                    '</div>';
                }).join("") + '</details>';
        }).join("");
        vazioCat.style.display = total === 0 ? "block" : "none";
    }

    busca.addEventListener("input", renderCatalogo);
    selGrupo.addEventListener("change", renderCatalogo);

    cat.addEventListener("click", function(e){
        const b = e.target.closest(".prop-salvar");
        if(!b) return;
        const f = fontes[Number(b.dataset.f)];
        const p = f && f.propriedades ? f.propriedades[Number(b.dataset.p)] : null;
        if(!p) return;
        const d = textoDe(p);
        minhas.unshift({
            nome: p.nome,
            fonte: f.nome,
            ameaca: (typeof p.ameaca === "number") ? p.ameaca : null,
            descricao: ((p.ganho ? "Ganho de ameaça: " + p.ganho + " " : "") + d.texto).trim(),
            anotacao: ""
        });
        renderMinhas();
        sincronizar(true);
        b.textContent = "Salva ✓";
        setTimeout(function(){ b.textContent = "Salvar"; }, 1200);
    });

    listaMinhas.addEventListener("click", function(e){
        if(!e.target.classList.contains("prop-remover")) return;
        const i = Number(e.target.closest(".prop-card").dataset.i);
        if(!minhas[i] || !confirm("Remover “" + minhas[i].nome + "” das suas propriedades?")) return;
        minhas.splice(i, 1);
        renderMinhas();
        sincronizar(true);
    });

    listaMinhas.addEventListener("input", function(e){
        const card = e.target.closest(".prop-card");
        if(!card) return;
        const p = minhas[Number(card.dataset.i)];
        if(!p) return;
        if(e.target.classList.contains("prop-ameaca-input")) p.ameaca = e.target.value === "" ? null : (parseInt(e.target.value, 10) || 0);
        if(e.target.classList.contains("prop-anotacao-input")) p.anotacao = e.target.value;
        sincronizar(true);
    });

    document.getElementById("prop-pers-salvar").addEventListener("click", function(){
        const nome = document.getElementById("prop-pers-nome").value.trim();
        if(nome === ""){
            mostrarPopup("Digite um nome para a propriedade.");
            return;
        }
        const am = document.getElementById("prop-pers-ameaca").value;
        minhas.unshift({
            nome: nome,
            fonte: document.getElementById("prop-pers-fonte").value.trim(),
            ameaca: am === "" ? null : (parseInt(am, 10) || 0),
            descricao: document.getElementById("prop-pers-descricao").value.trim(),
            anotacao: ""
        });
        ["prop-pers-nome", "prop-pers-fonte", "prop-pers-ameaca", "prop-pers-descricao"].forEach(id => document.getElementById(id).value = "");
        document.getElementById("prop-personalizada").open = false;
        renderMinhas();
        sincronizar(true);
    });

    renderGrupos();
    renderMinhas();
    renderCatalogo();
    sincronizar(false);
})();

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

    // Libera bastante pontos de habilidade só pra esse preenchimento de teste.
    // Usa o campo oficial de pontos extras, assim o total fica consistente
    // em qualquer recálculo posterior (antes ficava negativo ao mexer no nível).
    campoExtraHabilidades.value = "500";
    campoExtraHabilidades.dispatchEvent(new Event("input"));

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
    document.getElementById("item-critico").value = "20, ×2";
    document.getElementById("item-tipo-dano").value = "cortante";
    document.getElementById("item-acao-uso").value = "Ação padrão";
    document.getElementById("item-peso").value = "0,5 kg";
    adicionarItem.click();

    // Descrição
    document.getElementById("descricao").value = "Ficha gerada automaticamente para teste.";

    mostrarPopup("Ficha preenchida com dados de teste! Já pode salvar.");
});

// ==================== Armaduras e resistências (aba dentro de Atributos Vitais) ====================
// Cálculo puro (sem DOM) entre os marcadores; carregado por deploy/tests/dano.test.mjs.
/*DANO-INICIO*/
const Dano = {
    TIPOS: ["cortante", "perfurante", "impacto", "explosivo", "flamejante", "congelante", "eletrizante", "tóxico", "amaldiçoado", "queda"],
    // Peças de armadura (e a armadura natural) só bloqueiam impacto, cortante e perfurante; os demais passam direto
    IGNORAM: ["explosivo", "amaldiçoado", "queda", "flamejante", "congelante", "eletrizante", "tóxico"],
    inteiro: function(v){ const n = parseInt(v, 10); return isNaN(n) ? 0 : n; },
    // Primeira peça equipada cujo alcance cobre o número d
    pecaAtingida: function(pecas, d){
        for(let i = 0; i < pecas.length; i++){
            const p = pecas[i];
            if(p.equipada && Dano.inteiro(p.alcance_min) <= d && d <= Dano.inteiro(p.alcance_max)) return p;
        }
        return null;
    },
    // Tipos reduzidos pela armadura natural
    NATURAL_TIPOS: ["impacto", "cortante", "perfurante"],
    // Rola uma expressão: inteiro, "NdM", "NdM+K", "NdM-K" ou soma de termos ("2d6+1d4+3").
    // rnd: função que devolve [0,1) (padrão Math.random). Devolve { ok, total, dados[], expr } ou { ok:false, erro }.
    rolar: function(texto, rnd){
        rnd = rnd || Math.random;
        const expr = String(texto == null ? "" : texto).replace(/\s+/g, "").toLowerCase();
        if(!expr) return { ok: false, erro: "Informe o valor do dano." };
        if(!/^[+-]?(\d+d\d+|\d+)([+-](\d+d\d+|\d+))*$/.test(expr)) return { ok: false, erro: "Dano inválido: \"" + String(texto).trim() + "\". Use número ou dados, ex.: 12, 2d6+3, 1d8-1." };
        const termos = expr.match(/[+-]?(\d+d\d+|\d+)/g);
        let total = 0, dice = 0;
        const dados = [];
        for(let i = 0; i < termos.length; i++){
            const t = termos[i];
            const sinal = t.charAt(0) === "-" ? -1 : 1;
            const corpo = t.replace(/^[+-]/, "");
            if(corpo.indexOf("d") >= 0){
                const p = corpo.split("d");
                const qtd = parseInt(p[0], 10), lados = parseInt(p[1], 10);
                if(qtd < 1 || qtd > 100 || lados < 2 || lados > 1000) return { ok: false, erro: "Dados inválidos em \"" + corpo + "\" (1–100 dados de 2–1000 lados)." };
                dice += qtd;
                for(let k = 0; k < qtd; k++){
                    const r = Math.floor(rnd() * lados) + 1;
                    dados.push(r);
                    total += sinal * r;
                }
            } else {
                total += sinal * parseInt(corpo, 10);
            }
        }
        return { ok: true, total: Math.max(0, total), dados: dados, expr: expr };
    },
    // Um ataque inteiro. entrada: { linhas:[{tipo, valor}], peca|null, natural, usarRes, resistencias[], imunidades[] }
    // Só impacto, cortante e perfurante são reduzidos; as reduções são "reservas" gastas uma vez por ataque, na ordem das linhas.
    // Ordem em cada linha: 1) armadura natural; 2) peça atingida no d100. Contra impacto/cortante, se a peça tiver
    // "contra corte/impacto" > 0, vale SÓ esse valor (a defesa comum não soma); senão, vale a defesa. Perfurante usa a defesa.
    // Depois: imunidade → 0; resistência → metade (arredondada para baixo), só na linha do tipo.
    // Ex.: 20 impacto + 20 tóxico, natural 5, peça defesa 5, resistência a tóxico → 20−5−5 = 10 e 20/2 = 10.
    calcularAtaque: function(e){
        const peca = e.peca || null;
        let resDefesa = peca ? Math.max(0, Dano.inteiro(peca.defesa)) : 0;
        let resContra = peca ? Math.max(0, Dano.inteiro(peca.contra_corte_impacto)) : 0;
        const temContra = resContra > 0;
        let resNatural = Math.max(0, Dano.inteiro(e.natural));
        let total = 0;
        const linhas = (e.linhas || []).map(function(l){
            const tipo = l.tipo;
            const valor = Math.max(0, Dano.inteiro(l.valor));
            const ignora = Dano.IGNORAM.indexOf(tipo) >= 0;
            let contra = 0, defesa = 0, natural = 0;
            let resto = valor;
            if(!ignora){
                natural = Math.min(resto, resNatural); resNatural -= natural; resto -= natural;
                if((tipo === "cortante" || tipo === "impacto") && temContra){
                    contra = Math.min(resto, resContra); resContra -= contra; resto -= contra;
                } else {
                    defesa = Math.min(resto, resDefesa); resDefesa -= defesa; resto -= defesa;
                }
            }
            const reduzido = resto;
            let final = reduzido, efeito = "";
            if(e.usarRes){
                if((e.imunidades || []).indexOf(tipo) >= 0){ final = 0; efeito = "imunidade"; }
                else if((e.resistencias || []).indexOf(tipo) >= 0){ final = Math.floor(reduzido / 2); efeito = "resistência"; }
                else if((e.fraquezas || []).indexOf(tipo) >= 0){ final = reduzido * 2; efeito = "fraqueza"; }
            }
            total += final;
            return { tipo: tipo, valor: valor, defesa: defesa, contra: contra, natural: natural, reduzido: reduzido, final: final, efeito: efeito, ignora: ignora };
        });
        return { linhas: linhas, total: total };
    },
    // Compatível com o uso de uma linha só
    calcular: function(e){
        const r = Dano.calcularAtaque({ linhas: [{ tipo: e.tipo, valor: e.valor }], peca: e.peca, natural: e.natural, usarRes: e.usarRes, resistencias: e.resistencias, imunidades: e.imunidades });
        return r.linhas[0];
    },
    // Texto de uma linha do log: "impacto 2d6+90 (4,3)=97 − 10 (natural) − 14 (corte/impacto) = 73 → resistência 36"
    textoLinha: function(l, rolagem){
        let s = l.tipo + " " + rolagem.expr;
        if(rolagem.dados.length) s += " (" + rolagem.dados.join(",") + ")=" + l.valor;
        let red = false;
        if(l.natural){ s += " − " + l.natural + " (natural)"; red = true; }
        if(l.contra){ s += " − " + l.contra + " (corte/impacto)"; red = true; }
        if(l.defesa){ s += " − " + l.defesa + " (defesa)"; red = true; }
        if(red) s += " = " + l.reduzido;
        if(l.efeito) s += " → " + l.efeito + " " + l.final;
        else if(!red && !rolagem.dados.length) s += " = " + l.final;
        return s;
    }
};
/*DANO-FIM*/

(function(){
    const hidden = document.getElementById("armaduras");
    if(!hidden) return;
    const somenteLeitura = {{ $somenteLeitura ? 'true' : 'false' }};
    const dados = @json($armaduraSalva);
    const tipos = Dano.TIPOS;
    const num = Dano.inteiro;

    let pecas = (Array.isArray(dados.pecas) ? dados.pecas : []).map(normalizar);
    let resistencias = (Array.isArray(dados.resistencias) ? dados.resistencias : []).filter(t => tipos.indexOf(t) >= 0);
    let imunidades = (Array.isArray(dados.imunidades) ? dados.imunidades : []).filter(t => tipos.indexOf(t) >= 0 && resistencias.indexOf(t) < 0);
    let fraquezas = (Array.isArray(dados.fraquezas) ? dados.fraquezas : []).filter(t => tipos.indexOf(t) >= 0 && resistencias.indexOf(t) < 0 && imunidades.indexOf(t) < 0);
    // condições do livro (regras de combate e de Kuros); as que não estão na lista vão em "condições especiais"
    const CONDICOES = ["amedrontado", "cego", "surdo", "ofuscado", "desprevenido", "caído", "indefeso", "imobilizado", "sangrando",
        "vulnerável", "ferida grave", "ferida duradoura", "morrendo", "hexo-limitado"];
    let condicoes = (Array.isArray(dados.condicoes) ? dados.condicoes : []).filter(t => CONDICOES.indexOf(t) >= 0);
    let condicoesEspeciais = typeof dados.condicoes_especiais === "string" ? dados.condicoes_especiais : "";
    let natural = Math.max(0, num(dados.armadura_natural));
    const editando = new Set();
    const logs = [];

    function normalizar(p){
        p = p || {};
        const peso = parseFloat(String(p.peso).replace(",", "."));
        return {
            nome: String(p.nome || ""), descricao: String(p.descricao || ""), tamanho: String(p.tamanho || ""),
            alcance_min: num(p.alcance_min), alcance_max: num(p.alcance_max), defesa: num(p.defesa),
            contra_corte_impacto: num(p.contra_corte_impacto), defesa_base: num(p.defesa_base),
            penalidade_destreza: Math.abs(num(p.penalidade_destreza)), peso: isNaN(peso) ? 0 : peso, equipada: !!p.equipada,
            _id: Math.random().toString(36).slice(2)
        };
    }
    function esc(s){ return String(s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c])); }
    function fmtPeso(n){ return (Math.round(n * 100) / 100).toString().replace(".", ","); }
    function cap(t){ return t.charAt(0).toUpperCase() + t.slice(1); }

    function sincronizar(silencioso){
        hidden.value = JSON.stringify({
            pecas: pecas.map(p => { const c = Object.assign({}, p); delete c._id; return c; }),
            armadura_natural: natural, resistencias: resistencias, imunidades: imunidades,
            fraquezas: fraquezas, condicoes: condicoes, condicoes_especiais: condicoesEspeciais
        });
        mostrarCondicoesNoTopo();
        if(!silencioso) hidden.dispatchEvent(new Event("input", { bubbles: true }));
    }

    function atualizarResumo(){
        const eq = pecas.filter(p => p.equipada);
        const base = eq.reduce((s, p) => s + p.defesa_base, 0);
        const pen = eq.reduce((s, p) => s + p.penalidade_destreza, 0);
        const peso = eq.reduce((s, p) => s + p.peso, 0);
        document.getElementById("armadura-resumo").textContent =
            "Defesa base +" + base + " · Penalidade de destreza −" + pen + " · Peso " + fmtPeso(peso) + " kg";
        // sobreposição de faixas entre equipadas
        const sobrepoe = [];
        for(let i = 0; i < eq.length; i++) for(let j = i + 1; j < eq.length; j++){
            if(eq[i].alcance_min <= eq[j].alcance_max && eq[j].alcance_min <= eq[i].alcance_max) sobrepoe.push((eq[i].nome || "?") + " e " + (eq[j].nome || "?"));
        }
        const aviso = document.getElementById("armadura-aviso");
        aviso.style.display = sobrepoe.length ? "" : "none";
        aviso.textContent = sobrepoe.length ? "Atenção: faixas de d100 sobrepostas entre peças equipadas (" + sobrepoe.join("; ") + "). Vale a primeira da lista." : "";
    }

    const campos = [
        ["nome", "Nome", "text", true], ["tamanho", "Tamanho", "text", false],
        ["alcance_min", "d100 de", "number", false], ["alcance_max", "d100 até", "number", false],
        ["defesa", "Defesa", "number", false], ["contra_corte_impacto", "Contra corte/impacto", "number", false],
        ["defesa_base", "Defesa base", "number", false], ["penalidade_destreza", "Penal. destreza", "number", false],
        ["peso", "Peso (kg)", "number", false]
    ];

    function htmlPeca(p){
        const id = esc(p._id);
        const edit = editando.has(p._id);
        let h = '<div class="arm-peca' + (p.equipada ? " equipada" : "") + '" data-id="' + id + '">';
        h += '<div class="arm-peca-topo"><span class="arm-peca-nome">' + esc(p.nome || "(sem nome)") + '</span>' +
             '<span class="arm-faixa">d100: ' + p.alcance_min + '–' + p.alcance_max + '</span></div>';
        if(edit){
            h += '<div class="arm-edit">';
            campos.forEach(function(c){
                h += '<label class="' + (c[3] ? "larga" : "") + '">' + c[1] + '<input type="' + c[2] + '" data-campo="' + c[0] + '"' +
                     (c[2] === "number" ? ' step="' + (c[0] === "peso" ? "0.1" : "1") + '"' : "") + ' value="' + esc(p[c[0]]) + '"></label>';
            });
            h += '<label class="larga">Descrição<textarea rows="2" data-campo="descricao">' + esc(p.descricao) + '</textarea></label></div>';
        } else {
            h += '<div class="arm-peca-stats"><span>Defesa +' + p.defesa + '</span><span>Corte/impacto +' + p.contra_corte_impacto + '</span>' +
                 '<span>Defesa base ' + p.defesa_base + '</span><span>Penal. destreza −' + p.penalidade_destreza + '</span>' +
                 '<span>' + fmtPeso(p.peso) + ' kg</span>' + (p.tamanho ? '<span>' + esc(p.tamanho) + '</span>' : "") + '</div>';
            if(p.descricao) h += '<div class="arm-peca-desc">' + esc(p.descricao) + '</div>';
        }
        h += '<div class="arm-peca-acoes"><label><input type="checkbox" data-acao="equipar"' + (p.equipada ? " checked" : "") + '> Equipada</label>' +
             '<button type="button" class="arm-mini" data-acao="editar">' + (edit ? "Concluir" : "Editar") + '</button>' +
             '<button type="button" class="arm-mini" data-acao="remover">Remover</button></div></div>';
        return h;
    }

    function renderizarLista(){
        const lista = document.getElementById("armadura-lista");
        lista.innerHTML = pecas.length ? pecas.map(htmlPeca).join("") : '<p class="vazio-lista">Nenhuma peça de armadura.</p>';
        if(somenteLeitura){
            lista.querySelectorAll("input, textarea").forEach(el => { if(el.type === "checkbox") el.disabled = true; else el.readOnly = true; });
            lista.querySelectorAll("button").forEach(b => { b.disabled = true; });
        }
        atualizarResumo();
    }

    function renderizarChips(){
        function montar(id, atuais, outro, tipo){
            const el = document.getElementById(id);
            el.innerHTML = tipos.map(t => '<button type="button" class="arm-chip' + (atuais.indexOf(t) >= 0 ? " ativo" : "") + '" data-tipo="' + t + '">' + cap(t) + '</button>').join("");
            if(somenteLeitura) el.querySelectorAll("button").forEach(b => { b.disabled = true; });
        }
        montar("armadura-chips-res", resistencias);
        montar("armadura-chips-imu", imunidades);
        montar("armadura-chips-fra", fraquezas);
        const cond = document.getElementById("armadura-chips-cond");
        cond.innerHTML = CONDICOES.map(t => '<button type="button" class="arm-chip arm-chip--cond' + (condicoes.indexOf(t) >= 0 ? " ativo" : "") + '" data-tipo="' + t + '">' + cap(t) + '</button>').join("");
        if(somenteLeitura) cond.querySelectorAll("button").forEach(b => { b.disabled = true; });
    }
    // as condições marcadas e as especiais aparecem num aviso no topo da ficha
    function mostrarCondicoesNoTopo(){
        const el = document.getElementById("ficha-condicoes-topo");
        if(!el) return;
        const partes = condicoes.map(cap).concat(condicoesEspeciais.trim() ? [condicoesEspeciais.trim()] : []);
        el.hidden = !partes.length && !fraquezas.length;
        el.innerHTML = (partes.length ? "<strong>Condições:</strong> " + esc(partes.join(" · ")) : "") +
            (fraquezas.length ? (partes.length ? "<br>" : "") + "<strong>Fraquezas:</strong> " + esc(fraquezas.map(cap).join(", ")) : "");
    }

    function achar(el){ const c = el.closest(".arm-peca"); return c ? pecas.find(p => p._id === c.dataset.id) : null; }

    const lista = document.getElementById("armadura-lista");
    lista.addEventListener("input", function(e){
        const p = achar(e.target), campo = e.target.dataset.campo;
        if(!p || !campo) return;
        const v = e.target.value;
        if(campo === "nome" || campo === "descricao" || campo === "tamanho") p[campo] = v;
        else if(campo === "peso"){ const n = parseFloat(String(v).replace(",", ".")); p.peso = isNaN(n) ? 0 : Math.max(0, n); }
        else if(campo === "penalidade_destreza") p[campo] = Math.abs(num(v));
        else p[campo] = num(v);
        atualizarResumo();
        sincronizar();
    });
    lista.addEventListener("change", function(e){
        const p = achar(e.target);
        if(p && e.target.dataset.acao === "equipar"){
            p.equipada = e.target.checked;
            e.target.closest(".arm-peca").classList.toggle("equipada", p.equipada);
            atualizarResumo(); sincronizar();
        }
    });
    lista.addEventListener("click", function(e){
        const b = e.target.closest("button[data-acao]");
        if(!b || somenteLeitura) return;
        const p = achar(b);
        if(!p) return;
        if(b.dataset.acao === "editar"){
            if(editando.has(p._id)) editando.delete(p._id); else editando.add(p._id);
            renderizarLista();
        } else if(b.dataset.acao === "remover"){
            if(!confirm("Remover a peça \"" + (p.nome || "sem nome") + "\"?")) return;
            pecas = pecas.filter(x => x !== p);
            editando.delete(p._id);
            renderizarLista(); sincronizar();
        }
    });

    document.getElementById("armadura-nova").addEventListener("click", function(){
        if(somenteLeitura) return;
        const p = normalizar({ nome: "", alcance_min: 0, alcance_max: 0 });
        pecas.push(p);
        editando.add(p._id);
        renderizarLista(); sincronizar();
    });

    const campoNatural = document.getElementById("armadura-natural");
    campoNatural.value = natural;
    campoNatural.addEventListener("input", function(){ natural = Math.max(0, num(campoNatural.value)); sincronizar(); });

    function aoClicarChip(id, alvo, outras){
        document.getElementById(id).addEventListener("click", function(e){
            const b = e.target.closest(".arm-chip");
            if(!b || somenteLeitura) return;
            const t = b.dataset.tipo;
            const lst = alvo();
            const i = lst.indexOf(t);
            if(i >= 0) lst.splice(i, 1);
            else {
                lst.push(t);
                outras().forEach(o => { const j = o.indexOf(t); if(j >= 0) o.splice(j, 1); });
            }
            renderizarChips(); sincronizar();
        });
    }
    aoClicarChip("armadura-chips-res", () => resistencias, () => [imunidades, fraquezas]);
    aoClicarChip("armadura-chips-imu", () => imunidades, () => [resistencias, fraquezas]);
    aoClicarChip("armadura-chips-fra", () => fraquezas, () => [resistencias, imunidades]);
    aoClicarChip("armadura-chips-cond", () => condicoes, () => []);
    const campoCondEsp = document.getElementById("armadura-cond-especiais");
    campoCondEsp.value = condicoesEspeciais;
    if(somenteLeitura) campoCondEsp.disabled = true;
    campoCondEsp.addEventListener("input", function(){ condicoesEspeciais = campoCondEsp.value.slice(0, 1000); sincronizar(); });

    function rolarD100(){ return Math.floor(Math.random() * 100) + 1; }

    function marcarAtingida(p){
        lista.querySelectorAll(".arm-peca.atingida").forEach(c => c.classList.remove("atingida")); // sem re-renderizar (vale também na leitura)
        if(p){
            const c = lista.querySelector('.arm-peca[data-id="' + p._id + '"]');
            if(c){ c.classList.add("atingida"); c.scrollIntoView({ block: "nearest", behavior: "smooth" }); }
        }
    }

    // O local rolado aqui é o que o próximo "Aplicar dano" usa (a peça destacada é a que reduz o dano).
    // Sem local rolado, o "Aplicar dano" rola o d100 sozinho.
    let localRolado = null;
    document.getElementById("armadura-rolar-local").addEventListener("click", function(){
        const d = rolarD100();
        const p = Dano.pecaAtingida(pecas, d);
        marcarAtingida(p);
        if(!somenteLeitura) localRolado = d;
        document.getElementById("armadura-rolar-res").textContent = "Rolou " + d + " → " + (p ? (p.nome || "peça sem nome") : "sem armadura") +
            (somenteLeitura ? "" : " (vale para o próximo dano)");
    });

    // ---- Linhas de dano (valor/expressão + tipo + ✕); sempre há pelo menos uma ----
    const caixaLinhas = document.getElementById("armadura-dano-linhas");
    function novaLinhaDano(tipo, valor){
        const l = document.createElement("div");
        l.className = "arm-dano-linha";
        const v = document.createElement("input");
        v.type = "text"; v.className = "arm-dano-valor"; v.value = valor == null ? "0" : valor;
        v.placeholder = "ex.: 12 ou 2d6+3"; v.setAttribute("aria-label", "Valor do dano (número ou dados)");
        v.setAttribute("autocomplete", "off"); v.setAttribute("autocapitalize", "off"); v.spellcheck = false;
        const s = document.createElement("select");
        s.className = "arm-dano-tipo"; s.setAttribute("aria-label", "Tipo de dano");
        tipos.forEach(function(t){ const o = document.createElement("option"); o.value = t; o.textContent = cap(t); s.appendChild(o); });
        s.value = tipo || tipos[0];
        const x = document.createElement("button");
        x.type = "button"; x.className = "arm-mini"; x.textContent = "✕"; x.setAttribute("aria-label", "Remover este tipo de dano");
        x.addEventListener("click", function(){
            if(caixaLinhas.children.length <= 1) return;
            l.remove(); atualizarX();
        });
        l.append(v, s, x);
        caixaLinhas.appendChild(l);
        atualizarX();
        return l;
    }
    function atualizarX(){
        const unica = caixaLinhas.children.length <= 1;
        caixaLinhas.querySelectorAll(".arm-mini").forEach(b => { b.disabled = unica || somenteLeitura; });
    }
    novaLinhaDano();
    document.getElementById("armadura-dano-mais").addEventListener("click", function(){
        if(somenteLeitura) return;
        const ult = caixaLinhas.lastElementChild.querySelector("select").value;
        const prox = novaLinhaDano(tipos[(tipos.indexOf(ult) + 1) % tipos.length]);
        prox.querySelector("input").focus();
    });

    // Muda a Vida pelo mesmo caminho dos demais fluxos (descanso, gastar Aura):
    // o texto do span "atual / máximo" é a fonte da verdade (lido por prepararFichaParaSalvar e observado por vitais.js);
    // também grava o campo oculto vida_atual, ajusta a barra e dispara "input" no formulário para o salvamento automático.
    function lerVida(){
        const vidaEl = document.getElementById("vida");
        const p = vidaEl.textContent.split("/").map(v => parseInt(v.trim(), 10));
        let atual = p[0], maximo = p[1];
        if(isNaN(atual) || isNaN(maximo)){ // "? / ?": tenta os campos ocultos
            atual = parseInt(document.getElementById("vida_atual").value, 10);
            maximo = parseInt(document.getElementById("vida_maxima").value, 10);
        }
        return { atual: atual, maximo: maximo };
    }
    function gravarVida(novo, maximo){
        document.getElementById("vida").textContent = novo + " / " + maximo;
        const campo = document.getElementById("vida_atual");
        campo.value = novo;
        document.getElementById("vida_maxima").value = maximo;
        const barra = document.querySelector(".barra-vida");
        if(barra && maximo > 0) barra.style.width = Math.max(0, Math.min(100, (novo / maximo) * 100)) + "%";
        campo.dispatchEvent(new Event("input", { bubbles: true }));
    }

    document.getElementById("armadura-aplicar-dano").addEventListener("click", function(){
        if(somenteLeitura) return;
        const v = lerVida();
        if(isNaN(v.atual) || isNaN(v.maximo)){ mostrarPopup("Defina sua Vida antes de aplicar dano."); return; }

        // 1) rola todas as linhas (valida antes de mexer em qualquer coisa)
        const rolagens = [], entradas = [];
        const linhasDom = caixaLinhas.querySelectorAll(".arm-dano-linha");
        for(let i = 0; i < linhasDom.length; i++){
            const r = Dano.rolar(linhasDom[i].querySelector("input").value);
            if(!r.ok){ mostrarPopup(r.erro); linhasDom[i].querySelector("input").focus(); return; }
            rolagens.push(r);
            entradas.push({ tipo: linhasDom[i].querySelector("select").value, valor: r.total });
        }

        // 2) um único d100 de local por ataque: o do botão "Rolar local", se houver; senão rola agora
        const d = localRolado != null ? localRolado : rolarD100();
        localRolado = null;
        const peca = Dano.pecaAtingida(pecas, d);
        marcarAtingida(peca);
        document.getElementById("armadura-rolar-res").textContent = "Local " + d + " → " + (peca ? (peca.nome || "peça sem nome") : "sem armadura");
        const usarRes = document.getElementById("armadura-dano-usar").checked;
        const res = Dano.calcularAtaque({ linhas: entradas, peca: peca, natural: natural, usarRes: usarRes, resistencias: resistencias, imunidades: imunidades, fraquezas: fraquezas });

        // 3) desconta dos PVs (pode ficar negativo)
        const novo = v.atual - res.total;
        gravarVida(novo, v.maximo);

        let txt = "Rolou " + d + " → " + (peca ? (peca.nome || "peça").replace(/ de armadura$/i, "") : "sem armadura") + " · " +
            res.linhas.map((l, i) => Dano.textoLinha(l, rolagens[i])).join(" · ");
        txt += " · total " + res.total + " · PV " + v.atual + " → " + novo;
        logs.unshift(txt);
        logs.length = Math.min(logs.length, 5);
        registrarRolagem("Dano recebido", res.total, txt);
        document.getElementById("armadura-log").innerHTML = logs.map(l => "<div>" + esc(l) + "</div>").join("");
    });

    renderizarLista();
    renderizarChips();
    sincronizar(true);
})();

// ==================== Artes marciais ====================
// Cálculo puro (sem DOM) entre os marcadores; carregado por deploy/tests/artes-marciais.test.mjs.
// A lista de artes deve bater com Personagem::ARTES_MARCIAIS.
/*ARTES-INICIO*/
const ArtesMarciais = {
    ARTES: [["esgrima", "Esgrima"], ["hexomancia", "Hexomancia"], ["fisiomancia", "Fisiomancia"], ["boxing", "Boxing"],
            ["wrestling", "Wrestling"], ["tiro_tatico", "Tiro tático"], ["kuro_stomper", "Kuro Stomper"]],
    BASE: 20,      // barra de experiência no nível 0: 0/20
    POR_NIVEL: 80, // +80 no máximo da barra por nível de arte
    CRUZADO: 15,   // cada nível em Fisiomancia soma +15 na barra de Hexomancia, e vice-versa
    PARES: { hexomancia: "fisiomancia", fisiomancia: "hexomancia" },
    nat: function(v){ const n = parseInt(v, 10); return isNaN(n) || n < 0 ? 0 : n; },
    // Garante todas as artes com { nivel, xp, talentos[] }
    normalizar: function(dados){
        dados = dados && typeof dados === "object" ? dados : {};
        const out = {};
        ArtesMarciais.ARTES.forEach(function(par){
            const a = dados[par[0]] && typeof dados[par[0]] === "object" ? dados[par[0]] : {};
            out[par[0]] = {
                nivel: ArtesMarciais.nat(a.nivel),
                xp: ArtesMarciais.nat(a.xp),
                talentos: (Array.isArray(a.talentos) ? a.talentos : []).map(function(t){
                    t = t || {};
                    return { nome: String(t.nome || ""), custo: ArtesMarciais.nat(t.custo), descricao: String(t.descricao || ""), ativo: !!t.ativo };
                })
            };
        });
        return out;
    },
    // Máximo da barra: 20 + 80 × nível da arte (+ 15 × nível da arte "par", para Hexomancia/Fisiomancia)
    xpMaximo: function(artes, chave){
        const a = artes[chave] || {};
        let m = ArtesMarciais.BASE + ArtesMarciais.POR_NIVEL * ArtesMarciais.nat(a.nivel);
        const par = ArtesMarciais.PARES[chave];
        if(par && artes[par]) m += ArtesMarciais.CRUZADO * ArtesMarciais.nat(artes[par].nivel);
        return m;
    },
    // Sobe um nível se a barra estiver cheia: gasta o máximo atual e soma 1 ao nível. Devolve true se subiu.
    subirNivel: function(artes, chave){
        const a = artes[chave];
        const max = ArtesMarciais.xpMaximo(artes, chave);
        if(!a || a.xp < max) return false;
        a.xp -= max;
        a.nivel += 1;
        return true;
    }
};
/*ARTES-FIM*/

(function(){
    const hidden = document.getElementById("artes_marciais");
    if(!hidden) return;
    const somenteLeitura = {{ $somenteLeitura ? 'true' : 'false' }};
    const AM = ArtesMarciais;
    const artes = AM.normalizar(@json($artesSalvas));
    const abas = document.getElementById("am-abas");
    const corpo = document.getElementById("am-corpo");
    const card = document.getElementById("artes-marciais-card");
    let atual = AM.ARTES[0][0];
    try { const s = localStorage.getItem("ficha-arte-marcial"); if(s && artes[s]) atual = s; } catch(e) {}

    function esc(s){ return String(s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c])); }
    function nomeDe(chave){ const p = AM.ARTES.find(x => x[0] === chave); return p ? p[1] : chave; }

    function sincronizar(silencioso){
        hidden.value = JSON.stringify(artes);
        const total = AM.ARTES.reduce((s, p) => s + artes[p[0]].talentos.length, 0);
        document.getElementById("cont-artes-marciais").textContent = total ? "(" + total + ")" : "";
        if(!silencioso) hidden.dispatchEvent(new Event("input", { bubbles: true }));
    }

    function renderAbas(){
        abas.innerHTML = AM.ARTES.map(function(p){
            const n = artes[p[0]].nivel;
            return '<button type="button" class="aba-painel am-aba' + (p[0] === atual ? " ativo" : "") + '" role="tab" data-arte="' + p[0] + '">' +
                esc(p[1]) + '<span class="am-aba-nivel">' + (n ? "nv " + n : "") + '</span></button>';
        }).join("");
    }

    function atualizarBarra(){
        const a = artes[atual];
        const max = AM.xpMaximo(artes, atual);
        const fill = corpo.querySelector(".am-barra-fill");
        if(!fill) return;
        fill.style.width = Math.min(100, max > 0 ? (a.xp / max) * 100 : 0) + "%";
        corpo.querySelector(".am-barra-texto").textContent = a.xp + " / " + max;
        let regra = "Máximo = 20 + 80 × nível de arte (" + a.nivel + ")";
        const par = AM.PARES[atual];
        if(par) regra += " + 15 × nível em " + nomeDe(par) + " (" + artes[par].nivel + ")";
        corpo.querySelector(".am-xp-regra").textContent = regra + " = " + max;
        const subir = corpo.querySelector(".am-subir");
        subir.disabled = somenteLeitura || a.xp < max;
        subir.title = a.xp < max ? "Faltam " + (max - a.xp) + " de experiência" : "Gasta " + max + " de experiência e sobe 1 nível";
    }

    function resumoTalentos(){
        const ts = artes[atual].talentos;
        const ativos = ts.filter(t => t.ativo);
        const el = corpo.querySelector(".am-talentos-resumo");
        if(el) el.textContent = ts.length ? ts.length + " talento(s) · ativos custam " + ativos.reduce((s, t) => s + t.custo, 0) + " Aura" : "";
    }

    function htmlTalento(t, i){
        return '<div class="am-talento' + (t.ativo ? " ativo" : "") + '" data-i="' + i + '">' +
            '<label>Nome<input type="text" data-campo="nome" maxlength="120" value="' + esc(t.nome) + '" placeholder="Nome do talento"></label>' +
            '<label>Custo (Aura)<input type="number" min="0" data-campo="custo" value="' + t.custo + '"></label>' +
            '<label class="larga">Descrição<textarea rows="2" data-campo="descricao" maxlength="5000" placeholder="O que o talento faz">' + esc(t.descricao) + '</textarea></label>' +
            '<div class="am-talento-acoes">' +
                '<button type="button" class="arm-mini" data-acao="ativar">' + (t.ativo ? "Desativar" : "Ativar") + '</button>' +
                '<button type="button" class="arm-mini" data-acao="remover">Remover</button>' +
            '</div></div>';
    }

    function render(){
        renderAbas();
        const a = artes[atual];
        corpo.innerHTML =
            '<div class="am-linha"><label for="am-nivel">Nível de arte em ' + esc(nomeDe(atual)) + '</label>' +
                '<input type="number" id="am-nivel" min="0" max="999" value="' + a.nivel + '"></div>' +
            '<div class="am-xp">' +
                '<div class="am-linha"><label for="am-xp">Experiência de arte</label>' +
                    '<input type="number" id="am-xp" min="0" value="' + a.xp + '">' +
                    '<button type="button" class="arm-mini am-subir">Subir de nível</button></div>' +
                '<div class="am-barra"><div class="am-barra-fill"></div><span class="am-barra-texto"></span></div>' +
                '<p class="am-xp-regra"></p>' +
            '</div>' +
            '<div class="am-talentos-topo"><h4>Talentos de ' + esc(nomeDe(atual)) + '</h4><span class="am-talentos-resumo"></span></div>' +
            '<div class="am-talentos">' + (a.talentos.length ? a.talentos.map(htmlTalento).join("") : '<p class="vazio-lista">Nenhum talento nesta arte.</p>') + '</div>' +
            '<button type="button" class="botao-adicionar-talento am-adicionar">+ Adicionar talento</button>';
        if(somenteLeitura){
            corpo.querySelectorAll("input, textarea").forEach(el => { el.readOnly = true; });
            corpo.querySelectorAll("button").forEach(b => { b.disabled = true; });
        }
        atualizarBarra();
        resumoTalentos();
    }

    // Lista do livro: o talento entra na arte da aba atual, já preenchido e editável
    const livroEl = document.getElementById("am-livro");
    const livro = livroEl ? LivroTalentos.montar(livroEl, atual, function(t){
        const a = artes[atual];
        if(a.talentos.length >= 60){ alert("Limite de 60 talentos por arte."); return; }
        a.talentos.push({ nome: t.nome, custo: t.custo || 0, descricao: LivroTalentos.descricaoCompleta(t), ativo: false });
        render(); sincronizar();
        const ult = corpo.querySelector(".am-talento:last-child");
        if(ult) ult.scrollIntoView({ block: "nearest", behavior: "smooth" });
    }) : null;

    abas.addEventListener("click", function(e){
        const b = e.target.closest(".am-aba");
        if(!b) return;
        atual = b.dataset.arte;
        try { localStorage.setItem("ficha-arte-marcial", atual); } catch(err) {}
        render();
        if(livro) livro.grupo(atual);
    });

    corpo.addEventListener("input", function(e){
        if(somenteLeitura) return;
        const a = artes[atual];
        const t = e.target;
        if(t.id === "am-nivel"){ a.nivel = Math.min(999, AM.nat(t.value)); renderAbas(); atualizarBarra(); }
        else if(t.id === "am-xp"){ a.xp = AM.nat(t.value); atualizarBarra(); }
        else if(t.dataset.campo){
            const c = t.closest(".am-talento");
            const tal = c ? a.talentos[Number(c.dataset.i)] : null;
            if(!tal) return;
            if(t.dataset.campo === "custo"){ tal.custo = AM.nat(t.value); resumoTalentos(); }
            else tal[t.dataset.campo] = t.value;
        } else return;
        sincronizar();
    });

    corpo.addEventListener("click", function(e){
        if(somenteLeitura) return;
        const b = e.target.closest("button");
        if(!b) return;
        const a = artes[atual];
        if(b.classList.contains("am-subir")){
            if(AM.subirNivel(artes, atual)){ render(); sincronizar(); }
        } else if(b.classList.contains("am-adicionar")){
            a.talentos.push({ nome: "", custo: 0, descricao: "", ativo: false });
            render(); sincronizar();
            const ult = corpo.querySelector(".am-talento:last-child input[data-campo=nome]");
            if(ult) ult.focus();
        } else if(b.dataset.acao){
            const c = b.closest(".am-talento");
            const i = c ? Number(c.dataset.i) : -1;
            const tal = a.talentos[i];
            if(!tal) return;
            if(b.dataset.acao === "ativar"){
                tal.ativo = !tal.ativo;
                c.classList.toggle("ativo", tal.ativo);
                b.textContent = tal.ativo ? "Desativar" : "Ativar";
                resumoTalentos();
            } else if(b.dataset.acao === "remover"){
                if(!confirm("Remover o talento \"" + (tal.nome || "sem nome") + "\"?")) return;
                a.talentos.splice(i, 1);
                render();
            }
            sincronizar();
        }
    });

    document.getElementById("artes-marciais-abrir").addEventListener("click", () => card.classList.add("ativo"));
    document.getElementById("fechar-artes-marciais").addEventListener("click", () => card.classList.remove("ativo"));

    render();
    sincronizar(true);
})();

@if ($somenteLeitura)
// ==================== Somente leitura (mestre vendo a ficha de um jogador) ====================
// Tudo é mostrado como na ficha normal, mas os campos ficam travados e nada é salvo.
// Continuam funcionando: abrir/fechar painéis, recolher seções, filtrar pastas e rolar habilidades/dados.
(function(){
    const form = document.getElementById("form-ficha");
    form.addEventListener("submit", function(e){ e.preventDefault(); });

    form.querySelectorAll("input, select, textarea").forEach(function(el){
        if(el.type === "hidden" || el.id === "prop-busca" || el.id === "prop-grupo") return; // a pesquisa do catálogo continua livre
        if(el.tagName === "SELECT" || ["checkbox", "file", "color", "range", "number"].includes(el.type)) el.disabled = true;
        else el.readOnly = true;
    });

    form.querySelectorAll("button").forEach(function(b){
        const marca = (b.id || "") + " " + (b.className || "");
        const permitido = /abrir|fechar|ocultar|botao-rolar-habilidade|rolar-dado|armadura-rolar-local|rolarD100|pasta-chip|pasta-link|aba-painel/i.test(marca)
            || b.closest(".popup");
        if(!permitido) b.disabled = true;
    });
})();
@endif

</script>
@endsection