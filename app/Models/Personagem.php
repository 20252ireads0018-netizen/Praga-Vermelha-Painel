<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\PertenceAoUsuario;

class Personagem extends Model
{
    use PertenceAoUsuario;

    protected $table = 'fichas';

    public const ATRIBUTOS = [
        'forca', 'constituicao', 'destreza', 'conhecimento', 'carisma', 'percepcao', 'alma',
    ];

    public const PROFISSOES = ['Criminoso', 'Lutador', 'Intelectual', 'Comunicador', 'Construtor'];

    public const PORTES = ['Comum', 'Magro', 'Musculoso', 'Gordo', 'Infantil', 'Idoso'];

    /** Artes marciais da ficha (chave => nome). A barra de experiência é calculada na tela (form.blade.php, ArtesMarciais). */
    public const ARTES_MARCIAIS = [
        'esgrima'      => 'Esgrima',
        'hexomancia'   => 'Hexomancia',
        'fisiomancia'  => 'Fisiomancia',
        'boxing'       => 'Boxing',
        'wrestling'    => 'Wrestling',
        'tiro_tatico'  => 'Tiro tático',
        'kuro_stomper' => 'Kuro Stomper',
    ];

    /**
     * Chaves das habilidades exatamente como o formulário envia (atributo "name" dos inputs)
     * e como a view lê em $personagem->habilidades['chave'].
     */
    public const HABILIDADES = [
        'fisicas' => [
            'luta', 'resiliencia', 'acrobacia', 'atletismo', 'agarrar', 'arremesso', 'desviar',
            'contra_ataque', 'bloquear', 'iniciativa', 'furtividade', 'enxergar', 'olfato', 'audicao',
        ],
        'intelectuais' => [
            'maldicao_hab', 'precisao', 'investigacao', 'sobrevivencia', 'construcao', 'procura',
            'intuicao', 'tecnologia', 'religiao', 'raciocinio', 'medicina', 'primeiros_socorros',
            'culinaria', 'dirigir', 'explosivos',
        ],
        'introspectivas' => [
            'fotografia', 'labia', 'barganhar', 'manipulacao', 'intimidar', 'arte', 'disfarce',
            'performance', 'detectar_mentira',
        ],
    ];

    protected $fillable = [
        'usuario_id',

        // informações pessoais
        'nome', 'raca', 'maldicoes', 'idade', 'descricao',

        // informações técnicas
        'profissao', 'niveis_profissao', 'nivel_mundano', 'experiencia_mundana',
        'porte_fisico', 'be',

        // atributos
        'forca', 'constituicao', 'destreza', 'conhecimento', 'carisma', 'percepcao', 'alma', 'sorte',
        'pontos_extras_atributos', 'pontos_extras_habilidades',
        // ajuste manual dos pontos de habilidade e mods de atributo (modo "ficha já existente")
        'pontos_habilidade_ajuste', 'mods_atributos',

        // habilidades, itens e talentos
        'habilidades', 'itens', 'talentos', 'tecnicas', 'propriedades_fisiomantes',

        // maldições (configuração do card de Maldições)
        'nivel_hexomante', 'ameaca',

        // atributos vitais
        'vida_atual', 'vida_maxima', 'sanidade_atual', 'sanidade_maxima', 'aura_atual', 'aura_maxima',
        'vitais_extras', 'armaduras', 'artes_marciais',

        'anotacoes',

        // pastas de organização (talentos, técnicas e anotações)
        'pastas',
    ];

