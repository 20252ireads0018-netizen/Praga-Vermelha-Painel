<?php

namespace App\Http\Controllers;

use App\Jogos\Gladio;
use App\Jogos\PelaDama;
use App\Models\Campanha;
use App\Models\GladioCarta;
use App\Models\GladioImagem;
use App\Models\JogoCarteira;
use App\Models\JogoPartida;
use App\Models\PelaDamaPeca;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Jogos da campanha: Gladio (cartas do mestre, apostas de Dinheiro) e Pela Dama (xadrez com peças do jogador).
 * As regras ficam em App\Jogos; aqui só entram permissões, Dinheiro e gravação. Sincroniza por consulta (~1 s).
 */
class CampanhaJogoController extends Controller
{
    public const JOGOS = ['gladio', 'pela_dama'];

    /* ================================================================== */
    /*  Páginas                                                            */
    /* ================================================================== */

    public function index(Campanha $campanha): View
    {
        $uid = (int) auth()->id();
        $this->exigirMembro($campanha);

        return view('campanhas.jogos.index', [
            'campanha'  => $campanha,
            'souMestre' => $campanha->ehMestre($uid),
            'membros'   => $this->membros($campanha),
            'cartas'    => GladioCarta::where('campanha_id', $campanha->id)->orderBy('tipo')->orderBy('custo')->orderBy('nome')->get(),
            'pecas'     => PelaDamaPeca::where('campanha_id', $campanha->id)->where('user_id', $uid)->orderBy('nome')->get(),
            'imagens'   => $this->listaImagens($campanha),
            'lobby'     => $this->dadosLobby($campanha),
        ]);
    }

    /** Lista de partidas e Dinheiro de todos (o lobby consulta a cada poucos segundos). */
    public function lobby(Campanha $campanha): JsonResponse
    {
        $this->exigirMembro($campanha);

        return response()->json($this->dadosLobby($campanha))->header('Cache-Control', 'no-store');
    }

    public function partida(Campanha $campanha, JogoPartida $partida): View
    {
        $this->exigirMembro($campanha);
        $this->daCampanha($campanha, $partida);

        $cartas = $partida->jogo === 'gladio'
            ? GladioCarta::where('campanha_id', $campanha->id)->get(['id', 'imagem', 'descricao'])
                ->mapWithKeys(fn ($c) => [(string) $c->id => ['imagem' => $c->imagem, 'descricao' => (string) $c->descricao]])
            : collect();

        return view('campanhas.jogos.partida', [
            'campanha' => $campanha,
            'partida'  => $partida,
            'imagens'  => $cartas,
            'dados'    => $this->dadosPartida($partida),
        ]);
    }

    /* ================================================================== */
    /*  Dinheiro, cartas e peças                                           */
    /* ================================================================== */

    /** O mestre define o Dinheiro de um membro. */
    public function dinheiro(Request $request, Campanha $campanha): JsonResponse
    {
        $this->exigirMestre($campanha);
        $dados = $request->validate([
            'user_id'  => ['required', 'integer'],
            'dinheiro' => ['required', 'integer', 'min:0', 'max:1000000000'],
        ]);
        abort_unless($campanha->temMembro((int) $dados['user_id']), 422, 'Essa pessoa não participa da campanha.');

        JogoCarteira::updateOrCreate(
            ['campanha_id' => $campanha->id, 'user_id' => (int) $dados['user_id']],
            ['dinheiro' => (int) $dados['dinheiro']]
        );

        return response()->json(['ok' => true] + $this->dadosLobby($campanha));
    }

