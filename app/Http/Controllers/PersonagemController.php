<?php

namespace App\Http\Controllers;

use App\Support\Esquema;
use App\Support\Plano;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;
use App\Models\Personagem;

class PersonagemController extends Controller
{
    public function home()
    {
        // iniciante que ainda não disse se veio comprar os livros ou jogar
        $user = auth()->user();
        if ($user && Plano::temColunas() && empty($user->objetivo) && !session('objetivo_escolhido')) {
            return redirect()->route('boas-vindas');
        }

        return view('dashboard.home');
    }

    public function fichas()
    {
        if (Plano::fichasRestantes(auth()->user()) === 0) {
            return redirect()->route('planos.index')->with('limite', Plano::mensagemLimite('fichas'));
        }

        return view('personagem.form');
    }

    public function fichafeita(Request $request)
    {
        if (Plano::fichasRestantes($request->user()) === 0) {
            return redirect()->route('planos.index')->with('limite', Plano::mensagemLimite('fichas'));
        }
        $dados = $this->validarFicha($request);
        $personagem = Personagem::create($dados);

        return redirect()->route('personagens.show', $personagem->id);
    }

    /**
     * Aba Dinheiro da ficha: ganha/gasta (delta) ou define o saldo, gravando na hora (fora do salvamento da ficha, para
     * não brigar com o autosave nem com as apostas do Gladio, que mexem no mesmo saldo).
     */
    public function dinheiro(Request $request, string $id)
    {
        if (!Esquema::coluna('fichas', 'dinheiro')) {
            return response()->json(['ok' => false, 'erro' => 'O Dinheiro das fichas ainda não existe no banco: rode o SQL da migração 2026_10_29_000019.'], 422);
        }
        $d = $request->validate([
            'delta'    => ['nullable', 'integer', 'min:-1000000000', 'max:1000000000'],
            'definir'  => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ]);
        try {
            $saldo = $this->mexerDinheiro($id, $d);
        } catch (\InvalidArgumentException $x) {
            return response()->json(['ok' => false, 'erro' => $x->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'dinheiro' => $saldo]);
    }

    private function mexerDinheiro(string $id, array $d): int
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($id, $d) {
            $p = Personagem::lockForUpdate()->findOrFail($id); // o escopo do dono: só a própria ficha
            $atual = (int) $p->dinheiro;
            $novo = isset($d['definir']) ? (int) $d['definir'] : $atual + (int) ($d['delta'] ?? 0);
            if ($novo < 0) {
                throw new \InvalidArgumentException('Não há Dinheiro suficiente.');
            }
            $p->forceFill(['dinheiro' => min($novo, 1000000000)])->save();

            return (int) $p->dinheiro;
        });
    }

    public function update(Request $request, string $id)
    {
        $personagem = Personagem::findOrFail($id);
        $dados = $this->validarFicha($request);
        $personagem->update($dados);

        // Autosave (fetch): responde JSON e não grava a mensagem de sucesso na sessão
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('personagens.show', $personagem->id)
            ->with('sucesso', 'Ficha atualizada com sucesso!');
    }

    private function validarFicha(Request $request): array
    {
        // Decodifica o JSON do campo oculto ANTES de validar
        $request->merge([
            'vitais_extras' => json_decode($request->input('vitais_extras') ?: '[]', true) ?: [],
        ]);

        // Decodifica o JSON das anotações ANTES de validar
        $request->merge([
            'anotacoes' => json_decode($request->input('anotacoes') ?: '[]', true) ?: [],
        ]);

        // Decodifica o JSON das técnicas ANTES de validar
        $request->merge([
            'tecnicas' => json_decode($request->input('tecnicas') ?: '[]', true) ?: [],
        ]);

        // Decodifica o JSON dos talentos mundanos ANTES de validar
        $request->merge([
            'talentos' => json_decode($request->input('talentos') ?: '[]', true) ?: [],
        ]);

        // Decodifica o JSON das pastas (talentos / técnicas / anotações) ANTES de validar
        $request->merge([
            'pastas' => json_decode($request->input('pastas') ?: '{}', true) ?: [],
        ]);

        // Decodifica o JSON dos itens e ações ANTES de validar
        $request->merge([
            'itens' => json_decode($request->input('itens') ?: '[]', true) ?: [],
        ]);

        // Decodifica o JSON dos mods de atributo ajustados à mão ANTES de validar
        $request->merge([
            'mods_atributos' => json_decode($request->input('mods_atributos') ?: '{}', true) ?: [],
        ]);

        // Decodifica o JSON das propriedades fisiomantes salvas na ficha ANTES de validar
        $request->merge([
            'propriedades_fisiomantes' => json_decode($request->input('propriedades_fisiomantes') ?: '[]', true) ?: [],
        ]);

        // Campos que só são gravados se vieram no formulário (o merge abaixo cria a chave, então a presença é lida antes)
        $veio = [];
        foreach (['armaduras', 'artes_marciais', 'niveis_profissao', 'estudos_ama', 'habilidades_extras'] as $campo) {
            $veio[$campo] = $request->has($campo);
        }

        // Armaduras, resistências e imunidades (aba Armaduras dos atributos vitais). Só decodifica se veio no formulário,
        // para não apagar o que está salvo quando outra tela grava a ficha sem esse campo.
        $request->merge([
            'armaduras' => $request->has('armaduras') ? (json_decode($request->input('armaduras') ?: 'null', true) ?: null) : null,
            // Estudos na AMA (períodos, matérias e provas): o mesmo cuidado, só decodifica se veio
            'estudos_ama' => $request->has('estudos_ama') ? (json_decode($request->input('estudos_ama') ?: 'null', true) ?: null) : null,
            // Valores extras das habilidades ({chave: [{origem, valor}]}): o mesmo cuidado, só decodifica se veio
            'habilidades_extras' => $request->has('habilidades_extras') ? (json_decode($request->input('habilidades_extras') ?: 'null', true) ?: []) : null,
        ]);

        // Artes marciais (nível, experiência e talentos de cada arte). Mesmo cuidado das armaduras: só decodifica se veio no formulário.
        $request->merge([
            'artes_marciais' => $request->has('artes_marciais') ? (json_decode($request->input('artes_marciais') ?: 'null', true) ?: null) : null,
        ]);

        // Distribuição dos níveis mundanos entre as profissões (um item por nível). Mesmo cuidado das armaduras.
        $request->merge([
            'niveis_profissao' => $request->has('niveis_profissao') ? (json_decode($request->input('niveis_profissao') ?: 'null', true) ?: null) : null,
        ]);

        $dados = $request->validate([
            'nome' => 'required|string|max:255',
            'raca' => 'required|string',
            'maldicoes' => 'nullable|string',
            'idade' => 'required|integer',
            'profissao' => 'nullable|string',
            'niveis_profissao' => 'nullable|array|max:500',
            'niveis_profissao.*' => ['string', Rule::in(Personagem::PROFISSOES)],
            'nivel_mundano' => 'nullable|integer',
            'experiencia_mundana' => 'nullable|integer',
            'porte_fisico' => 'nullable|string',
            'be' => 'nullable|integer',
            'forca' => 'nullable|integer',
            'constituicao' => 'nullable|integer',
            'destreza' => 'nullable|integer',
            'conhecimento' => 'nullable|integer',
            'carisma' => 'nullable|integer',
            'percepcao' => 'nullable|integer',
            'alma' => 'nullable|integer',
            'sorte' => 'nullable|integer',

            // Pontos extras (ganhos de fontes externas) para atributos e habilidades
            'pontos_extras_atributos' => 'nullable|integer|min:0|max:65535',
            'pontos_extras_habilidades' => 'nullable|integer|min:0|max:65535',
            // Ajuste manual dos pontos de habilidade (modo "ficha já existente"); pode ser negativo
            'pontos_habilidade_ajuste' => 'nullable|integer|min:-65535|max:65535',
            // Mods de atributo ajustados à mão (modo "ficha já existente"): {"forca": 10, ...}
            'mods_atributos' => 'nullable|array',
            'mods_atributos.*' => 'nullable|integer|min:-99|max:999',
            'descricao' => 'nullable|string',
            'vida_atual' => 'nullable|integer',
            'vida_maxima' => 'nullable|integer',
            'sanidade_atual' => 'nullable|integer',
            'sanidade_maxima' => 'nullable|integer',
            'aura_atual' => 'nullable|integer',
            'aura_maxima' => 'nullable|integer',

            // Configuração do card de Maldições
            'nivel_hexomante' => 'nullable|integer|min:0|max:65535',
            'ameaca' => 'nullable|integer|min:0|max:65535',

            // Pastas de organização
            'pastas' => 'nullable|array',
            'pastas.talentos' => 'nullable|array|max:50',
            'pastas.talentos.*' => 'string|max:60',
            'pastas.tecnicas' => 'nullable|array|max:50',
            'pastas.tecnicas.*' => 'string|max:60',
            'pastas.anotacoes' => 'nullable|array|max:50',
            'pastas.anotacoes.*' => 'string|max:60',
            'pastas.talentos_hexomancia' => 'nullable|array|max:50',
            'pastas.talentos_hexomancia.*' => 'string|max:60',
            'pastas.talentos_fisiomancia' => 'nullable|array|max:50',
            'pastas.talentos_fisiomancia.*' => 'string|max:60',

            // Itens e ações (nome, categoria, dano e textos de cada item criado)
            'itens' => 'nullable|array|max:200',
            'itens.*.nome' => 'required|string|max:255',
            'itens.*.categoria' => 'nullable|string|max:100',
            'itens.*.qtd_dados' => 'nullable|integer|min:0',
            'itens.*.lados_dado' => 'nullable|integer|min:0',
            'itens.*.valor_fixo' => 'nullable|integer',
            'itens.*.descricao' => 'nullable|string|max:5000',
            'itens.*.modificacoes' => 'nullable|string|max:5000',

            // Talentos (mundanos, de Hexomancia e de Fisiomancia: mesma estrutura, campo "tipo" separa as abas)
            'talentos' => 'nullable|array|max:300',
            'talentos.*.tipo' => 'nullable|string|in:mundano,hexomancia,fisiomancia',
            'talentos.*.titulo' => 'required|string|max:150',
            'talentos.*.descricao' => 'nullable|string|max:5000',
            'talentos.*.pasta' => 'nullable|string|max:60',
            'talentos.*.custo' => 'nullable|integer|min:0',
            'talentos.*.ativo' => 'nullable|boolean',
            // Técnicas de arte (talentos de tipo hexomancia/fisiomancia): eficiência abate só o custo base
            'talentos.*.eficiencia' => 'nullable|integer|min:0',
            'talentos.*.nivel_req' => 'nullable|integer|min:0|max:99', // nível de maldição requisitado (abaixo: +5 de Aura por nível)
            'talentos.*.aprimoramentos' => 'nullable|array|max:50',
            'talentos.*.aprimoramentos.*.texto' => 'nullable|string|max:1000',
            'talentos.*.aprimoramentos.*.custo' => 'nullable|integer|min:0',
            'talentos.*.aprimoramentos.*.ativo' => 'nullable|boolean',
            // aprimoramentos acumulativos (qtd de aplicações) e aplicações grátis, que não somam ao custo
            'talentos.*.aprimoramentos.*.acumulativo' => 'nullable|boolean',
            'talentos.*.aprimoramentos.*.qtd' => 'nullable|integer|min:0|max:99',
            'talentos.*.aprimoramentos.*.gratis' => 'nullable|integer|min:0|max:99',
            // aprimoramentos gerais (dano, acao, alvos, alcance): {chave: {qtd, gratis}}
            'talentos.*.gerais' => 'nullable|array|max:10',
            'talentos.*.gerais.*.qtd' => 'nullable|integer|min:0|max:99',
            'talentos.*.gerais.*.gratis' => 'nullable|integer|min:0|max:99',
            'talentos.*.gratis_disponiveis' => 'nullable|integer|min:0|max:99',

            // Vitais extras
            'vitais_extras' => 'nullable|array',
            'vitais_extras.*.nome' => 'required|string|max:60',
            'vitais_extras.*.cor' => 'nullable|string|max:7',
            'vitais_extras.*.atual' => 'nullable|integer',
            'vitais_extras.*.max' => 'nullable|integer|min:0',

            // Anotações (cada uma é um "arquivo de texto" com título e conteúdo)
            'anotacoes' => 'nullable|array|max:100',
            'anotacoes.*.titulo' => 'required|string|max:100',
            'anotacoes.*.pasta' => 'nullable|string|max:60',
            'anotacoes.*.texto' => 'nullable|string|max:20000',

            // Técnicas (com aprimoramentos e o estado "ativo" de cada um)
            'tecnicas' => 'nullable|array|max:200',
            'tecnicas.*.tipo' => 'nullable|string|in:hexomancia,fisiomancia',
            'tecnicas.*.nome' => 'required|string|max:150',
            'tecnicas.*.pasta' => 'nullable|string|max:60',
            'tecnicas.*.descricao' => 'nullable|string|max:5000',
            'tecnicas.*.logica' => 'nullable|string|max:5000',
            'tecnicas.*.nivel' => 'nullable|integer|min:0',
            'tecnicas.*.custo' => 'nullable|integer|min:0',
            'tecnicas.*.eficiencia' => 'nullable|integer|min:0',
            'tecnicas.*.processo' => 'nullable|string|max:5000',
            'tecnicas.*.efeito' => 'nullable|string|max:5000',
            'tecnicas.*.aprimoramentos' => 'nullable|array|max:50',
            'tecnicas.*.aprimoramentos.*.texto' => 'nullable|string|max:1000',
            'tecnicas.*.aprimoramentos.*.custo' => 'nullable|integer|min:0',
            'tecnicas.*.aprimoramentos.*.ativo' => 'nullable|boolean',
            // aprimoramentos acumulativos (qtd de aplicações) e aplicações grátis, que não somam ao custo
            'tecnicas.*.aprimoramentos.*.acumulativo' => 'nullable|boolean',
            'tecnicas.*.aprimoramentos.*.qtd' => 'nullable|integer|min:0|max:99',
            'tecnicas.*.aprimoramentos.*.gratis' => 'nullable|integer|min:0|max:99',
            // aprimoramentos gerais (dano, acao, alvos, alcance): {chave: {qtd, gratis}}
            'tecnicas.*.gerais' => 'nullable|array|max:10',
            'tecnicas.*.gerais.*.qtd' => 'nullable|integer|min:0|max:99',
            'tecnicas.*.gerais.*.gratis' => 'nullable|integer|min:0|max:99',
            'tecnicas.*.gratis_disponiveis' => 'nullable|integer|min:0|max:99',

            // Propriedades fisiomantes salvas na ficha (do catálogo ou personalizadas)
            'propriedades_fisiomantes' => 'nullable|array|max:300',
            'estudos_ama' => 'nullable|array',
            'habilidades_extras' => 'nullable|array|max:60',
            'habilidades_extras.*' => 'array|max:30',
            'habilidades_extras.*.*.origem' => 'nullable|string|max:80',
            'habilidades_extras.*.*.valor' => 'nullable|integer|min:-99999|max:99999',
            'estudos_ama.periodos' => 'nullable|array|max:20',
            'estudos_ama.periodos.*.aprovado' => 'nullable|boolean',
            'estudos_ama.periodos.*.talento' => 'nullable|string|max:80',
            'estudos_ama.periodos.*.materias' => 'nullable|array|max:5',
            'estudos_ama.periodos.*.materias.*.id' => 'required|integer|min:1|max:99',
            'estudos_ama.periodos.*.materias.*.aulas' => 'nullable|integer|min:0|max:99',
            'estudos_ama.periodos.*.materias.*.esc' => 'nullable|array|max:5',
            'estudos_ama.periodos.*.materias.*.esc.*' => 'nullable|string|max:30',
            'armaduras' => 'nullable|array',
            'armaduras.armadura_natural' => 'nullable|integer|min:-999|max:9999',
            'armaduras.resistencias' => 'nullable|array|max:20',
            'armaduras.resistencias.*' => 'string|max:30',
            'armaduras.imunidades' => 'nullable|array|max:20',
            'armaduras.imunidades.*' => 'string|max:30',
            'armaduras.fraquezas' => 'nullable|array|max:20',
            'armaduras.fraquezas.*' => 'string|max:30',
            'armaduras.condicoes' => 'nullable|array|max:30',
            'armaduras.condicoes.*' => 'string|max:30',
            'armaduras.condicoes_especiais' => 'nullable|string|max:1000',
            'armaduras.pecas' => 'nullable|array|max:30',
            'armaduras.pecas.*.nome' => 'required|string|max:100',
            'armaduras.pecas.*.descricao' => 'nullable|string|max:1000',
            'armaduras.pecas.*.tamanho' => 'nullable|string|max:40',
            'armaduras.pecas.*.alcance_min' => 'nullable|integer|min:0|max:1000',
            'armaduras.pecas.*.alcance_max' => 'nullable|integer|min:0|max:1000',
            'armaduras.pecas.*.defesa' => 'nullable|integer|min:-999|max:9999',
            'armaduras.pecas.*.contra_corte_impacto' => 'nullable|integer|min:-999|max:9999',
            'armaduras.pecas.*.defesa_base' => 'nullable|integer|min:-999|max:9999',
            'armaduras.pecas.*.penalidade_destreza' => 'nullable|integer|min:-999|max:999',
            'armaduras.pecas.*.peso' => 'nullable|numeric|min:0|max:9999',
            'armaduras.pecas.*.equipada' => 'nullable|boolean',
            'artes_marciais' => 'nullable|array',
            'artes_marciais.*' => 'array',
            'artes_marciais.*.nivel' => 'nullable|integer|min:0|max:999',
            'artes_marciais.*.xp' => 'nullable|integer|min:0|max:9999999',
            'artes_marciais.*.talentos' => 'nullable|array|max:60',
            'artes_marciais.*.talentos.*.nome' => 'required|string|max:120',
            'artes_marciais.*.talentos.*.custo' => 'nullable|integer|min:0|max:99999',
            'artes_marciais.*.talentos.*.descricao' => 'nullable|string|max:5000',
            'artes_marciais.*.talentos.*.ativo' => 'nullable|boolean',
            'propriedades_fisiomantes.*.nome' => 'required|string|max:120',
            'propriedades_fisiomantes.*.fonte' => 'nullable|string|max:120',
            'propriedades_fisiomantes.*.ameaca' => 'nullable|integer|min:-999|max:9999',
            'propriedades_fisiomantes.*.descricao' => 'nullable|string|max:5000',
            'propriedades_fisiomantes.*.anotacao' => 'nullable|string|max:2000',
        ]);

        $nomesHabilidades = [
            'luta', 'resiliencia', 'acrobacia', 'atletismo', 'agarrar', 'arremesso',
            'desviar', 'contra_ataque', 'bloquear', 'iniciativa', 'furtividade',
            'enxergar', 'olfato', 'audicao', 'maldicao_hab', 'precisao', 'investigacao',
            'sobrevivencia', 'construcao', 'procura', 'intuicao', 'tecnologia', 'religiao',
            'raciocinio', 'medicina', 'primeiros_socorros', 'culinaria', 'dirigir',
            'explosivos', 'fotografia', 'labia', 'barganhar', 'manipulacao', 'intimidar',
            'arte', 'disfarce', 'performance', 'detectar_mentira',
        ];

        $habilidades = [];
        foreach ($nomesHabilidades as $habilidade) {
            $habilidades[$habilidade] = $request->input($habilidade, 0);
        }

        // No banco, nivel_hexomante e ameaca são NOT NULL (padrão 0). Campo vazio chega
        // como null depois da validação, então convertemos para 0 antes de gravar.
        $dados['nivel_hexomante'] = (int) ($dados['nivel_hexomante'] ?? 0);
        $dados['ameaca'] = (int) ($dados['ameaca'] ?? 0);

        // Pontos extras: campo vazio vira 0 (colunas NOT NULL, padrão 0)
        $dados['pontos_extras_atributos'] = (int) ($dados['pontos_extras_atributos'] ?? 0);
        $dados['pontos_extras_habilidades'] = (int) ($dados['pontos_extras_habilidades'] ?? 0);

        // Ajuste manual dos pontos de habilidade: só grava se a migration que cria a coluna já rodou
        if (Esquema::coluna('fichas', 'pontos_habilidade_ajuste')) {
            $dados['pontos_habilidade_ajuste'] = (int) ($dados['pontos_habilidade_ajuste'] ?? 0);
        } else {
            unset($dados['pontos_habilidade_ajuste']);
        }

        // Propriedades fisiomantes: só grava se a migration que cria a coluna já rodou
        // Níveis por profissão: só grava se a coluna existir e se o formulário enviou o campo
        if (!Esquema::coluna('fichas', 'niveis_profissao') || !$veio['niveis_profissao']) {
            unset($dados['niveis_profissao']);
        } elseif (isset($dados['niveis_profissao'])) {
            $dados['niveis_profissao'] = array_values($dados['niveis_profissao']);
        }

        // Valores extras das habilidades: só grava se a coluna existir (SQL da migração 2026_11_03_000024) e se veio no
        // formulário; ficam só habilidades conhecidas e, em cada uma, a origem (texto) e o valor (inteiro, sem limite de pontos)
        if (!Esquema::coluna('fichas', 'habilidades_extras') || !$veio['habilidades_extras']) {
            unset($dados['habilidades_extras']);
        } else {
            $limpos = [];
            foreach ((array) ($dados['habilidades_extras'] ?? []) as $chave => $lista) {
                if (!in_array($chave, $nomesHabilidades, true) || !is_array($lista)) {
                    continue;
                }
                $itens = [];
                foreach ($lista as $e) {
                    $origem = trim((string) ($e['origem'] ?? ''));
                    $valor = (int) ($e['valor'] ?? 0);
                    if ($origem === '' && $valor === 0) {
                        continue;
                    }
                    $itens[] = ['origem' => mb_substr($origem, 0, 80), 'valor' => $valor];
                }
                if ($itens) {
                    $limpos[$chave] = $itens;
                }
            }
            $dados['habilidades_extras'] = $limpos;
        }

        // Estudos na AMA: só grava se a coluna existir (rode o SQL da migração 2026_11_02_000020) e se veio no formulário
        if (!Esquema::coluna('fichas', 'estudos_ama') || !$veio['estudos_ama']) {
            unset($dados['estudos_ama']);
        }

        // Armaduras: só grava se a coluna existir e se o formulário enviou o campo
        if (!Esquema::coluna('fichas', 'armaduras') || !$veio['armaduras']) {
            unset($dados['armaduras']);
        }

        // Artes marciais: só as artes conhecidas e só se a coluna existir e o formulário enviou o campo
        if (!Esquema::coluna('fichas', 'artes_marciais') || !$veio['artes_marciais']) {
            unset($dados['artes_marciais']);
        } else {
            $artes = [];
            foreach (array_keys(Personagem::ARTES_MARCIAIS) as $chave) {
                $a = $dados['artes_marciais'][$chave] ?? [];
                $artes[$chave] = [
                    'nivel' => (int) ($a['nivel'] ?? 0),
                    'xp' => (int) ($a['xp'] ?? 0),
                    'talentos' => array_values(array_map(fn ($t) => [
                        'nome' => trim((string) $t['nome']),
                        'custo' => (int) ($t['custo'] ?? 0),
                        'descricao' => (string) ($t['descricao'] ?? ''),
                        'ativo' => !empty($t['ativo']),
                    ], $a['talentos'] ?? [])),
                ];
            }
            $dados['artes_marciais'] = $artes;
        }

        if (!Esquema::coluna('fichas', 'propriedades_fisiomantes')) {
            unset($dados['propriedades_fisiomantes']);
        }

        // Mods de atributo ajustados à mão: só os atributos conhecidos, só se a coluna existir
        if (Esquema::coluna('fichas', 'mods_atributos')) {
            $mods = [];
            foreach (Personagem::ATRIBUTOS as $atributo) {
                if (isset($dados['mods_atributos'][$atributo]) && $dados['mods_atributos'][$atributo] !== '') {
                    $mods[$atributo] = (int) $dados['mods_atributos'][$atributo];
                }
            }
            $dados['mods_atributos'] = $mods;
        } else {
            unset($dados['mods_atributos']);
        }

        // Normaliza as pastas: só nomes não vazios e sem repetição
        $pastas = [];
        foreach (['talentos', 'tecnicas', 'anotacoes', 'talentos_hexomancia', 'talentos_fisiomancia'] as $tipo) {
            $nomes = array_map('trim', $dados['pastas'][$tipo] ?? []);
            $pastas[$tipo] = array_values(array_unique(array_filter($nomes, fn ($n) => $n !== '')));
        }
        $dados['pastas'] = $pastas;

        $dados['habilidades'] = $habilidades;
        $dados['usuario_id'] = auth()->id() ?? null;

        return $dados;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $personagens = Personagem::orderBy('created_at', 'desc')->get();
        return view('personagem.lista', compact('personagens'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $personagem = Personagem::findOrFail($id);
        return view('personagem.form', compact('personagem'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $personagem = Personagem::findOrFail($id);
        $personagem->delete();

        return redirect()->route('personagens.index')->with('sucesso', 'Ficha excluida com sucesso!');
    }
}
