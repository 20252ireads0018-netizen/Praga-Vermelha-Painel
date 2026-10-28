<?php

namespace App\Http\Controllers;

use App\Models\Campanha;
use App\Models\CampanhaFicha;
use App\Models\CampanhaJogador;
use App\Models\CampanhaSom;
use App\Models\Mesa;
use App\Models\Personagem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CampanhaController extends Controller
{
    private const MAX_SONS = 40;

    // ------------------------------------------------------------------
    // Listagem / CRUD da campanha
    // ------------------------------------------------------------------

    public function index()
    {
        $user = auth()->user();
        $this->garantirCodigo($user);

        $minhas = Campanha::where('mestre_id', $user->id)
            ->withCount([
                'participantes as jogadores_count' => fn ($q) => $q->where('status', 'ativo'),
            ])
            ->latest()->get();

        $participo = Campanha::with('mestre')
            ->whereHas('participantes', fn ($q) => $q->where('user_id', $user->id)->where('status', 'ativo'))
            ->latest()->get();

        $convites = Campanha::with('mestre')
            ->whereHas('participantes', fn ($q) => $q->where('user_id', $user->id)->where('status', 'convidado'))
            ->latest()->get();

        return view('campanhas.index', compact('minhas', 'participo', 'convites', 'user'));
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome'      => 'required|string|max:80',
            'descricao' => 'nullable|string|max:2000',
        ]);

        $campanha = Campanha::create($dados + ['mestre_id' => auth()->id()]);

        return redirect()->route('campanhas.show', $campanha)->with('ok', 'Campanha criada.');
    }

    public function show(Campanha $campanha)
    {
        $user = auth()->user();
        abort_unless($campanha->temMembro($user->id), 403, 'Você não participa desta campanha.');

        $souMestre = $campanha->ehMestre($user->id);

        $campanha->load(['mestre', 'participantes.user']);

        // Mestre vê todas as fichas vinculadas; jogador vê apenas as próprias.
        $vinculos = $this->vinculosVisiveis($campanha, $user->id, $souMestre)->get();
        $this->carregarFichas($vinculos);

        // Para o seletor "vincular ficha" é preciso excluir também as fichas já vinculadas por mim,
        // mesmo quando o mestre ou outro jogador as vinculou (a lista acima já cobre o caso do jogador).
        $idsJaVinculados = CampanhaFicha::where('campanha_id', $campanha->id)->pluck('ficha_id');

        $mesas = Mesa::where('campanha_id', $campanha->id)->get();
        $sons  = CampanhaSom::where('campanha_id', $campanha->id)->orderBy('nome')->get();

        $minhasFichas = Personagem::where('usuario_id', $user->id)
            ->whereNotIn('id', $idsJaVinculados)
            ->get();

        $minhasMesas = $souMestre
            ? Mesa::where('user_id', $user->id)->whereNull('campanha_id')->get()
            : collect();

        // Fichas rolláveis (mestre: todas da campanha; jogador: as próprias) com habilidades já calculadas.
        $fichasRolagem = $vinculos
            ->filter(fn ($v) => $v->ficha !== null)
            ->map(fn ($v) => [
                'id'          => $v->ficha->id,
                'nome'        => trim((string) $v->ficha->nome) !== '' ? $v->ficha->nome : 'Ficha #' . $v->ficha->id,
                'habilidades' => $v->ficha->habilidadesParaRolagem(),
            ])
            ->unique('id')
            ->values()
            ->all();

        return view('campanhas.show', [
            'fichasRolagem' => $fichasRolagem,
            'campanha'     => $campanha,
            'souMestre'    => $souMestre,
            'vinculos'     => $vinculos,
            'mesas'        => $mesas,
            'sons'         => $sons,
            'minhasFichas' => $minhasFichas,
            'minhasMesas'  => $minhasMesas,
            'maldicoesDisputa' => CampanhaCatalogoController::disponiveis($user),
        ]);
    }

    public function update(Request $request, Campanha $campanha)
    {
        $this->exigirMestre($campanha);

        $dados = $request->validate([
            'nome'      => 'required|string|max:80',
            'descricao' => 'nullable|string|max:2000',
        ]);

        $campanha->update($dados);

        return back()->with('ok', 'Campanha atualizada.');
    }

    public function destroy(Campanha $campanha)
    {
        $this->exigirMestre($campanha);

        Mesa::where('campanha_id', $campanha->id)->update(['campanha_id' => null]);
        // apaga também as fotos das fichas (campanhas/{id}/fichas) e os sons
        Storage::disk('local')->deleteDirectory("campanhas/{$campanha->id}");
        $campanha->delete(); // jogadores, fichas e sons caem em cascata (FK)

        return redirect()->route('campanhas.index')->with('ok', 'Campanha excluída.');
    }

    // ------------------------------------------------------------------
    // Jogadores
    // ------------------------------------------------------------------

    public function convidar(Request $request, Campanha $campanha)
    {
        $this->exigirMestre($campanha);

        $request->validate(['jogador' => 'required|string|max:100']);
        $busca = trim($request->input('jogador'));

        $alvo = User::where('codigo_jogador', Str::upper($busca))->first()
            ?? User::where('name', $busca)->first();

        if (!$alvo) {
            return back()->withErrors(['jogador' => 'Nenhum jogador encontrado com esse código ou nome.'])->withInput();
        }
        if ((int) $alvo->id === (int) $campanha->mestre_id) {
            return back()->withErrors(['jogador' => 'Você já é o mestre desta campanha.'])->withInput();
        }

        $existente = CampanhaJogador::where('campanha_id', $campanha->id)->where('user_id', $alvo->id)->first();
        if ($existente) {
            $msg = $existente->status === 'ativo' ? 'Essa pessoa já participa da campanha.' : 'Essa pessoa já tem um convite pendente.';
            return back()->withErrors(['jogador' => $msg])->withInput();
        }

        CampanhaJogador::create([
            'campanha_id' => $campanha->id,
            'user_id'     => $alvo->id,
            'status'      => 'convidado',
        ]);

        return back()->with('ok', "Convite enviado para {$alvo->name}.");
    }

    public function aceitar(Campanha $campanha)
    {
        $convite = CampanhaJogador::where('campanha_id', $campanha->id)
            ->where('user_id', auth()->id())->where('status', 'convidado')->firstOrFail();

        $convite->update(['status' => 'ativo']);

        return redirect()->route('campanhas.show', $campanha)->with('ok', 'Você entrou na campanha.');
    }

    public function recusar(Campanha $campanha)
    {
        CampanhaJogador::where('campanha_id', $campanha->id)
            ->where('user_id', auth()->id())->where('status', 'convidado')->delete();

        return redirect()->route('campanhas.index')->with('ok', 'Convite recusado.');
    }

    /** Mestre remove um jogador (ou cancela um convite). */
    public function removerJogador(Campanha $campanha, User $user)
    {
        $this->exigirMestre($campanha);
        $this->tirarDaCampanha($campanha, $user->id);

        return back()->with('ok', 'Jogador removido da campanha.');
    }

    /** Jogador sai da campanha por conta própria. */
    public function sair(Campanha $campanha)
    {
        abort_if($campanha->ehMestre(auth()->id()), 422, 'O mestre não pode sair da própria campanha.');
        $this->tirarDaCampanha($campanha, auth()->id());

        return redirect()->route('campanhas.index')->with('ok', 'Você saiu da campanha.');
    }

    // ------------------------------------------------------------------
    // Fichas
    // ------------------------------------------------------------------

    public function vincularFicha(Request $request, Campanha $campanha)
    {
        $this->exigirMembro($campanha);

        $dados = $request->validate(['ficha_id' => 'required|integer']);

        // só dá para vincular fichas próprias
        $ficha = Personagem::where('id', $dados['ficha_id'])->where('usuario_id', auth()->id())->firstOrFail();

        CampanhaFicha::firstOrCreate(
            ['campanha_id' => $campanha->id, 'ficha_id' => $ficha->id],
            ['user_id' => auth()->id()]
        );

        return back()->with('ok', 'Ficha vinculada à campanha.');
    }

    public function desvincularFicha(Campanha $campanha, CampanhaFicha $vinculo)
    {
        abort_unless((int) $vinculo->campanha_id === (int) $campanha->id, 404);

        $uid = auth()->id();
        abort_unless($campanha->ehMestre($uid) || (int) $vinculo->user_id === (int) $uid, 403);

        $this->apagarFotoDoVinculo($vinculo);
        $vinculo->delete();

        return back()->with('ok', 'Ficha desvinculada.');
    }

    /**
     * Mestre abre a ficha completa (somente leitura) de qualquer jogador da campanha.
     * O dono da ficha também pode abrir por aqui, mas normalmente usa personagens.show.
     */
    public function verFicha(Campanha $campanha, CampanhaFicha $vinculo)
    {
        $this->exigirMembro($campanha);
        abort_unless((int) $vinculo->campanha_id === (int) $campanha->id, 404);
        $this->exigirMestreOuDono($campanha, $vinculo);

        // sem escopos globais: o mestre precisa abrir fichas que não são dele
        $personagem = Personagem::withoutGlobalScopes()->findOrFail($vinculo->ficha_id);
        $vinculo->loadMissing('user');

        $donoNome = ((int) $vinculo->user_id !== (int) auth()->id())
            ? ($vinculo->user->name ?? 'jogador')
            : null;

        // Mesma página da ficha normal (personagem/form.blade.php), em modo somente leitura:
        // campos travados, nada é salvo, com o link de voltar para a campanha.
        return view('personagem.form', [
            'personagem'     => $personagem,
            'somenteLeitura' => true,
            'voltarUrl'      => route('campanhas.show', $campanha),
            'donoNome'       => $donoNome ?? ($vinculo->user->name ?? 'jogador'),
        ]);
    }

    /**
     * Consulta rápida (polling) com o estado das fichas visíveis para quem está perguntando.
     * Mestre recebe todas; jogador recebe só as próprias. A "assinatura" muda sempre que
     * qualquer coisa da ficha muda, então o front sabe quando destacar a atualização.
     */
    public function fichasEstado(Campanha $campanha)
    {
        $this->exigirMembro($campanha);

        $uid       = auth()->id();
        $souMestre = $campanha->ehMestre($uid);

        $vinculos = $this->vinculosVisiveis($campanha, $uid, $souMestre)->get();
        $this->carregarFichas($vinculos);

        $fichas = $vinculos->map(function (CampanhaFicha $v) {
            $ficha = $v->ficha;
            $stats = $this->statsDaFicha($ficha);
            $nome  = $ficha->nome ?? 'Ficha removida';
            $ts    = optional($ficha?->updated_at)->timestamp ?? 0;

            return [
                'id'         => $v->id,
                'nome'       => $nome,
                'jogador'    => $v->user->name ?? '',
                'stats'      => $stats,
                'atualizado' => $ts,
                'assinatura' => md5(json_encode([$nome, $stats, $ts, $ficha?->id])),
            ];
        })->values();

        return response()->json([
            'fichas' => $fichas,
            'ids'    => $fichas->pluck('id')->values(),
        ])->header('Cache-Control', 'no-store');
    }

    /** Entrega a foto da ficha só para o mestre e para o dono da ficha. */
    public function fotoFicha(Campanha $campanha, CampanhaFicha $vinculo)
    {
        $this->exigirMembro($campanha);
        abort_unless((int) $vinculo->campanha_id === (int) $campanha->id, 404);
        $this->exigirMestreOuDono($campanha, $vinculo);

        abort_unless($vinculo->foto_path, 404);

        $caminho = Storage::disk('local')->path($vinculo->foto_path);
        abort_unless(is_file($caminho), 404);

        return response()->file($caminho, [
            'Cache-Control'          => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Só quem vinculou a ficha (mestre ou jogador) pode trocar a foto. */
    public function enviarFotoFicha(Request $request, Campanha $campanha, CampanhaFicha $vinculo)
    {
        $this->exigirMembro($campanha);
        abort_unless((int) $vinculo->campanha_id === (int) $campanha->id, 404);
        abort_unless((int) $vinculo->user_id === (int) auth()->id(), 403, 'Só quem vinculou a ficha pode trocar a foto.');

        $request->validate([
            'foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:3072', // 3 MB
        ], [
            'foto.required' => 'Escolha uma imagem.',
            'foto.image'    => 'O arquivo precisa ser uma imagem.',
            'foto.mimes'    => 'Use JPG, PNG ou WEBP.',
            'foto.max'      => 'A foto pode ter no máximo 3 MB.',
        ]);

        $this->apagarFotoDoVinculo($vinculo);

        $vinculo->foto_path = $request->file('foto')->store("campanhas/{$campanha->id}/fichas", 'local'); // fora do public
        $vinculo->save();

        return back()->with('ok', 'Foto da ficha atualizada.');
    }

    /** PV, Aura e Sanidade ajustados pela miniatura da campanha (mestre ou dono da ficha). */
    public function atualizarVitais(Request $request, Campanha $campanha, CampanhaFicha $vinculo)
    {
        $this->exigirMembro($campanha);
        abort_unless((int) $vinculo->campanha_id === (int) $campanha->id, 404);
        $this->exigirMestreOuDono($campanha, $vinculo);

        $dados = $request->validate([
            'vida_atual'      => 'nullable|integer|min:-9999|max:99999',
            'vida_maxima'     => 'nullable|integer|min:0|max:99999',
            'aura_atual'      => 'nullable|integer|min:-9999|max:99999',
            'aura_maxima'     => 'nullable|integer|min:0|max:99999',
            'sanidade_atual'  => 'nullable|integer|min:-9999|max:99999',
            'sanidade_maxima' => 'nullable|integer|min:0|max:99999',
        ]);

        // sem escopos globais: o mestre ajusta fichas que não são dele
        $ficha = Personagem::withoutGlobalScopes()->findOrFail($vinculo->ficha_id);
        // só os campos enviados mudam; os demais ficam como estão
        $ficha->fill(array_filter($dados, fn ($v) => $v !== null));
        $ficha->save();

        $stats = $this->statsDaFicha($ficha);

        return response()->json([
            'ok'          => true,
            'pv'          => $stats['pv']['atual'],       'pvMax'       => $stats['pv']['max'],
            'aura'        => $stats['aura']['atual'],     'auraMax'     => $stats['aura']['max'],
            'sanidade'    => $stats['sanidade']['atual'], 'sanidadeMax' => $stats['sanidade']['max'],
            'atualizado'  => optional($ficha->updated_at)->timestamp ?? 0,
        ]);
    }

    // ------------------------------------------------------------------
    // Imagem de fundo do banner
    // ------------------------------------------------------------------

    /** Entrega a imagem de fundo só para membros da campanha. */
    public function banner(Campanha $campanha)
    {
        $this->exigirMembro($campanha);

        $relativo = Schema::hasColumn('campanhas', 'banner_path') ? $campanha->banner_path : null;
        abort_unless($relativo, 404);

        $caminho = Storage::disk('local')->path($relativo);
        abort_unless(is_file($caminho), 404);

        return response()->file($caminho, [
            'Cache-Control'          => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Só o mestre troca a imagem de fundo. */
    public function enviarBanner(Request $request, Campanha $campanha)
    {
        $this->exigirMestre($campanha);

        if (!Schema::hasColumn('campanhas', 'banner_path')) {
            return back()->withErrors(['banner' => 'A coluna banner_path ainda não existe no banco: rode a migration 2026_10_28_000001.']);
        }

        $request->validate([
            'banner' => 'required|image|mimes:jpg,jpeg,png,webp|max:6144', // 6 MB
        ], [
            'banner.required' => 'Escolha uma imagem.',
            'banner.image'    => 'O arquivo precisa ser uma imagem.',
            'banner.mimes'    => 'Use JPG, PNG ou WEBP.',
            'banner.max'      => 'A imagem pode ter no máximo 6 MB.',
        ]);

        $this->apagarBanner($campanha);

        $campanha->banner_path = $request->file('banner')->store("campanhas/{$campanha->id}/banner", 'local'); // fora do public
        $campanha->save();

        return back()->with('ok', 'Imagem de fundo da campanha atualizada.');
    }

    public function salvarChaveMaldicoes(Request $request, Campanha $campanha)
    {
        $this->exigirMestre($campanha);

        $dados = $request->validate(['chave_maldicoes' => 'nullable|string|max:60']);
        $chave = trim((string) ($dados['chave_maldicoes'] ?? ''));

        if (Schema::hasColumn('campanhas', 'chave_maldicoes')) {
            $campanha->chave_maldicoes = $chave === '' ? null : $chave;
            $campanha->save();
        }

        return back()->with('ok', 'Chave de maldições atualizada.');
    }

    public function removerBanner(Campanha $campanha)
    {
        $this->exigirMestre($campanha);

        if (Schema::hasColumn('campanhas', 'banner_path')) {
            $this->apagarBanner($campanha);
            $campanha->banner_path = null;
            $campanha->save();
        }

        return back()->with('ok', 'Imagem de fundo removida.');
    }

    private function apagarBanner(Campanha $campanha): void
    {
        if (!empty($campanha->banner_path)) {
            Storage::disk('local')->delete($campanha->banner_path);
        }
    }

    // ------------------------------------------------------------------
    // Tabletops (mesas)
    // ------------------------------------------------------------------

    public function vincularMesa(Request $request, Campanha $campanha)
    {
        $this->exigirMestre($campanha);

        $dados = $request->validate(['mesa_id' => 'required|integer']);
        $mesa  = Mesa::where('id', $dados['mesa_id'])->where('user_id', auth()->id())->firstOrFail();

        $mesa->forceFill(['campanha_id' => $campanha->id])->save();

        return back()->with('ok', 'Tabletop vinculado à campanha.');
    }

    public function criarMesa(Request $request, Campanha $campanha)
    {
        $this->exigirMestre($campanha);

        $dados = $request->validate(['nome' => 'required|string|max:80']);

        // Ajuste os campos abaixo para ficarem iguais aos do TabletopController@store
        // (por exemplo, o estado inicial em JSON, caso a coluna não tenha valor padrão).
        $mesa = new Mesa();
        $mesa->forceFill([
            'user_id'     => auth()->id(),
            'nome'        => $dados['nome'],
            'campanha_id' => $campanha->id,
        ])->save();

        return redirect()->route('tabletop.show', $mesa)->with('ok', 'Tabletop criado para a campanha.');
    }

    public function desvincularMesa(Campanha $campanha, Mesa $mesa)
    {
        $this->exigirMestre($campanha);
        abort_unless((int) $mesa->campanha_id === (int) $campanha->id, 404);

        $mesa->forceFill(['campanha_id' => null])->save();

        return back()->with('ok', 'Tabletop desvinculado (ele continua na sua lista de mesas).');
    }

    // ------------------------------------------------------------------
    // Sessão
    // ------------------------------------------------------------------

    public function iniciarSessao(Campanha $campanha)
    {
        $this->exigirMestre($campanha);

        $campanha->forceFill(['sessao_ativa' => true, 'sessao_inicio' => now()])->save();

        return back()->with('ok', 'Sessão iniciada.');
    }

    public function encerrarSessao(Campanha $campanha)
    {
        $this->exigirMestre($campanha);

        $campanha->forceFill(['sessao_ativa' => false, 'sessao_inicio' => null])->save();
        if (Schema::hasColumn('campanhas', 'som_seq')) {
            $this->emitirSom($campanha, null, 'parar'); // corta qualquer som em andamento
        }

        return back()->with('ok', 'Sessão encerrada.');
    }

    /** Consulta rápida feita pelos jogadores (polling). */
    public function estado(Campanha $campanha)
    {
        $this->exigirMembro($campanha);

        return response()->json([
            'sessao_ativa' => (bool) $campanha->sessao_ativa,
            'som_seq'      => (int) $campanha->som_seq,
            'som_id'       => $campanha->som_id,
            'som_acao'     => $campanha->som_acao,
        ])->header('Cache-Control', 'no-store');
    }

    // ------------------------------------------------------------------
    // Efeitos sonoros
    // ------------------------------------------------------------------

    public function enviarSom(Request $request, Campanha $campanha)
    {
        $this->exigirMestre($campanha);

        $request->validate([
            'nome'    => 'required|string|max:60',
            'arquivo' => 'required|file|mimes:mp3,wav,ogg,m4a,aac|max:8192', // 8 MB
        ]);

        if (CampanhaSom::where('campanha_id', $campanha->id)->count() >= self::MAX_SONS) {
            return back()->withErrors(['arquivo' => 'Limite de ' . self::MAX_SONS . ' sons por campanha atingido.']);
        }

        $arquivo = $request->file('arquivo');
        $tamanho = (int) $arquivo->getSize();
        $mime    = $arquivo->getMimeType() ?: 'audio/mpeg';
        $caminho = $arquivo->store("campanhas/{$campanha->id}/sons", 'local'); // fora do public

        CampanhaSom::create([
            'campanha_id' => $campanha->id,
            'user_id'     => auth()->id(),
            'nome'        => $request->input('nome'),
            'arquivo'     => $caminho,
            'mime'        => Str::limit($mime, 60, ''),
            'tamanho'     => $tamanho,
        ]);

        return back()->with('ok', 'Som adicionado.');
    }

    public function excluirSom(Campanha $campanha, CampanhaSom $som)
    {
        $this->exigirMestre($campanha);
        abort_unless((int) $som->campanha_id === (int) $campanha->id, 404);

        // os efeitos coringa (storage/app/private/coringa) são compartilhados entre campanhas: só a linha sai
        if (!str_starts_with((string) $som->arquivo, 'coringa/')) {
            Storage::disk('local')->delete($som->arquivo);
        }
        $som->delete();

        return back()->with('ok', 'Som excluído.');
    }

    /** Entrega o áudio só para quem participa da campanha. */
    public function arquivoSom(Campanha $campanha, CampanhaSom $som)
    {
        $this->exigirMembro($campanha);
        abort_unless((int) $som->campanha_id === (int) $campanha->id, 404);

        $caminho = Storage::disk('local')->path($som->arquivo);
        abort_unless(is_file($caminho), 404);

        return response()->file($caminho, [
            'Content-Type'           => $som->mime,
            'Cache-Control'          => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function tocarSom(Campanha $campanha, CampanhaSom $som)
    {
        $this->exigirMestre($campanha);
        abort_unless((int) $som->campanha_id === (int) $campanha->id, 404);

        if (!$campanha->sessao_ativa) {
            return response()->json(['erro' => 'Inicie a sessão antes de tocar sons.'], 422);
        }
        if (!Schema::hasColumn('campanhas', 'som_seq')) {
            return response()->json(['erro' => 'O banco ainda não tem as colunas de som (som_seq, som_id, som_acao): rode a migration 2026_10_28_000004.'], 422);
        }

        $this->emitirSom($campanha, $som->id, 'tocar');

        return response()->json(['ok' => true]);
    }

    /** Adiciona à campanha os efeitos "coringa" (config/sons_coringa.php) que ainda não estiverem nela. */
    public function adicionarSonsPadrao(Campanha $campanha)
    {
        $this->exigirMestre($campanha);

        $existentes  = CampanhaSom::where('campanha_id', $campanha->id)->pluck('arquivo')->all();
        $total       = count($existentes);
        $adicionados = 0;
        $faltando    = 0;

        foreach (config('sons_coringa', []) as $som) {
            if (in_array($som['arquivo'], $existentes, true)) {
                continue;
            }
            $caminho = Storage::disk('local')->path($som['arquivo']);
            if (!is_file($caminho)) {
                $faltando++; // arquivo ainda não está no servidor
                continue;
            }
            if ($total >= self::MAX_SONS) {
                break;
            }
            CampanhaSom::create([
                'campanha_id' => $campanha->id,
                'user_id'     => auth()->id(),
                'nome'        => $som['nome'],
                'arquivo'     => $som['arquivo'],
                'mime'        => $som['mime'],
                'tamanho'     => (int) filesize($caminho),
            ]);
            $total++;
            $adicionados++;
        }

        $msg = $adicionados ? "{$adicionados} efeito(s) coringa adicionado(s)." : 'Todos os efeitos coringa já estão na campanha.';
        if ($faltando) {
            $msg .= " {$faltando} arquivo(s) ainda não foram enviados ao servidor.";
        }

        return back()->with('ok', $msg);
    }

    public function pararSom(Campanha $campanha)
    {
        $this->exigirMestre($campanha);
        if (!Schema::hasColumn('campanhas', 'som_seq')) {
            return response()->json(['erro' => 'O banco ainda não tem as colunas de som: rode a migration 2026_10_28_000004.'], 422);
        }
        $this->emitirSom($campanha, null, 'parar');

        return response()->json(['ok' => true]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function exigirMestre(Campanha $campanha): void
    {
        abort_unless($campanha->ehMestre(auth()->id()), 403, 'Apenas o mestre pode fazer isso.');
    }

    private function exigirMembro(Campanha $campanha): void
    {
        abort_unless($campanha->temMembro(auth()->id()), 403, 'Você não participa desta campanha.');
    }

    /** Mestre da campanha ou dono do vínculo (da ficha); qualquer outro membro é barrado. */
    private function exigirMestreOuDono(Campanha $campanha, CampanhaFicha $vinculo): void
    {
        $uid = auth()->id();

        abort_unless(
            $campanha->ehMestre($uid) || (int) $vinculo->user_id === (int) $uid,
            403,
            'Você só pode ver as suas próprias fichas.'
        );
    }

    /**
     * Consulta base dos vínculos que a pessoa pode enxergar:
     * o mestre vê todos, o jogador apenas os próprios.
     */
    private function vinculosVisiveis(Campanha $campanha, int $userId, bool $souMestre)
    {
        // a ficha é carregada à parte em carregarFichas(), sem depender da relação do model
        $q = CampanhaFicha::where('campanha_id', $campanha->id)->with('user');

        if (!$souMestre) {
            $q->where('user_id', $userId);
        }

        return $q;
    }

    /**
     * Busca as fichas (Personagem) dos vínculos direto pelo ficha_id e injeta na relação "ficha".
     * Assim o mestre enxerga fichas de outros jogadores mesmo que a relação do model esteja
     * com outra chave estrangeira ou que o Personagem tenha escopo global por usuário.
     */
    private function carregarFichas($vinculos): void
    {
        $ids = $vinculos->pluck('ficha_id')->filter()->unique()->values();

        $mapa = $ids->isEmpty()
            ? collect()
            : Personagem::withoutGlobalScopes()->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($vinculos as $v) {
            $v->setRelation('ficha', $mapa->get($v->ficha_id));
        }
    }

    /** Barras de PV, Aura e Sanidade da ficha (mesmas colunas usadas na view). */
    private function statsDaFicha(?Personagem $ficha): array
    {
        $par = fn ($atual, $max) => [
            'atual' => $atual !== null ? (int) $atual : null,
            'max'   => $max !== null ? (int) $max : null,
        ];

        return [
            'pv'       => $par($ficha->vida_atual ?? null,     $ficha->vida_maxima ?? null),
            'aura'     => $par($ficha->aura_atual ?? null,     $ficha->aura_maxima ?? null),
            'sanidade' => $par($ficha->sanidade_atual ?? null, $ficha->sanidade_maxima ?? null),
        ];
    }

    /** Registra o evento de som; os jogadores percebem pela mudança do som_seq. */
    private function emitirSom(Campanha $campanha, ?int $somId, string $acao): void
    {
        DB::transaction(function () use ($campanha, $somId, $acao) {
            $c = Campanha::lockForUpdate()->find($campanha->id);
            $c->forceFill([
                'som_seq'  => $c->som_seq + 1,
                'som_id'   => $somId,
                'som_acao' => $acao,
                'som_em'   => now(),
            ])->save();
        });
    }

    /** Apaga do disco a foto do vínculo (se houver). */
    private function apagarFotoDoVinculo(CampanhaFicha $vinculo): void
    {
        if ($vinculo->foto_path) {
            Storage::disk('local')->delete($vinculo->foto_path);
        }
    }

    /** Remove o jogador e as fichas dele vinculadas à campanha (com as fotos). */
    private function tirarDaCampanha(Campanha $campanha, int $userId): void
    {
        $vinculos = CampanhaFicha::where('campanha_id', $campanha->id)->where('user_id', $userId)->get();
        foreach ($vinculos as $v) {
            $this->apagarFotoDoVinculo($v);
        }

        DB::transaction(function () use ($campanha, $userId) {
            CampanhaJogador::where('campanha_id', $campanha->id)->where('user_id', $userId)->delete();
            CampanhaFicha::where('campanha_id', $campanha->id)->where('user_id', $userId)->delete();
        });
    }

    /** Gera o código que o mestre usa para convidar a pessoa (ex.: K7P2QX). */
    private function garantirCodigo(User $user): void
    {
        if (!empty($user->codigo_jogador)) {
            return;
        }

        do {
            $codigo = Str::upper(Str::random(6));
            // evita caracteres que se confundem na leitura
            $codigo = strtr($codigo, ['O' => 'X', '0' => 'Y', 'I' => 'Z', '1' => 'W']);
        } while (User::where('codigo_jogador', $codigo)->exists());

        User::whereKey($user->id)->update(['codigo_jogador' => $codigo]);
        $user->codigo_jogador = $codigo;
    }
}