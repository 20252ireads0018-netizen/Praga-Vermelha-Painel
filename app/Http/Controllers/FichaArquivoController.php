<?php

namespace App\Http\Controllers;

use App\Support\Plano;
use App\Models\Personagem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Baixar e importar fichas como arquivo .json.
 * Usa as mesmas regras de validação do PersonagemController@validarFicha,
 * então uma ficha importada passa pelo mesmo crivo de uma ficha criada no formulário.
 */
class FichaArquivoController extends Controller
{
    /** Marca que identifica um arquivo como ficha de agente. */
    private const FORMATO = 'ficha-agente';
    private const VERSAO = 1;

    /** Colunas que guardam listas/objetos (podem estar como texto JSON no banco). */
    private const CAMPOS_JSON = ['habilidades', 'itens', 'vitais_extras', 'anotacoes'];

    /** Nunca exportadas nem importadas (o dono é sempre quem importa). */
    private const IGNORAR = ['id', 'usuario_id', 'user_id', 'created_at', 'updated_at', 'deleted_at'];

    /** Mesma lista de habilidades do PersonagemController. */
    private const HABILIDADES = [
        'luta', 'resiliencia', 'acrobacia', 'atletismo', 'agarrar', 'arremesso',
        'desviar', 'contra_ataque', 'bloquear', 'iniciativa', 'furtividade',
        'enxergar', 'olfato', 'audicao', 'maldicao_hab', 'precisao', 'investigacao',
        'sobrevivencia', 'construcao', 'procura', 'intuicao', 'tecnologia', 'religiao',
        'raciocinio', 'medicina', 'primeiros_socorros', 'culinaria', 'dirigir',
        'explosivos', 'fotografia', 'labia', 'barganhar', 'manipulacao', 'intimidar',
        'arte', 'disfarce', 'performance', 'detectar_mentira',
    ];

    private const TAMANHO_MAX_KB = 1024;
    private const ARQUIVOS_MAX = 20;