    /** Criador de cartas (só o mestre): cria ou edita. */
    public function salvarCarta(Request $request, Campanha $campanha): JsonResponse
    {
        $this->exigirMestre($campanha);
        $d = $request->validate([
            'id'        => ['nullable', 'integer'],
            'nome'      => ['required', 'string', 'max:60'],
            'tipo'      => ['required', Rule::in(Gladio::TIPOS)],
            'custo'     => ['required', 'integer', 'min:0', 'max:10'],
            'ataque'    => ['nullable', 'integer', 'min:0', 'max:9999'],
            'vida'      => ['nullable', 'integer', 'min:0', 'max:9999'],
            'efeito'    => ['nullable', Rule::in(Gladio::EFEITOS)],
            'valor'     => ['nullable', 'integer', 'min:0', 'max:9999'],
            'descricao' => ['nullable', 'string', 'max:500'],
            'imagem'    => ['nullable', 'string', 'max:400000'],
            'sem_imagem'=> ['nullable', 'boolean'],
        ]);
        if ($d['tipo'] === 'lenda' && (int) ($d['vida'] ?? 0) < 1) {
            return response()->json(['ok' => false, 'erro' => 'Uma Lenda precisa de pelo menos 1 de vida.'], 422);
        }
        if ($d['tipo'] === 'feitico' && empty($d['efeito'])) {
            return response()->json(['ok' => false, 'erro' => 'Escolha o efeito do feitiço.'], 422);
        }

        $carta = !empty($d['id'])
            ? GladioCarta::where('campanha_id', $campanha->id)->findOrFail((int) $d['id'])
            : new GladioCarta(['campanha_id' => $campanha->id]);

        $lenda = $d['tipo'] === 'lenda';
        $carta->fill([
            'nome'      => trim($d['nome']),
            'tipo'      => $d['tipo'],
            'custo'     => (int) $d['custo'],
            'ataque'    => $lenda ? (int) ($d['ataque'] ?? 0) : 0,
            'vida'      => $lenda ? (int) ($d['vida'] ?? 1) : 0,
            'efeito'    => $lenda ? null : $d['efeito'],
            'valor'     => $lenda ? 0 : (int) ($d['valor'] ?? 0),
            'descricao' => trim((string) ($d['descricao'] ?? '')) ?: null,
        ]);
        if (!empty($d['imagem'])) {
            if (!preg_match('#^data:image/(png|jpe?g|webp);base64,[A-Za-z0-9+/=]+$#', $d['imagem'])) {
                return response()->json(['ok' => false, 'erro' => 'Imagem inválida.'], 422);
            }
            $carta->imagem = $d['imagem'];
        } elseif (!empty($d['sem_imagem'])) {
            $carta->imagem = null;
        }
        $carta->save();

        return response()->json(['ok' => true, 'carta' => $this->cartaJson($carta)]);
    }

    public function excluirCarta(Campanha $campanha, GladioCarta $carta): JsonResponse
    {
        $this->exigirMestre($campanha);
        abort_unless((int) $carta->campanha_id === (int) $campanha->id, 404);
        $carta->delete(); // partidas em andamento guardam uma cópia da carta

        return response()->json(['ok' => true]);
    }

    /** Criador de peças (cada membro tem as suas). */
    public function salvarPeca(Request $request, Campanha $campanha): JsonResponse
    {
        $this->exigirMembro($campanha);
        $uid = (int) auth()->id();
        $d = $request->validate([
            'id'      => ['nullable', 'integer'],
            'nome'    => ['required', 'string', 'max:40'],
            'simbolo' => ['required', 'string', 'max:8'],
            'regras'  => ['required', 'string', 'max:20000'],
        ]);
        $regras = PelaDama::limparRegras(json_decode($d['regras'], true));
        if (!$regras) {
            return response()->json(['ok' => false, 'erro' => 'Marque pelo menos um movimento para a peça.'], 422);
        }
        $simbolo = mb_substr(trim($d['simbolo']), 0, 2);
        if ($simbolo === '') {
            return response()->json(['ok' => false, 'erro' => 'Escolha um símbolo para a peça.'], 422);
        }

        $peca = !empty($d['id'])
            ? PelaDamaPeca::where('campanha_id', $campanha->id)->where('user_id', $uid)->findOrFail((int) $d['id'])
            : new PelaDamaPeca(['campanha_id' => $campanha->id, 'user_id' => $uid]);
        $peca->fill(['nome' => trim($d['nome']), 'simbolo' => $simbolo, 'regras' => $regras])->save();

        return response()->json(['ok' => true, 'peca' => $this->pecaJson($peca)]);
    }

    /* ---------- Galeria de imagens das cartas (o mestre envia; todos os membros veem) ---------- */