    protected $casts = [
        'habilidades'      => 'array',
        'itens'            => 'array',
        'talentos'         => 'array',
        'tecnicas'         => 'array',
        'propriedades_fisiomantes' => 'array',
        'niveis_profissao' => 'array',
        'vitais_extras'    => 'array',
        'armaduras'        => 'array',
        'artes_marciais'   => 'array',
        'anotacoes'        => 'array',
        'pastas'           => 'array',
        'mods_atributos'   => 'array',

        'idade'                     => 'integer',
        'nivel_mundano'             => 'integer',
        'experiencia_mundana'       => 'integer',
        'be'                        => 'integer',
        'sorte'                     => 'integer',
        'pontos_extras_atributos'   => 'integer',
        'pontos_extras_habilidades' => 'integer',
        'pontos_habilidade_ajuste'  => 'integer',
        'nivel_hexomante'           => 'integer',
        'ameaca'                    => 'integer',
    ];

    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_id');
    }

    /** Lista plana com todas as chaves de habilidade. */
    public static function chavesHabilidades(): array
    {
        return array_merge(...array_values(self::HABILIDADES));
    }

    /**
     * Modificador de um atributo (mesma regra do formulário): o primeiro dígito do valor
     * (80 => 8), exceto de 1 a 9, em que o modificador é sempre 1. Zero ou vazio => 0.
     */
    public static function modDe(int $valor): int
    {
        if ($valor >= 10) {
            return intdiv($valor, 10);
        }

        return $valor >= 1 ? 1 : 0;
    }

    public function modificador(string $atributo): int
    {
        return self::modDe((int) ($this->{$atributo} ?? 0));
    }

    /** Rótulos das habilidades (chave salva => nome), iguais ao objeto JS nomesHabilidades do formulário. */
    public const NOMES_HABILIDADES = [
        'luta' => 'Luta', 'resiliencia' => 'Resiliência', 'acrobacia' => 'Acrobacia', 'atletismo' => 'Atletismo',
        'agarrar' => 'Agarrar', 'arremesso' => 'Arremesso', 'desviar' => 'Desviar', 'contra_ataque' => 'Contra ataque',
        'bloquear' => 'Bloquear', 'iniciativa' => 'Iniciativa', 'furtividade' => 'Furtividade', 'enxergar' => 'Enxergar',
        'olfato' => 'Olfato', 'audicao' => 'Audição',
        'maldicao_hab' => 'Maldição', 'precisao' => 'Precisão', 'investigacao' => 'Investigação',
        'sobrevivencia' => 'Sobrevivência', 'construcao' => 'Construção', 'procura' => 'Procura', 'intuicao' => 'Intuição',
        'tecnologia' => 'Tecnologia', 'religiao' => 'Religião', 'raciocinio' => 'Raciocínio', 'medicina' => 'Medicina',
        'primeiros_socorros' => 'Primeiros socorros', 'culinaria' => 'Culinária', 'dirigir' => 'Dirigir',
        'explosivos' => 'Explosivos',
        'fotografia' => 'Fotografia', 'labia' => 'Lábia', 'barganhar' => 'Barganhar', 'manipulacao' => 'Manipulação',
        'intimidar' => 'Intimidar', 'arte' => 'Arte', 'disfarce' => 'Disfarce', 'performance' => 'Performance',
        'detectar_mentira' => 'Detectar mentira',
    ];

    /** Vínculo habilidade => atributos (usa o maior mod), igual a vinculoHabilidadeAtributo do formulário. */
    public const VINCULO_HABILIDADE_ATRIBUTO = [
        'luta' => ['forca', 'destreza'], 'acrobacia' => ['destreza'], 'atletismo' => ['constituicao'],
        'resiliencia' => ['constituicao'], 'agarrar' => ['forca'], 'arremesso' => ['forca'], 'desviar' => ['destreza'],
        'contra_ataque' => ['forca', 'destreza'], 'bloquear' => ['forca', 'destreza'], 'iniciativa' => ['destreza'],
        'furtividade' => ['destreza', 'conhecimento'], 'enxergar' => ['percepcao'], 'olfato' => ['percepcao'],
        'audicao' => ['percepcao'],
        'maldicao_hab' => ['alma'], 'precisao' => ['destreza', 'percepcao'], 'investigacao' => ['conhecimento', 'percepcao'],
        'sobrevivencia' => ['conhecimento'], 'construcao' => ['conhecimento'], 'procura' => ['percepcao'],
        'intuicao' => ['conhecimento'], 'tecnologia' => ['conhecimento'], 'religiao' => ['conhecimento'],
        'raciocinio' => ['conhecimento'], 'medicina' => ['conhecimento'], 'primeiros_socorros' => ['destreza'],
        'culinaria' => ['conhecimento'], 'dirigir' => ['conhecimento'], 'explosivos' => ['conhecimento'],
        'fotografia' => ['carisma'], 'labia' => ['carisma'], 'barganhar' => ['carisma'], 'manipulacao' => ['carisma'],
        'intimidar' => ['carisma'], 'arte' => ['carisma'], 'disfarce' => ['carisma'], 'performance' => ['carisma'],
        'detectar_mentira' => ['carisma', 'conhecimento'],
    ];

    /** Mod do atributo: o manual (mods_atributos) tem prioridade sobre o calculado. */
    public function modAtributoEfetivo(string $atributo): int
    {
        $manuais = $this->mods_atributos;
        if (is_string($manuais)) {
            $manuais = json_decode($manuais, true);
        }
        if (is_array($manuais) && isset($manuais[$atributo]) && $manuais[$atributo] !== '' && is_numeric($manuais[$atributo])) {
            return (int) $manuais[$atributo];
        }

        return $this->modificador($atributo);
    }

    /**
     * Habilidades prontas para rolagem, na ordem da ficha.
     * @return array<int, array{chave:string,nome:string,valor:int,mod:int,total:int}>
     */
    public function habilidadesParaRolagem(): array
    {
        $salvas = is_array($this->habilidades) ? $this->habilidades : [];
        $multiplicador = max(1, (int) ceil(((int) ($this->nivel_mundano ?? 0)) / 5));

        $lista = [];
        foreach (self::chavesHabilidades() as $chave) {
            $valor = (int) ($salvas[$chave] ?? 0);
            $atributos = self::VINCULO_HABILIDADE_ATRIBUTO[$chave] ?? [];
            $mod = $atributos
                ? max(array_map(fn ($a) => $this->modAtributoEfetivo($a), $atributos)) * $multiplicador
                : 0;

            $lista[] = [
                'chave' => $chave,
                'nome'  => self::NOMES_HABILIDADES[$chave] ?? $chave,
                'valor' => $valor,
                'mod'   => $mod,
                'total' => $valor + $mod,
            ];
        }

        return $lista;
    }

    public function pontosAtributosGastos(): int
    {
        return (int) array_sum(array_map(fn ($a) => (int) ($this->{$a} ?? 0), self::ATRIBUTOS));
    }

    public function pontosHabilidadesGastos(): int
    {
        return (int) array_sum(array_map('intval', $this->habilidades ?? []));
    }
}