    /**
     * Baixa a ficha como arquivo .json.
     */
    public function exportar(string $id)
    {
        $personagem = Personagem::findOrFail($id);
        $this->autorizar($personagem);

        $dados = $personagem->attributesToArray();

        foreach (self::IGNORAR as $coluna) {
            unset($dados[$coluna]);
        }

        // Garante que listas saiam como JSON de verdade, mesmo sem cast no model.
        foreach (self::CAMPOS_JSON as $coluna) {
            if (isset($dados[$coluna]) && is_string($dados[$coluna])) {
                $decodificado = json_decode($dados[$coluna], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $dados[$coluna] = $decodificado;
                }
            }
        }

        $conteudo = [
            'formato'      => self::FORMATO,
            'versao'       => self::VERSAO,
            'exportado_em' => now()->toIso8601String(),
            'personagem'   => $dados,
        ];

        $slug = Str::slug((string) $personagem->nome) ?: 'ficha';
        $json = json_encode($conteudo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, "ficha-{$slug}.json", ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    /**
     * Recebe um ou mais arquivos; cada um que for uma ficha válida vira um personagem novo.
     */
    public function importar(Request $request)
    {
        $arquivos = $request->file('arquivos', []);
        if (!is_array($arquivos)) {
            $arquivos = [$arquivos];
        }
        $arquivos = array_values(array_filter($arquivos));

        if (empty($arquivos)) {
            return redirect()->route('personagens.index')
                ->with('erros_importacao', ['Nenhum arquivo foi enviado.']);
        }

        if (count($arquivos) > self::ARQUIVOS_MAX) {
            return redirect()->route('personagens.index')
                ->with('erros_importacao', ['Envie no máximo ' . self::ARQUIVOS_MAX . ' arquivos por vez.']);
        }

        $importadas = [];
        $erros = [];
        $restantes = Plano::fichasRestantes($request->user()); // null = sem limite

        foreach ($arquivos as $arquivo) {
            $rotulo = $arquivo->getClientOriginalName();

            if ($restantes !== null && count($importadas) >= $restantes) {
                $erros[] = "{$rotulo}: " . Plano::mensagemLimite('fichas');
                continue;
            }

            if (!$arquivo->isValid()) {
                $erros[] = "{$rotulo}: falha no envio do arquivo.";
                continue;
            }
            if ($arquivo->getSize() > self::TAMANHO_MAX_KB * 1024) {
                $erros[] = "{$rotulo}: arquivo grande demais (máximo " . self::TAMANHO_MAX_KB . " KB).";
                continue;
            }

            $texto = @file_get_contents($arquivo->getRealPath());
            if ($texto === false) {
                $erros[] = "{$rotulo}: não foi possível ler o arquivo.";
                continue;
            }
            // remove BOM, se houver
            $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);

            $json = json_decode($texto, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
                $erros[] = "{$rotulo}: não é um arquivo de ficha válido.";
                continue;
            }

            if (($json['formato'] ?? null) !== self::FORMATO || !isset($json['personagem']) || !is_array($json['personagem'])) {
                $erros[] = "{$rotulo}: este arquivo não é uma ficha de agente.";
                continue;
            }

            $validador = Validator::make($json['personagem'], $this->regras());
            if ($validador->fails()) {
                $erros[] = "{$rotulo}: " . $validador->errors()->first();
                continue;
            }

            try {
                $personagem = $this->criarPersonagem($validador->validated(), $json['personagem']);
                $importadas[] = $personagem->nome;
            } catch (\Throwable $e) {
                report($e);
                $erros[] = "{$rotulo}: não foi possível criar a ficha (dados incompatíveis).";
            }
        }

        $resposta = redirect()->route('personagens.index');
        if (!empty($importadas)) {
            $resposta->with('fichas_importadas', $importadas);
        }
        if (!empty($erros)) {
            $resposta->with('erros_importacao', $erros);
        }

        return $resposta;
    }

    /**
     * Mesmas regras do PersonagemController@validarFicha (mais os itens, usados na ficha de leitura).
     */
    private function regras(): array
    {
        return [
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
            'descricao' => 'nullable|string',
            'vida_atual' => 'nullable|integer',
            'vida_maxima' => 'nullable|integer',
            'sanidade_atual' => 'nullable|integer',
            'sanidade_maxima' => 'nullable|integer',
            'aura_atual' => 'nullable|integer',
            'aura_maxima' => 'nullable|integer',

            'habilidades' => 'nullable|array',

            'vitais_extras' => 'nullable|array',
            'vitais_extras.*.nome' => 'required|string|max:60',
            'vitais_extras.*.cor' => 'nullable|string|max:7',
            'vitais_extras.*.atual' => 'nullable|integer',
            'vitais_extras.*.max' => 'nullable|integer|min:0',

            'anotacoes' => 'nullable|array|max:100',
            'anotacoes.*.titulo' => 'required|string|max:100',
            'anotacoes.*.texto' => 'nullable|string|max:20000',

            'itens' => 'nullable|array|max:500',
            'itens.*.nome' => 'nullable|string|max:255',
            'itens.*.categoria' => 'nullable|string|max:100',
            'itens.*.descricao' => 'nullable|string|max:20000',
            'itens.*.modificacoes' => 'nullable|string|max:20000',
        ];
    }

    /**
     * Cria o personagem como o formulário faz: Personagem::create() com o dono sendo quem importa.
     */
    private function criarPersonagem(array $dados, array $original): Personagem
    {
        $modelo = new Personagem();
        $tabela = $modelo->getTable();

        // Habilidades: só as conhecidas, todas presentes (como no formulário).
        $habilidadesOriginais = is_array($original['habilidades'] ?? null) ? $original['habilidades'] : [];
        $habilidades = [];
        foreach (self::HABILIDADES as $habilidade) {
            $habilidades[$habilidade] = (int) ($habilidadesOriginais[$habilidade] ?? 0);
        }
        $dados['habilidades'] = $habilidades;

        // Colunas que podem não existir na tabela são descartadas.
        foreach (['itens', 'anotacoes', 'vitais_extras', 'niveis_profissao'] as $coluna) {
            if (array_key_exists($coluna, $dados) && !Schema::hasColumn($tabela, $coluna)) {
                unset($dados[$coluna]);
            }
        }

        // Sem cast no model, listas precisam ir como texto JSON.
        foreach (self::CAMPOS_JSON as $coluna) {
            if (isset($dados[$coluna]) && is_array($dados[$coluna]) && !$modelo->hasCast($coluna)) {
                $dados[$coluna] = json_encode($dados[$coluna], JSON_UNESCAPED_UNICODE);
            }
        }

        $dados['usuario_id'] = auth()->id();

        return Personagem::create($dados);
    }

    /**
     * Quem baixa a ficha é o dono. Fichas antigas, sem dono, qualquer logado baixa
     * (o PersonagemController atual também não restringe por dono).
     */
    private function autorizar(Personagem $personagem): void
    {
        if (
            auth()->check()
            && Schema::hasColumn($personagem->getTable(), 'usuario_id')
            && $personagem->usuario_id !== null
            && (int) $personagem->usuario_id !== (int) auth()->id()
        ) {
            abort(403);
        }
    }
}