    /** Envia uma ou várias imagens (o navegador já reduz e converte para JPEG). */
    public function enviarImagens(Request $request, Campanha $campanha): JsonResponse
    {
        $this->exigirMestre($campanha);
        if (!Schema::hasTable('gladio_imagens')) {
            return $this->erro('A galeria ainda não existe no banco: rode o SQL da migração 2026_10_28_000012.');
        }
        $d = $request->validate([
            'imagens'   => ['required', 'array', 'max:40'],
            'imagens.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'nomes'     => ['nullable', 'array'],
            'nomes.*'   => ['nullable', 'string', 'max:120'],
            'pasta'     => ['nullable', 'string', 'max:60'],
        ], [
            'imagens.*.mimes' => 'Um dos arquivos não é uma imagem válida.',
            'imagens.*.max'   => 'Cada imagem pode ter no máximo 4 MB.',
        ]);
        $pasta = trim((string) ($d['pasta'] ?? '')) ?: null;

        $novas = [];
        foreach ($request->file('imagens') as $i => $arq) {
            $nome = trim((string) ($d['nomes'][$i] ?? '')) ?: pathinfo($arq->getClientOriginalName(), PATHINFO_FILENAME);
            $novas[] = GladioImagem::create([
                'campanha_id' => $campanha->id,
                'pasta'       => $pasta,
                'nome'        => Str::limit($nome ?: 'Imagem', 120, ''),
                'caminho'     => $arq->store("campanhas/{$campanha->id}/galeria", 'local'), // fora do public
            ])->paraLista();
        }

        return response()->json(['ok' => true, 'imagens' => $novas]);
    }

    public function verImagem(Campanha $campanha, GladioImagem $imagem)
    {
        $this->exigirMembro($campanha);
        abort_unless((int) $imagem->campanha_id === (int) $campanha->id, 404);
        $caminho = Storage::disk('local')->path($imagem->caminho);
        abort_unless(is_file($caminho), 404);

        return response()->file($caminho, [
            'Cache-Control'          => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function excluirImagem(Campanha $campanha, GladioImagem $imagem): JsonResponse
    {
        $this->exigirMestre($campanha);
        abort_unless((int) $imagem->campanha_id === (int) $campanha->id, 404);
        // cartas já criadas guardam a própria cópia da imagem; o arquivo só sai se nenhuma outra entrada da galeria usa
        if (!GladioImagem::where('caminho', $imagem->caminho)->whereKeyNot($imagem->id)->exists()) {
            Storage::disk('local')->delete($imagem->caminho);
        }
        $imagem->delete();

        return response()->json(['ok' => true]);
    }

    public function excluirPeca(Campanha $campanha, PelaDamaPeca $peca): JsonResponse
    {
        $this->exigirMembro($campanha);
        abort_unless((int) $peca->campanha_id === (int) $campanha->id && (int) $peca->user_id === (int) auth()->id(), 404);
        $peca->delete();

        return response()->json(['ok' => true]);
    }

    /* ================================================================== */
    /*  Desafios                                                           */
    /* ================================================================== */

    public function criarPartida(Request $request, Campanha $campanha): JsonResponse
    {
        $this->exigirMembro($campanha);
        $uid = (int) auth()->id();
        $d = $request->validate([
            'jogo'         => ['required', Rule::in(self::JOGOS)],
            'aposta'       => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'convidado_id' => ['nullable', 'integer'],
            'exercito'     => ['nullable', 'string', 'max:2000'],
        ]);
        $convidado = !empty($d['convidado_id']) ? (int) $d['convidado_id'] : null;
        if ($convidado !== null && ($convidado === $uid || !$campanha->temMembro($convidado))) {
            return $this->erro('Escolha alguém da campanha para desafiar.');
        }

        $aposta = 0;
        $config = null;
        if ($d['jogo'] === 'gladio') {
            if (!GladioCarta::where('campanha_id', $campanha->id)->exists()) {
                return $this->erro('O mestre ainda não criou nenhuma carta de Gladio.');
            }
            $aposta = (int) ($d['aposta'] ?? 0);
            if ($aposta > $this->saldo($campanha, $uid)) {
                return $this->erro('Você não tem Dinheiro suficiente para essa aposta.');
            }
        } else {
            $ex = json_decode((string) ($d['exercito'] ?? ''), true) ?: PelaDama::exercitoPadrao();
            if ($msg = PelaDama::validarExercito($ex, $this->tiposDe($campanha, $uid))) {
                return $this->erro($msg);
            }
            $config = ['exercito' => $ex];
        }

        JogoPartida::create([
            'campanha_id' => $campanha->id, 'jogo' => $d['jogo'], 'jogador1_id' => $uid,
            'convidado_id' => $convidado, 'aposta' => $aposta, 'status' => 'aberta', 'config' => $config,
        ]);

        return response()->json(['ok' => true] + $this->dadosLobby($campanha));
    }

    /** Aceitar um desafio: no Gladio as duas apostas saem da carteira agora e voltam para quem vencer. */
    public function aceitar(Request $request, Campanha $campanha, JogoPartida $partida): JsonResponse
    {
        $this->exigirMembro($campanha);
        $this->daCampanha($campanha, $partida);
        $uid = (int) auth()->id();
        $request->validate(['exercito' => ['nullable', 'string', 'max:2000']]);

        try {
            DB::transaction(function () use ($request, $campanha, $partida, $uid) {
                $p = JogoPartida::lockForUpdate()->findOrFail($partida->id);
                if ($p->status !== 'aberta') {
                    throw new \InvalidArgumentException('Esse desafio não está mais aberto.');
                }
                if ((int) $p->jogador1_id === $uid) {
                    throw new \InvalidArgumentException('Você não pode aceitar o próprio desafio.');
                }
                if ($p->convidado_id && (int) $p->convidado_id !== $uid) {
                    throw new \InvalidArgumentException('Esse desafio é para outra pessoa.');
                }

                if ($p->jogo === 'gladio') {
                    $cartas = GladioCarta::where('campanha_id', $campanha->id)->get()
                        ->mapWithKeys(fn ($c) => [(string) $c->id => $c->paraJogo()])->all();
                    if ($p->aposta > 0) {
                        foreach ([(int) $p->jogador1_id, $uid] as $quem) {
                            $c = $this->carteira($campanha, $quem, true);
                            if ($c->dinheiro < $p->aposta) {
                                throw new \InvalidArgumentException($quem === $uid
                                    ? 'Você não tem Dinheiro suficiente para essa aposta.'
                                    : 'Quem desafiou não tem mais Dinheiro suficiente para a aposta.');
                            }
                            $c->dinheiro -= $p->aposta;
                            $c->save();
                        }
                    }
                    $estado = Gladio::iniciar($cartas, (int) $p->aposta);
                } else {
                    $ex2 = json_decode((string) $request->input('exercito', ''), true) ?: PelaDama::exercitoPadrao();
                    $tipos = $this->tiposDe($campanha, (int) $p->jogador1_id) + $this->tiposDe($campanha, $uid);
                    if ($msg = PelaDama::validarExercito($ex2, $this->tiposDe($campanha, $uid))) {
                        throw new \InvalidArgumentException($msg);
                    }
                    $ex1 = $p->config['exercito'] ?? PelaDama::exercitoPadrao();
                    if (PelaDama::validarExercito($ex1, $tipos)) {
                        $ex1 = PelaDama::exercitoPadrao(); // a peça de quem desafiou foi apagada: joga com o padrão
                    }
                    $estado = PelaDama::iniciar($tipos, $ex1, $ex2);
                }

                $p->jogador2_id = $uid;
                $p->status = 'andamento';
                $p->estado = $estado;
                $p->versao = (int) $p->versao + 1;
                $p->save();
            });
        } catch (\InvalidArgumentException $e) {
            return $this->erro($e->getMessage());
        }

        return response()->json(['ok' => true, 'url' => route('campanhas.jogos.partida', [$campanha, $partida])]);
    }

    /** Desafio aberto: quem criou ou o mestre cancela. Em andamento: só o mestre, e as apostas voltam. */
    public function cancelar(Campanha $campanha, JogoPartida $partida): JsonResponse
    {
        $this->exigirMembro($campanha);
        $this->daCampanha($campanha, $partida);
        $uid = (int) auth()->id();
        $mestre = $campanha->ehMestre($uid);

        try {
            DB::transaction(function () use ($campanha, $partida, $uid, $mestre) {
                $p = JogoPartida::lockForUpdate()->findOrFail($partida->id);
                if ($p->status === 'aberta') {
                    if (!$mestre && (int) $p->jogador1_id !== $uid) {
                        throw new \InvalidArgumentException('Só quem criou o desafio ou o mestre pode cancelar.');
                    }
                } elseif ($p->status === 'andamento') {
                    if (!$mestre) {
                        throw new \InvalidArgumentException('Só o mestre pode cancelar uma partida em andamento.');
                    }
                    if ($p->jogo === 'gladio' && $p->aposta > 0) {
                        foreach ([(int) $p->jogador1_id, (int) $p->jogador2_id] as $quem) {
                            $c = $this->carteira($campanha, $quem, true);
                            $c->dinheiro += $p->aposta;
                            $c->save();
                        }
                    }
                } else {
                    throw new \InvalidArgumentException('Essa partida já terminou.');
                }
                $p->status = 'cancelada';
                $p->versao = (int) $p->versao + 1;
                $p->save();
            });
        } catch (\InvalidArgumentException $e) {
            return $this->erro($e->getMessage());
        }

        return response()->json(['ok' => true] + $this->dadosLobby($campanha));
    }

    /* ================================================================== */
    /*  Partida                                                            */
    /* ================================================================== */

    public function estado(Request $request, Campanha $campanha, JogoPartida $partida): JsonResponse
    {
        $this->exigirMembro($campanha);
        $this->daCampanha($campanha, $partida);
        if ((int) $request->query('v', 0) === (int) $partida->versao) {
            return response()->json(['v' => (int) $partida->versao])->header('Cache-Control', 'no-store');
        }

        return response()->json($this->dadosPartida($partida))->header('Cache-Control', 'no-store');
    }

    public function acao(Request $request, Campanha $campanha, JogoPartida $partida): JsonResponse
    {
        $this->exigirMembro($campanha);
        $this->daCampanha($campanha, $partida);
        $uid = (int) auth()->id();
        $request->validate([
            'acao' => ['required', 'string', 'max:20'],
            'inst' => ['nullable', 'integer'],
            'alvo' => ['nullable', 'string', 'max:20'],
            'x' => ['nullable', 'integer'], 'y' => ['nullable', 'integer'],
            'tx' => ['nullable', 'integer'], 'ty' => ['nullable', 'integer'],
        ]);

        try {
            $p = DB::transaction(function () use ($request, $campanha, $partida, $uid) {
                $p = JogoPartida::lockForUpdate()->findOrFail($partida->id);
                if ($p->status !== 'andamento') {
                    throw new \InvalidArgumentException('A partida não está em andamento.');
                }
                $lado = $p->ladoDe($uid);
                if (!$lado) {
                    throw new \InvalidArgumentException('Você está só assistindo esta partida.');
                }
                $e = $p->estado;
                $acao = $request->input('acao');

                if ($p->jogo === 'gladio') {
                    $inst = (int) $request->input('inst', 0);
                    $alvo = $request->input('alvo');
                    $e = match ($acao) {
                        'jogar'    => Gladio::jogar($e, $lado, $inst, $alvo),
                        'atacar'   => Gladio::atacar($e, $lado, $inst, $alvo),
                        'passar'   => Gladio::passar($e, $lado),
                        'desistir' => Gladio::desistir($e, $lado),
                        default    => throw new \InvalidArgumentException('Ação desconhecida.'),
                    };
                    $venceu = $e['fim'] ? $e['fim']['vencedor'] : null;
                } else {
                    if ($acao === 'desistir') {
                        if ($e['fim']) {
                            throw new \InvalidArgumentException('A partida já acabou.');
                        }
                        $e['fim'] = ['motivo' => 'desistencia', 'vencedor' => $lado === 'b' ? 'p' : 'b'];
                    } elseif ($acao === 'mover') {
                        $e = PelaDama::mover($e, $lado, (int) $request->input('x'), (int) $request->input('y'),
                            (int) $request->input('tx'), (int) $request->input('ty'));
                    } else {
                        throw new \InvalidArgumentException('Ação desconhecida.');
                    }
                    $venceu = $e['fim'] ? $e['fim']['vencedor'] : null;
                }

                $p->estado = $e;
                $p->versao = (int) $p->versao + 1;
                if ($e['fim']) {
                    $p->status = 'fim';
                    // lado de quem criou o desafio: 'a' no Gladio, 'b' (brancas) no Pela Dama; empate não tem vencedor
                    $ladoJ1 = $p->jogo === 'gladio' ? 'a' : 'b';
                    $vencedorId = $venceu === null ? null : ($venceu === $ladoJ1 ? $p->jogador1_id : $p->jogador2_id);
                    $p->vencedor_id = $vencedorId;
                    if ($p->jogo === 'gladio' && $p->aposta > 0 && $vencedorId) {
                        $c = $this->carteira($campanha, (int) $vencedorId, true);
                        $c->dinheiro += 2 * $p->aposta; // as duas apostas
                        $c->save();
                    }
                }
                $p->save();

                return $p;
            });
        } catch (\InvalidArgumentException $e) {
            return $this->erro($e->getMessage());
        }

        return response()->json(['ok' => true] + $this->dadosPartida($p));
    }

    /* ================================================================== */
    /*  Auxiliares                                                         */
    /* ================================================================== */

    private function erro(string $msg): JsonResponse
    {
        return response()->json(['ok' => false, 'erro' => $msg], 422);
    }

    private function exigirMembro(Campanha $campanha): void
    {
        abort_unless($campanha->temMembro(auth()->id()), 403, 'Você não participa desta campanha.');
    }

    private function exigirMestre(Campanha $campanha): void
    {
        abort_unless($campanha->ehMestre(auth()->id()), 403, 'Apenas o mestre pode fazer isso.');
    }

    private function daCampanha(Campanha $campanha, JogoPartida $partida): void
    {
        abort_unless((int) $partida->campanha_id === (int) $campanha->id, 404);
    }

    private function carteira(Campanha $campanha, int $uid, bool $travar = false): JogoCarteira
    {
        JogoCarteira::firstOrCreate(['campanha_id' => $campanha->id, 'user_id' => $uid], ['dinheiro' => 0]);
        $q = JogoCarteira::where('campanha_id', $campanha->id)->where('user_id', $uid);

        return ($travar ? $q->lockForUpdate() : $q)->firstOrFail();
    }

    private function saldo(Campanha $campanha, int $uid): int
    {
        return (int) (JogoCarteira::where('campanha_id', $campanha->id)->where('user_id', $uid)->value('dinheiro') ?? 0);
    }

    /** Mestre e jogadores ativos: [{ id, nome, mestre }]. */
    private function membros(Campanha $campanha): array
    {
        $campanha->loadMissing(['mestre', 'participantes.user']);
        $lista = [];
        if ($campanha->mestre) {
            $lista[] = ['id' => (int) $campanha->mestre->id, 'nome' => (string) $campanha->mestre->name, 'mestre' => true];
        }
        foreach ($campanha->participantes->where('status', 'ativo') as $pj) {
            if ($pj->user && (int) $pj->user->id !== (int) $campanha->mestre_id) {
                $lista[] = ['id' => (int) $pj->user->id, 'nome' => (string) $pj->user->name, 'mestre' => false];
            }
        }

        return $lista;
    }

    /** Tipos de peça que um membro pode escalar: os padrão e os que ele criou. */
    private function tiposDe(Campanha $campanha, int $uid): array
    {
        $tipos = PelaDama::tiposPadrao();
        foreach (PelaDamaPeca::where('campanha_id', $campanha->id)->where('user_id', $uid)->get() as $peca) {
            $tipos[$peca->tipoId()] = $peca->paraJogo();
        }

        return $tipos;
    }

    /** Galeria da campanha (vazia enquanto a tabela não existir no banco). */
    private function listaImagens(Campanha $campanha): array
    {
        if (!Schema::hasTable('gladio_imagens')) {
            return [];
        }

        return GladioImagem::where('campanha_id', $campanha->id)->orderBy('pasta')->orderBy('id')->get()
            ->map(fn (GladioImagem $i) => $i->paraLista())->all();
    }

    private function cartaJson(GladioCarta $c): array
    {
        return ['id' => $c->id] + $c->paraJogo() + ['imagem' => $c->imagem];
    }

    private function pecaJson(PelaDamaPeca $p): array
    {
        return ['id' => $p->id, 'tipo' => $p->tipoId(), 'nome' => $p->nome, 'simbolo' => $p->simbolo, 'regras' => $p->regras ?: []];
    }

    private function dadosLobby(Campanha $campanha): array
    {
        $uid = (int) auth()->id();
        $mestre = $campanha->ehMestre($uid);
        $nomes = collect($this->membros($campanha))->pluck('nome', 'id');
        $nome = fn ($id) => $id ? ($nomes[(int) $id] ?? User::whereKey($id)->value('name') ?? '—') : null;

        $partidas = JogoPartida::where('campanha_id', $campanha->id)
            ->where(fn ($q) => $q->whereIn('status', ['aberta', 'andamento'])->orWhere('updated_at', '>=', now()->subDays(7)))
            ->orderByRaw("CASE status WHEN 'andamento' THEN 0 WHEN 'aberta' THEN 1 WHEN 'fim' THEN 2 ELSE 3 END")
            ->orderByDesc('updated_at')
            ->limit(40)
            ->get(['id', 'jogo', 'jogador1_id', 'jogador2_id', 'convidado_id', 'aposta', 'status', 'vencedor_id', 'updated_at']);

        return [
            'carteiras' => JogoCarteira::where('campanha_id', $campanha->id)->pluck('dinheiro', 'user_id'),
            'partidas'  => $partidas->map(fn ($p) => [
                'id'        => $p->id,
                'jogo'      => $p->jogo,
                'status'    => $p->status,
                'aposta'    => (int) $p->aposta,
                'j1'        => $nome($p->jogador1_id),
                'j2'        => $nome($p->jogador2_id),
                'convidado' => $nome($p->convidado_id),
                'vencedor'  => $nome($p->vencedor_id),
                'meu'       => (int) $p->jogador1_id === $uid || (int) $p->jogador2_id === $uid,
                'aceitar'   => $p->status === 'aberta' && (int) $p->jogador1_id !== $uid && (!$p->convidado_id || (int) $p->convidado_id === $uid),
                'cancelar'  => ($p->status === 'aberta' && ($mestre || (int) $p->jogador1_id === $uid)) || ($p->status === 'andamento' && $mestre),
                'url'       => route('campanhas.jogos.partida', [$campanha, $p->id]),
            ])->values(),
        ];
    }

    /** Estado que o navegador recebe: no Gladio cada um só vê a própria mão; no Pela Dama vão os lances possíveis de quem joga. */
    private function dadosPartida(JogoPartida $p): array
    {
        $uid = (int) auth()->id();
        $lado = $p->ladoDe($uid);
        $e = $p->estado;
        if ($e && $p->jogo === 'gladio') {
            $e = Gladio::visao($e, $lado);
        } elseif ($e && $p->jogo === 'pela_dama') {
            $e['legais'] = $lado && $p->status === 'andamento' && ($e['vez'] ?? null) === $lado ? PelaDama::mapaLegais($e) : (object) [];
        }
        $p->loadMissing(['jogador1', 'jogador2']);

        return [
            'v'        => (int) $p->versao,
            'jogo'     => $p->jogo,
            'status'   => $p->status,
            'aposta'   => (int) $p->aposta,
            'lado'     => $lado,
            'nomes'    => [
                'j1' => (string) optional($p->jogador1)->name,
                'j2' => (string) optional($p->jogador2)->name,
            ],
            'vencedor' => $p->vencedor_id ? ((int) $p->vencedor_id === (int) $p->jogador1_id ? 'j1' : 'j2') : null,
            'estado'   => $e,
        ];
    }
}
