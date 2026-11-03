<?php

namespace App\Http\Controllers;

use App\Jogos\Gladio;
use App\Jogos\GladioBot;
use App\Jogos\PelaDama;
use App\Models\Campanha;
use App\Models\CampanhaFicha;
use App\Models\GladioCarta;
use App\Models\GladioDeck;
use App\Models\GladioImagem;
use App\Models\JogoCarteira;
use App\Models\JogoPartida;
use App\Models\PelaDamaPeca;
use App\Models\Personagem;
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
    /** O bolão de quem assiste aceita apostas até o fim deste turno. */
    private const BOLAO_ATE_TURNO = 3;

    /** Letras das equipes do Gladio (índice da equipe => letra). */
    private const LETRAS = ['A', 'B', 'C'];

    /** Campanhas do catálogo oficial de cartas (calculado uma vez por requisição). */
    private ?array $oficiais = null;

    public const JOGOS = ['gladio', 'pela_dama'];
    public const MAX_PECAS = 60; // peças de Pela Dama por pessoa em cada campanha (cada uma pode ter foto)

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
            'podeCartas' => $this->donoDasCartas(), // só a conta dona do Gladio cria e edita cartas
            'membros'   => $this->membros($campanha),
            'cartas'    => GladioCarta::whereIn('campanha_id', $this->campanhasOficiais($campanha))->orderBy('tipo')->orderBy('custo')->orderBy('nome')->get(),
            'pecas'     => $this->pecasVisiveis($campanha, $uid)->map(fn ($p) => $this->pecaJson($p, $uid))->values(),
            'imagens'   => $this->listaImagens($campanha),
            'decks'     => $this->listaDecks($campanha, $uid),
            'playmat'   => $this->playmatUrl($campanha, $uid),
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
            ? GladioCarta::whereIn('campanha_id', $this->campanhasOficiais($campanha))->get(['id', 'imagem', 'descricao'])
                ->mapWithKeys(fn ($c) => [(string) $c->id => ['imagem' => $c->imagem, 'descricao' => (string) $c->descricao]])
            : collect();

        // playmat de cada jogador do Gladio, pelo lado dele na mesa
        $playmats = [];
        if ($partida->jogo === 'gladio') {
            foreach ($partida->jogadores() as $k => $uid) {
                if ($url = $this->playmatUrl($campanha, (int) $uid)) {
                    $playmats[Gladio::LADOS[$k]] = $url;
                }
            }
        }

        return view('campanhas.jogos.partida', [
            'campanha' => $campanha,
            'partida'  => $partida,
            'imagens'  => $cartas,
            'playmats' => (object) $playmats,
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
        $this->exigirDonoDasCartas();
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
            'imagem_arquivo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:600'], // KB
            'sem_imagem'=> ['nullable', 'boolean'],
            'palavras'  => ['nullable', 'array'],
            'palavras.*'=> [Rule::in(array_keys(Gladio::PALAVRAS))],
            'subtipos'  => ['nullable', 'string', 'max:120'], // hexomante, aluno, kuro, forma hexomante…
            'limite'    => ['nullable', 'integer', 'min:0', 'max:' . GladioDeck::COPIAS], // banlist: vazio = livre, 0 = banida
        ]);
        if ($d['tipo'] === 'lenda' && (int) ($d['vida'] ?? 0) < 1) {
            return response()->json(['ok' => false, 'erro' => 'Uma Lenda precisa de pelo menos 1 de vida.'], 422);
        }
        $carta = !empty($d['id'])
            ? GladioCarta::whereIn('campanha_id', $this->campanhasOficiais($campanha))->findOrFail((int) $d['id'])
            : new GladioCarta(['campanha_id' => $this->campanhasOficiais($campanha)[0] ?? $campanha->id]); // carta nova entra no catálogo oficial

        $lenda = $d['tipo'] === 'lenda';
        $equipo = $d['tipo'] === 'equipo'; // Equipamento: ataque/vida são o bônus que a Lenda equipada recebe
        $carta->fill([
            'nome'      => trim($d['nome']),
            'tipo'      => $d['tipo'],
            'custo'     => (int) $d['custo'],
            'ataque'    => $lenda || $equipo ? (int) ($d['ataque'] ?? 0) : 0,
            'vida'      => $lenda ? (int) ($d['vida'] ?? 1) : ($equipo ? (int) ($d['vida'] ?? 0) : 0),
            // feitiço não tem efeito automático: quem joga escolhe o efeito e o valor que a descrição pede
            'efeito'    => null,
            'valor'     => 0,
            'descricao' => trim((string) ($d['descricao'] ?? '')) ?: null,
        ]);
        if (Schema::hasColumn('gladio_cartas', 'palavras')) {
            $palavras = implode(',', array_unique($d['palavras'] ?? [])); // qualquer tipo de carta pode ter palavras-chave
            if (strlen($palavras) > 100) { // tamanho da coluna
                return response()->json(['ok' => false, 'erro' => 'Palavras-chave demais numa carta só: escolha menos.'], 422);
            }
            $carta->palavras = $palavras ?: null;
        }
        if (Schema::hasColumn('gladio_cartas', 'limite')) {
            $carta->limite = isset($d['limite']) && $d['limite'] !== '' && (int) $d['limite'] < GladioDeck::COPIAS ? (int) $d['limite'] : null;
        }
        if (Schema::hasColumn('gladio_cartas', 'subtipos')) {
            $carta->subtipos = trim(preg_replace('/\s*,\s*/', ', ', (string) ($d['subtipos'] ?? '')), ' ,') ?: null;
        }
        if ($request->hasFile('imagem_arquivo')) {
            // A imagem vem como arquivo: em texto (base64) o firewall da hospedagem às vezes barra o envio com erro 406.
            // Guardada como data URL, igual às outras cartas.
            $arq = $request->file('imagem_arquivo');
            $carta->imagem = 'data:' . $arq->getMimeType() . ';base64,' . base64_encode((string) file_get_contents($arq->getRealPath()));
        } elseif (!empty($d['imagem'])) {
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
        $this->exigirDonoDasCartas();
        abort_unless(in_array((int) $carta->campanha_id, $this->campanhasOficiais($campanha), true), 404);
        $carta->delete(); // partidas em andamento guardam uma cópia da carta

        return response()->json(['ok' => true]);
    }

    /** Criador de peças (cada membro tem as suas; as do mestre valem para todos). A foto vem já reduzida em JPEG. */
    public function salvarPeca(Request $request, Campanha $campanha): JsonResponse
    {
        $this->exigirMembro($campanha);
        $uid = (int) auth()->id();
        $d = $request->validate([
            'id'      => ['nullable', 'integer'],
            'nome'    => ['required', 'string', 'max:40'],
            'simbolo' => ['required', 'string', 'max:24'],
            'regras'  => ['required', 'string', 'max:20000'],
            'foto'    => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:600'], // KB (a tela já manda 256 px)
            'sem_foto'=> ['nullable', 'boolean'],
        ], ['foto.mimes' => 'A foto precisa ser uma imagem (JPG, PNG ou WebP).', 'foto.max' => 'A foto pode ter no máximo 600 KB.']);
        $regras = PelaDama::limparRegras(json_decode($d['regras'], true));
        if (!$regras) {
            return response()->json(['ok' => false, 'erro' => 'Marque pelo menos um movimento para a peça.'], 422);
        }
        // até 2 símbolos inteiros: um emoji com modificadores (⚔️, 🧙‍♂️) conta como um só e não é cortado no meio
        $simbolo = preg_match('/^\X{1,2}/u', trim($d['simbolo']), $ms) ? $ms[0] : mb_substr(trim($d['simbolo']), 0, 2);
        if (mb_strlen($simbolo) > 8 && preg_match('/^\X/u', $simbolo, $ms)) { // a coluna guarda até 8 caracteres
            $simbolo = mb_substr($ms[0], 0, 8);
        }
        if ($simbolo === '') {
            return response()->json(['ok' => false, 'erro' => 'Escolha um símbolo para a peça.'], 422);
        }

        if (empty($d['id']) && PelaDamaPeca::where('campanha_id', $campanha->id)->where('user_id', $uid)->count() >= self::MAX_PECAS) {
            return response()->json(['ok' => false, 'erro' => 'Você já tem ' . self::MAX_PECAS . ' peças nesta campanha: exclua uma para criar outra.'], 422);
        }
        $peca = !empty($d['id'])
            ? PelaDamaPeca::where('campanha_id', $campanha->id)->where('user_id', $uid)->findOrFail((int) $d['id'])
            : new PelaDamaPeca(['campanha_id' => $campanha->id, 'user_id' => $uid]);
        $peca->fill(['nome' => trim($d['nome']), 'simbolo' => $simbolo, 'regras' => $regras])->save();
        if ($request->hasFile('foto')) {
            Storage::disk('local')->put($peca->fotoCaminho(), (string) file_get_contents($request->file('foto')->getRealPath()));
        } elseif ($request->boolean('sem_foto')) {
            Storage::disk('local')->delete($peca->fotoCaminho());
        }

        return response()->json(['ok' => true, 'peca' => $this->pecaJson($peca, $uid)]);
    }

    /* ---------- Decks de Gladio (cada membro monta os seus com as cartas da campanha) ---------- */

    public function salvarDeck(Request $request, Campanha $campanha): JsonResponse
    {
        $this->exigirMembro($campanha);
        if (!Schema::hasTable('gladio_decks')) {
            return $this->erro('Os decks ainda não existem no banco: rode o SQL da migração 2026_10_28_000013.');
        }
        $uid = (int) auth()->id();
        $d = $request->validate([
            'id'     => ['nullable', 'integer'],
            'nome'   => ['required', 'string', 'max:60'],
            'cartas' => ['required', 'string', 'max:5000'], // JSON { id da carta: quantidade }
            'comandante_id' => ['nullable', 'integer'],
        ]);
        $catalogo = $this->catalogo($campanha);
        $cartas = [];
        foreach ((array) json_decode($d['cartas'], true) as $id => $qtd) {
            $qtd = (int) $qtd;
            if ($qtd > 0 && isset($catalogo[(string) $id])) {
                // banlist do mestre: as cópias além do limite saem (banida: a carta inteira sai) em vez de recusar o deck
                $qtd = min($qtd, GladioDeck::copiasDe($catalogo[(string) $id]));
                if ($qtd > 0) {
                    $cartas[(string) $id] = $qtd;
                }
            }
        }
        $total = array_sum($cartas);
        if ($total < GladioDeck::MIN || $total > GladioDeck::MAX) {
            return $this->erro('O deck precisa ter de ' . GladioDeck::MIN . ' a ' . GladioDeck::MAX . " cartas (tem $total).");
        }

        $deck = !empty($d['id'])
            ? GladioDeck::where('campanha_id', $campanha->id)->where('user_id', $uid)->findOrFail((int) $d['id'])
            : new GladioDeck(['campanha_id' => $campanha->id, 'user_id' => $uid]);
        $deck->fill(['nome' => trim($d['nome']), 'cartas' => $cartas]);
        if (GladioDeck::temComandante()) {
            // comandante: uma Lenda do próprio deck (sem escolha, uma Lenda do deck é sorteada quando a partida começa)
            $cmd = (string) ($d['comandante_id'] ?? '');
            $deck->comandante_id = isset($cartas[$cmd]) && Gladio::podeComandar($catalogo[$cmd] ?? []) ? (int) $cmd : null;
        }
        $deck->save();

        return response()->json(['ok' => true, 'deck' => $deck->paraLista()]);
    }

    public function excluirDeck(Campanha $campanha, GladioDeck $deck): JsonResponse
    {
        $this->exigirMembro($campanha);
        abort_unless((int) $deck->campanha_id === (int) $campanha->id && (int) $deck->user_id === (int) auth()->id(), 404);
        $deck->delete(); // partidas já começadas guardam a própria lista de cartas

        return response()->json(['ok' => true]);
    }

    /* ---------- Galeria de imagens das cartas (o mestre envia; todos os membros veem) ---------- */

    /** Envia uma ou várias imagens (o navegador já reduz e converte para JPEG). */
    public function enviarImagens(Request $request, Campanha $campanha): JsonResponse
    {
        $this->exigirDonoDasCartas();
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

    /* ---------- Playmat do Gladio (imagem que cada jogador escolhe para o próprio campo) ---------- */

    /** Arquivo do playmat do usuário (um por jogador, vale em todas as campanhas). */
    private function playmatCaminho(int $uid): string
    {
        return "playmats/$uid.jpg";
    }

    /** Endereço do playmat do usuário (com a versão para o navegador não usar a antiga) ou null se ele não tem. */
    private function playmatUrl(Campanha $campanha, int $uid): ?string
    {
        $disco = Storage::disk('local');
        $arq = $this->playmatCaminho($uid);

        return $disco->exists($arq) ? route('campanhas.jogos.playmat.ver', [$campanha, $uid]) . '?v=' . $disco->lastModified($arq) : null;
    }

    /** Envia (arquivo playmat) ou tira (remover=1) o próprio playmat. A tela já manda a imagem reduzida em JPEG. */
    public function salvarPlaymat(Request $request, Campanha $campanha): JsonResponse
    {
        $this->exigirMembro($campanha);
        $uid = (int) auth()->id();
        $request->validate([
            'playmat' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remover' => ['nullable', 'boolean'],
        ], ['playmat.mimes' => 'Escolha uma imagem (JPG, PNG ou WebP).', 'playmat.max' => 'A imagem pode ter no máximo 3 MB.']);
        $disco = Storage::disk('local');
        if ($request->boolean('remover')) {
            $disco->delete($this->playmatCaminho($uid));

            return response()->json(['ok' => true, 'playmat' => null]);
        }
        if (!$request->hasFile('playmat')) {
            return $this->erro('Escolha uma imagem para o playmat.');
        }
        $disco->put($this->playmatCaminho($uid), (string) file_get_contents($request->file('playmat')->getRealPath()));

        return response()->json(['ok' => true, 'playmat' => $this->playmatUrl($campanha, $uid)]);
    }

    /** A imagem do playmat de um jogador (para quem é da campanha). */
    public function verPlaymat(Campanha $campanha, int $usuario)
    {
        $this->exigirMembro($campanha);
        $caminho = Storage::disk('local')->path($this->playmatCaminho($usuario));
        abort_unless(is_file($caminho), 404);

        return response()->file($caminho, [
            'Content-Type'           => mime_content_type($caminho) ?: 'image/jpeg',
            'Cache-Control'          => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function excluirImagem(Campanha $campanha, GladioImagem $imagem): JsonResponse
    {
        $this->exigirDonoDasCartas();
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
        Storage::disk('local')->delete($peca->fotoCaminho());
        $peca->delete();

        return response()->json(['ok' => true]);
    }

    /** Foto de uma peça (para quem é da campanha). */
    public function verFotoPeca(Campanha $campanha, PelaDamaPeca $peca)
    {
        $this->exigirMembro($campanha);
        abort_unless((int) $peca->campanha_id === (int) $campanha->id, 404);
        $caminho = Storage::disk('local')->path($peca->fotoCaminho());
        abort_unless(is_file($caminho), 404);

        return response()->file($caminho, [
            'Content-Type'           => mime_content_type($caminho) ?: 'image/jpeg',
            'Cache-Control'          => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
            'deck_id'      => ['nullable', 'integer'],
            'vagas'        => ['nullable', 'integer', 'min:2', 'max:' . Gladio::MAX_JOGADORES], // Gladio: jogadores na mesa
            'ficha_id'     => ['nullable', 'integer'], // Gladio: apostar o Dinheiro de um personagem (vazio: carteira da campanha)
            'equipes'      => ['nullable', 'integer', 'in:0,2,3'], // Gladio: 0 todos contra todos; 2 ou 3 equipes do mesmo tamanho
            'equipe'       => ['nullable', 'integer', 'min:0', 'max:2'], // a equipe de quem cria a mesa
        ]);
        $convidado = !empty($d['convidado_id']) ? (int) $d['convidado_id'] : null;
        if ($convidado !== null && ($convidado === $uid || !$campanha->temMembro($convidado))) {
            return $this->erro('Escolha alguém da campanha para desafiar.');
        }

        $aposta = 0;
        $config = null;
        if ($d['jogo'] === 'gladio') {
            if (!GladioCarta::whereIn('campanha_id', $this->campanhasOficiais($campanha))->exists()) {
                return $this->erro('O mestre ainda não criou nenhuma carta de Gladio.');
            }
            $aposta = (int) ($d['aposta'] ?? 0);
            try {
                $ficha = $this->fichaDaAposta($campanha, $uid, $d['ficha_id'] ?? null);
                if ($aposta > $this->saldoDaAposta($campanha, $uid, $ficha)) {
                    return $this->erro($ficha ? 'Esse personagem não tem Dinheiro suficiente para essa aposta.' : 'Você não tem Dinheiro suficiente para essa aposta.');
                }
                $deck = $this->deckDe($campanha, $uid, $d['deck_id'] ?? null, $this->catalogo($campanha));
            } catch (\InvalidArgumentException $e) {
                return $this->erro($e->getMessage());
            }
            $vagas = (int) ($d['vagas'] ?? 2);
            $equipes = (int) ($d['equipes'] ?? 0);
            if ($equipes && ($vagas % $equipes !== 0 || intdiv($vagas, $equipes) < 2)) {
                return $this->erro('Em equipes, o número de jogadores precisa dividir em equipes iguais de pelo menos 2: 4 ou 6 jogadores para 2 equipes, 6 para 3.');
            }
            if ($vagas > 2) {
                $convidado = null; // mesa com várias vagas fica aberta a qualquer um da campanha
            }
            // quem entra na mesa, na ordem da roda, e o deck de cada um (null: 20 sorteadas)
            $config = ['vagas' => $vagas, 'jogadores' => [$uid], 'decks' => [(string) $uid => $deck],
                       'comandantes' => [(string) $uid => $this->comandanteDe($campanha, $uid, $d['deck_id'] ?? null)],
                       'fichas' => [(string) $uid => $ficha]]
                + ($equipes ? ['equipes' => $equipes, 'equipe' => [(string) $uid => min($equipes - 1, (int) ($d['equipe'] ?? 0))]] : []);
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

    /**
     * Aceitar um desafio. No Gladio é entrar na mesa: a partida começa sozinha quando as vagas enchem (ou quando quem
     * criou manda começar). Pela Dama começa na hora.
     */
    public function aceitar(Request $request, Campanha $campanha, JogoPartida $partida): JsonResponse
    {
        $this->exigirMembro($campanha);
        $this->daCampanha($campanha, $partida);
        $uid = (int) auth()->id();
        $request->validate(['exercito' => ['nullable', 'string', 'max:2000'], 'deck_id' => ['nullable', 'integer']]);
        $comecou = true;

        try {
            DB::transaction(function () use ($request, $campanha, $partida, $uid, &$comecou) {
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
                    $cfg = $this->configMesa($p);
                    if (in_array($uid, $cfg['jogadores'], true)) {
                        throw new \InvalidArgumentException('Você já está nessa mesa.');
                    }
                    if (count($cfg['jogadores']) >= $cfg['vagas']) {
                        throw new \InvalidArgumentException('Essa mesa já está cheia.');
                    }
                    $ficha = $this->fichaDaAposta($campanha, $uid, $request->input('ficha_id'));
                    if ($p->aposta > $this->saldoDaAposta($campanha, $uid, $ficha)) {
                        throw new \InvalidArgumentException($ficha ? 'Esse personagem não tem Dinheiro suficiente para essa aposta.' : 'Você não tem Dinheiro suficiente para essa aposta.');
                    }
                    if ($cfg['equipes']) { // em equipes: entra na escolhida, se ainda houver vaga nela
                        $eq = (int) $request->input('equipe', -1);
                        if ($eq < 0 || $eq >= $cfg['equipes']) {
                            throw new \InvalidArgumentException('Escolha em qual equipe entrar.');
                        }
                        if (count(array_keys($cfg['equipe'], $eq, true)) >= intdiv($cfg['vagas'], $cfg['equipes'])) {
                            throw new \InvalidArgumentException('Essa equipe já está completa.');
                        }
                        $cfg['equipe'][(string) $uid] = $eq;
                    }
                    $cfg['jogadores'][] = $uid;
                    $cfg['fichas'][(string) $uid] = $ficha;
                    $cfg['decks'][(string) $uid] = $this->deckDe($campanha, $uid, $request->input('deck_id'), $this->catalogo($campanha));
                    $cfg['comandantes'][(string) $uid] = $this->comandanteDe($campanha, $uid, $request->input('deck_id'));
                    $p->config = $cfg;
                    $comecou = count($cfg['jogadores']) >= $cfg['vagas'];
                    if ($comecou) {
                        $this->comecarMesa($campanha, $p);
                    } else {
                        $p->versao = (int) $p->versao + 1;
                        $p->save();
                    }

                    return;
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

        return response()->json(['ok' => true, 'comecou' => $comecou, 'url' => route('campanhas.jogos.partida', [$campanha, $partida])]
            + ($comecou ? [] : $this->dadosLobby($campanha)));
    }

    /** Chat da partida: jogadores e quem assiste escrevem; a tela mostra a caixa e um balão sobre o assento de quem jogou. */
    public function chat(Request $request, Campanha $campanha, JogoPartida $partida): JsonResponse
    {
        $this->exigirMembro($campanha);
        $this->daCampanha($campanha, $partida);
        $uid = (int) auth()->id();
        $texto = trim((string) $request->validate(['texto' => ['required', 'string', 'max:200']])['texto']);
        if ($texto === '') {
            return $this->erro('Escreva alguma coisa.');
        }
        $p = DB::transaction(function () use ($partida, $uid, $texto) {
            $p = JogoPartida::lockForUpdate()->findOrFail($partida->id);
            $cfg = (array) ($p->config ?? []);
            $chat = (array) ($cfg['chat'] ?? []);
            $n = (int) ($cfg['chat_n'] ?? 0) + 1;
            $chat[] = ['n' => $n, 'u' => $uid, 'nome' => (string) (User::whereKey($uid)->value('name') ?? 'Alguém'),
                       'lado' => $p->ladoDe($uid), 'texto' => $texto, 't' => time()];
            $cfg['chat'] = array_slice($chat, -60);
            $cfg['chat_n'] = $n;
            $p->config = $cfg;
            $p->versao = (int) $p->versao + 1;
            $p->save();

            return $p;
        });

        return response()->json($this->dadosPartida($p));
    }

    /**
     * Bolão de quem assiste: aposta Dinheiro da carteira da campanha num jogador (ou equipe) até o fim do turno 3.
     * Quem acertar divide o bolão na proporção do que apostou; sem acertos, ou partida cancelada, todos recebem de volta.
     */
    public function apostarBolao(Request $request, Campanha $campanha, JogoPartida $partida): JsonResponse
    {
        $this->exigirMembro($campanha);
        $this->daCampanha($campanha, $partida);
        $uid = (int) auth()->id();
        $d = $request->validate(['alvo' => ['required', 'string', 'max:2'], 'valor' => ['required', 'integer', 'min:1', 'max:1000000000']]);
        try {
            $p = DB::transaction(function () use ($campanha, $partida, $uid, $d) {
                $p = JogoPartida::lockForUpdate()->findOrFail($partida->id);
                $e = $p->estado;
                if ($p->jogo !== 'gladio' || $p->status !== 'andamento' || !$e || !empty($e['fim'])) {
                    throw new \InvalidArgumentException('O bolão só vale com a partida em andamento.');
                }
                if ($p->ladoDe($uid)) {
                    throw new \InvalidArgumentException('Quem está jogando não aposta no bolão.');
                }
                if ((int) ($e['turno'] ?? 1) > self::BOLAO_ATE_TURNO) {
                    throw new \InvalidArgumentException('O bolão fechou: as apostas valem até o turno ' . self::BOLAO_ATE_TURNO . '.');
                }
                $alvos = $this->alvosDoBolao($p);
                if (!isset($alvos[$d['alvo']])) {
                    throw new \InvalidArgumentException('Escolha em quem apostar.');
                }
                $c = $this->carteira($campanha, $uid, true);
                if ($c->dinheiro < (int) $d['valor']) {
                    throw new \InvalidArgumentException('Você não tem Dinheiro suficiente na carteira da campanha.');
                }
                $c->dinheiro -= (int) $d['valor'];
                $c->save();
                $cfg = (array) ($p->config ?? []);
                $cfg['bolao'] = (array) ($cfg['bolao'] ?? []);
                $k = collect($cfg['bolao'])->search(fn ($a) => (int) $a['u'] === $uid && $a['alvo'] === $d['alvo']);
                if ($k !== false) {
                    $cfg['bolao'][$k]['v'] = (int) $cfg['bolao'][$k]['v'] + (int) $d['valor'];
                } else {
                    $cfg['bolao'][] = ['u' => $uid, 'alvo' => $d['alvo'], 'v' => (int) $d['valor']];
                }
                $p->config = $cfg;
                $p->versao = (int) $p->versao + 1;
                $p->save();

                return $p;
            });
        } catch (\InvalidArgumentException $x) {
            return $this->erro($x->getMessage());
        }

        return response()->json($this->dadosPartida($p));
    }

    /** Em quem dá para apostar: os lados (todos contra todos) ou as equipes. alvo => nome. */
    private function alvosDoBolao(JogoPartida $p): array
    {
        $e = $p->estado ?? [];
        if (!empty($e['equipes'])) {
            $r = [];
            foreach (array_unique(array_values($e['equipes'])) as $letra) {
                $r[$letra] = 'Equipe ' . $letra;
            }

            return $r;
        }
        $r = [];
        foreach ($p->jogadores() as $k => $id) {
            $r[Gladio::LADOS[$k]] = $this->nomeJogador($p, (int) $id);
        }

        return $r;
    }

    /** Paga o bolão (fim da partida) ou devolve tudo (cancelada, ou ninguém acertou). Uma vez só. */
    private function acertarBolao(Campanha $campanha, JogoPartida $p, ?string $vencedor): void
    {
        $cfg = (array) ($p->config ?? []);
        $apostas = (array) ($cfg['bolao'] ?? []);
        if (!$apostas || !empty($cfg['bolao_pago'])) {
            return;
        }
        $pote = array_sum(array_column($apostas, 'v'));
        $certas = $vencedor === null ? [] : array_values(array_filter($apostas, fn ($a) => $a['alvo'] === $vencedor));
        $somaCertas = array_sum(array_column($certas, 'v'));
        $pagar = function (int $uid, int $valor) use ($campanha) {
            $c = $this->carteira($campanha, $uid, true);
            $c->dinheiro += $valor;
            $c->save();
        };
        if (!$certas) {
            foreach ($apostas as $a) {
                $pagar((int) $a['u'], (int) $a['v']); // ninguém acertou ou cancelou: cada um recebe de volta
            }
        } else {
            $pago = 0;
            foreach ($certas as $a) {
                $parte = intdiv($pote * (int) $a['v'], $somaCertas);
                $pago += $parte;
                $pagar((int) $a['u'], $parte);
            }
            if ($pote > $pago) {
                $pagar((int) $certas[0]['u'], $pote - $pago); // o que sobra da divisão
            }
        }
        $cfg['bolao_pago'] = true;
        $p->config = $cfg;
    }

    /** Gladio em equipes: quem já está na mesa muda de equipe enquanto ela espera começar (se houver vaga na outra). */
    public function trocarEquipe(Request $request, Campanha $campanha, JogoPartida $partida): JsonResponse
    {
        $this->exigirMembro($campanha);
        $this->daCampanha($campanha, $partida);
        $uid = (int) auth()->id();
        $eq = (int) $request->validate(['equipe' => ['required', 'integer', 'min:0', 'max:2']])['equipe'];
        try {
            DB::transaction(function () use ($partida, $uid, $eq) {
                $p = JogoPartida::lockForUpdate()->findOrFail($partida->id);
                $cfg = $p->jogo === 'gladio' ? $this->configMesa($p) : null;
                if (!$cfg || $p->status !== 'aberta' || !$cfg['equipes']) {
                    throw new \InvalidArgumentException('Essa mesa não está esperando jogadores em equipes.');
                }
                if (!in_array($uid, $cfg['jogadores'], true)) {
                    throw new \InvalidArgumentException('Você não está nessa mesa.');
                }
                if ($eq >= $cfg['equipes']) {
                    throw new \InvalidArgumentException('Essa equipe não existe nesta mesa.');
                }
                if (($cfg['equipe'][(string) $uid] ?? null) === $eq) {
                    return;
                }
                if (count(array_keys($cfg['equipe'], $eq, true)) >= intdiv($cfg['vagas'], $cfg['equipes'])) {
                    throw new \InvalidArgumentException('Essa equipe já está completa.');
                }
                $cfg['equipe'][(string) $uid] = $eq;
                $p->config = $cfg;
                $p->versao = (int) $p->versao + 1;
                $p->save();
            });
        } catch (\InvalidArgumentException $e) {
            return $this->erro($e->getMessage());
        }

        return response()->json(['ok' => true] + $this->dadosLobby($campanha));
    }

    /** Gladio: quem criou a mesa começa a partida antes de encher as vagas (precisa de pelo menos 2 jogadores). */
    public function comecar(Campanha $campanha, JogoPartida $partida): JsonResponse
    {
        $this->exigirMembro($campanha);
        $this->daCampanha($campanha, $partida);
        $uid = (int) auth()->id();
        try {
            DB::transaction(function () use ($campanha, $partida, $uid) {
                $p = JogoPartida::lockForUpdate()->findOrFail($partida->id);
                if ($p->jogo !== 'gladio' || $p->status !== 'aberta') {
                    throw new \InvalidArgumentException('Essa mesa não está esperando jogadores.');
                }
                if ((int) $p->jogador1_id !== $uid) {
                    throw new \InvalidArgumentException('Só quem criou a mesa pode começar a partida.');
                }
                $cfgM = $this->configMesa($p);
                if (count($cfgM['jogadores']) < 2) {
                    throw new \InvalidArgumentException('Espere pelo menos mais um jogador entrar.');
                }
                if ($cfgM['equipes'] && count(array_unique($cfgM['equipe'])) < $cfgM['equipes']) {
                    throw new \InvalidArgumentException('Cada equipe precisa de pelo menos um jogador para começar.');
                }
                $this->comecarMesa($campanha, $p);
            });
        } catch (\InvalidArgumentException $e) {
            return $this->erro($e->getMessage());
        }

        return response()->json(['ok' => true, 'url' => route('campanhas.jogos.partida', [$campanha, $partida])]);
    }

    /**
     * Gladio contra o bot: a partida começa na hora, sem aposta, com o seu deck (ou 20 sorteadas) contra um deck
     * sorteado entre todos os decks já montados por qualquer jogador (só os que ainda têm cartas suficientes no
     * catálogo; sem nenhum, o bot joga com 20 sorteadas). Quem começa é sorteado.
     */
    public function contraBot(Request $request, Campanha $campanha): JsonResponse
    {
        $this->exigirMembro($campanha);
        $uid = (int) auth()->id();
        $request->validate(['deck_id' => ['nullable', 'integer']]);
        if (!GladioCarta::whereIn('campanha_id', $this->campanhasOficiais($campanha))->exists()) {
            return $this->erro('O mestre ainda não criou nenhuma carta de Gladio.');
        }
        $catalogo = $this->catalogo($campanha);
        try {
            $meu = $this->deckDe($campanha, $uid, $request->input('deck_id'), $catalogo);
        } catch (\InvalidArgumentException $e) {
            return $this->erro($e->getMessage());
        }
        [$deckBot, $nomeDeck, $cmdBot] = $this->deckDoBot($catalogo);
        $jogadores = random_int(0, 1) ? [$uid, GladioBot::ID] : [GladioBot::ID, $uid];
        $cfg = ['vagas' => 2, 'jogadores' => $jogadores,
                'decks' => [(string) $uid => $meu, (string) GladioBot::ID => $deckBot],
                'comandantes' => [(string) $uid => $this->comandanteDe($campanha, $uid, $request->input('deck_id')), (string) GladioBot::ID => $cmdBot],
                'fichas' => [], 'bot' => ['nome' => 'Bot' . ($nomeDeck ? " ($nomeDeck)" : ''), 'deck' => $nomeDeck]];

        $p = DB::transaction(function () use ($campanha, $uid, $cfg) {
            $p = JogoPartida::create(['campanha_id' => $campanha->id, 'jogo' => 'gladio', 'jogador1_id' => $uid,
                                      'convidado_id' => null, 'aposta' => 0, 'status' => 'aberta', 'config' => $cfg]);
            $this->comecarMesa($campanha, $p);
            $p->estado = GladioBot::agir($p->estado, $this->ladoDoBot($p)); // o bot já mantém a mão
            $p->save();

            return $p;
        });

        return response()->json(['ok' => true, 'url' => route('campanhas.jogos.partida', [$campanha, $p])]);
    }

    /** Deck sorteado para o bot entre os decks de todos os jogadores: [lista de ids | null, nome, comandante]. */
    private function deckDoBot(array $catalogo): array
    {
        if (!Schema::hasTable('gladio_decks')) {
            return [null, null, null];
        }
        $ids = GladioDeck::query()->pluck('id')->shuffle();
        foreach ($ids->take(40) as $id) { // os que o mestre esvaziou (cartas apagadas/banidas) ficam de fora
            $deck = GladioDeck::find($id);
            $lista = $deck ? $deck->lista($catalogo) : [];
            if (count($lista) >= GladioDeck::MIN) {
                $cmd = GladioDeck::temComandante() && $deck->comandante_id ? (string) $deck->comandante_id : null;

                return [$lista, (string) $deck->nome, $cmd];
            }
        }

        return [null, null, null];
    }

    /** Lado do bot na partida (null: partida só de pessoas). */
    private function ladoDoBot(JogoPartida $p): ?string
    {
        if (empty(($p->config ?? [])['bot'])) {
            return null;
        }
        $k = array_search(GladioBot::ID, $p->jogadores(), true);

        return $k === false ? null : Gladio::LADOS[$k];
    }

    /** Nome de quem joga na mesa: o da conta ou, para o bot, o nome dele com o deck sorteado. */
    private function nomeJogador(JogoPartida $p, int $id): string
    {
        if ($id === GladioBot::ID && !empty(($p->config ?? [])['bot'])) {
            return (string) $p->config['bot']['nome'];
        }

        return (string) (User::whereKey($id)->value('name') ?? 'Jogador');
    }

    /** Config da mesa de Gladio, completando desafios criados antes das vagas (2 jogadores, deck em config.deck). */
    private function configMesa(JogoPartida $p): array
    {
        $cfg = (array) ($p->config ?? []);
        $cfg['vagas'] = max(2, min(Gladio::MAX_JOGADORES, (int) ($cfg['vagas'] ?? 2)));
        $cfg['jogadores'] = array_map('intval', (array) ($cfg['jogadores'] ?? [(int) $p->jogador1_id]));
        $cfg['decks'] = (array) ($cfg['decks'] ?? [(string) $p->jogador1_id => $cfg['deck'] ?? null]);
        $cfg['comandantes'] = (array) ($cfg['comandantes'] ?? []);
        $cfg['fichas'] = (array) ($cfg['fichas'] ?? []); // personagem de cada um cuja aposta sai do Dinheiro dele
        $cfg['ocultos'] = array_map('intval', (array) ($cfg['ocultos'] ?? []));
        $cfg['equipes'] = (int) ($cfg['equipes'] ?? 0); // 0: todos contra todos
        $cfg['equipe'] = array_map('intval', (array) ($cfg['equipe'] ?? [])); // uid => índice da equipe
        unset($cfg['deck']);

        return $cfg;
    }

    /**
     * Começa a mesa de Gladio (dentro da transação, com a partida travada): a aposta sai da carteira de cada jogador e
     * o motor monta a roda na ordem em que entraram.
     */
    private function comecarMesa(Campanha $campanha, JogoPartida $p): void
    {
        $cfg = $this->configMesa($p);
        $cartas = $this->catalogo($campanha);
        if ($cfg['equipes']) { // na roda, as equipes se alternam (A1, B1, A2, B2…)
            $porEquipe = [];
            foreach ($cfg['jogadores'] as $quem) {
                $porEquipe[$cfg['equipe'][(string) $quem] ?? 0][] = $quem;
            }
            ksort($porEquipe);
            $roda = [];
            for ($k = 0; $k < max(array_map('count', $porEquipe)); $k++) {
                foreach ($porEquipe as $membros) {
                    if (isset($membros[$k])) {
                        $roda[] = $membros[$k];
                    }
                }
            }
            $cfg['jogadores'] = $roda;
        }
        $p->config = $cfg;
        if ($p->aposta > 0) { // da ficha vinculada ou da carteira da campanha de cada um
            foreach ($cfg['jogadores'] as $quem) {
                $this->moverAposta($campanha, $p, (int) $quem, -(int) $p->aposta, true);
            }
        }
        $decks = [];
        $nomes = [];
        $comandantes = [];
        foreach ($cfg['jogadores'] as $k => $quem) {
            $lado = Gladio::LADOS[$k];
            // cartas do deck que o mestre apagou nesse meio-tempo ficam de fora
            $deck = array_values(array_filter((array) ($cfg['decks'][(string) $quem] ?? []), fn ($id) => isset($cartas[(string) $id])));
            // banlist: o que o mestre baniu ou limitou depois de o deck ser escolhido também vale
            $conta = [];
            $deck = array_values(array_filter($deck, function ($id) use (&$conta, $cartas) {
                $conta[(string) $id] = ($conta[(string) $id] ?? 0) + 1;

                return $conta[(string) $id] <= GladioDeck::copiasDe($cartas[(string) $id]);
            }));
            $decks[$lado] = $deck ?: null;
            $comandantes[$lado] = $cfg['comandantes'][(string) $quem] ?? null;
            $nomes[$lado] = $this->nomeJogador($p, (int) $quem);
            if ($f = ($cfg['fichas'][(string) $quem] ?? null)) { // partida vinculada a um personagem: aparece o nome dele
                $nomes[$lado] = (string) (Personagem::withoutGlobalScopes()->whereKey($f)->value('nome') ?? '') . ' (' . $nomes[$lado] . ')';
            }
        }
        $p->config = $cfg;
        $p->jogador2_id = ($cfg['jogadores'][1] ?? null) ?: null; // o bot (id 0) não é uma conta
        // antes do primeiro turno, cada um decide se mantém a mão inicial ou faz mulligan
        $equipesLado = [];
        foreach ($cfg['equipes'] ? $cfg['jogadores'] : [] as $k => $quem) {
            $equipesLado[Gladio::LADOS[$k]] = self::LETRAS[$cfg['equipe'][(string) $quem] ?? 0];
        }
        $p->estado = Gladio::abrirMulligan(Gladio::iniciar($cartas, (int) $p->aposta, $decks, null, $nomes, array_filter($comandantes), $equipesLado));
        $p->status = 'andamento';
        $p->versao = (int) $p->versao + 1;
        $p->save();
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
                    // quem só entrou na mesa (não criou) sai dela; a mesa continua aberta
                    if ($p->jogo === 'gladio' && (int) $p->jogador1_id !== $uid && in_array($uid, $this->configMesa($p)['jogadores'], true)) {
                        $cfg = $this->configMesa($p);
                        $cfg['jogadores'] = array_values(array_diff($cfg['jogadores'], [$uid]));
                        unset($cfg['decks'][(string) $uid], $cfg['comandantes'][(string) $uid], $cfg['equipe'][(string) $uid]);
                        $p->config = $cfg;
                        $p->versao = (int) $p->versao + 1;
                        $p->save();

                        return;
                    }
                    if (!$mestre && (int) $p->jogador1_id !== $uid) {
                        throw new \InvalidArgumentException('Só quem criou o desafio ou o mestre pode cancelar.');
                    }
                } elseif ($p->status === 'andamento') {
                    if (!$mestre) {
                        throw new \InvalidArgumentException('Só o mestre pode cancelar uma partida em andamento.');
                    }
                    if ($p->jogo === 'gladio' && $p->aposta > 0) {
                        foreach ($p->jogadores() as $quem) {
                            $this->moverAposta($campanha, $p, (int) $quem, (int) $p->aposta);
                        }
                    }
                    if ($p->jogo === 'gladio') {
                        $this->acertarBolao($campanha, $p, null); // cancelada: o bolão volta para quem apostou
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

    /**
     * Tira uma partida terminada (ou cancelada) do histórico: o mestre apaga de vez; quem jogou só a esconde para si.
     */
    public function excluirPartida(Campanha $campanha, JogoPartida $partida): JsonResponse
    {
        $this->exigirMembro($campanha);
        $this->daCampanha($campanha, $partida);
        if (!in_array($partida->status, ['fim', 'cancelada'], true)) {
            return $this->erro('Só dá para apagar partidas que já terminaram ou foram canceladas.');
        }
        $uid = (int) auth()->id();
        if ($campanha->ehMestre($uid)) {
            $partida->delete();
        } else {
            $cfg = (array) ($partida->config ?? []);
            $cfg['ocultos'] = array_values(array_unique(array_merge(array_map('intval', (array) ($cfg['ocultos'] ?? [])), [$uid])));
            $partida->config = $cfg;
            $partida->save();
        }

        return response()->json(['ok' => true] + $this->dadosLobby($campanha));
    }

    /** Limpa de uma vez as partidas terminadas ou canceladas de um jogo: o mestre apaga para todos; o jogador tira do próprio histórico. */
    public function limparPartidas(Request $request, Campanha $campanha): JsonResponse
    {
        $this->exigirMembro($campanha);
        $d = $request->validate(['jogo' => ['required', Rule::in(self::JOGOS)]]);
        $uid = (int) auth()->id();
        $mestre = $campanha->ehMestre($uid);
        $q = JogoPartida::where('campanha_id', $campanha->id)->where('jogo', $d['jogo'])->whereIn('status', ['fim', 'cancelada']);
        if ($mestre) {
            $q->delete();
        } else {
            foreach ($q->get() as $p) {
                $cfg = (array) ($p->config ?? []);
                $cfg['ocultos'] = array_values(array_unique(array_merge(array_map('intval', (array) ($cfg['ocultos'] ?? [])), [$uid])));
                $p->config = $cfg;
                $p->save();
            }
        }

        return response()->json(['ok' => true] + $this->dadosLobby($campanha));
    }

    /**
     * Miniatura do Gladio (todas as páginas do site): as partidas em andamento em que o usuário joga, com o resumo
     * de quem a mesa espera, para ele acompanhar e abrir a mesa sem sair do que está fazendo.
     */
    public function ativas(): JsonResponse
    {
        $uid = (int) auth()->id();
        $lista = [];
        foreach (JogoPartida::where('jogo', 'gladio')->where('status', 'andamento')->latest('updated_at')->limit(50)->get() as $p) {
            $lado = $p->ladoDe($uid);
            $e = $p->estado;
            if (!$lado || !$e || !empty($e['lados'][$lado]['fora'])) {
                continue;
            }
            $e = Gladio::completar($e);
            $nomes = [];
            foreach ($p->jogadores() as $k => $id) {
                $nomes[Gladio::LADOS[$k]] = $this->nomeJogador($p, (int) $id);
            }
            $espera = Gladio::esperando($e);
            $campanha = Campanha::find($p->campanha_id);
            $lista[] = [
                'id'       => (int) $p->id,
                'v'        => (int) $p->versao,
                'campanha' => (string) ($campanha->nome ?? ''),
                'url'      => route('campanhas.jogos.partida', [$p->campanha_id, $p->id]),
                'minha'    => $espera === $lado && empty($e['pausa']) && ($e['fase'] ?? '') !== 'mulligan',
                'espera'   => $nomes[$espera] ?? '',
                'pausa'    => !empty($e['pausa']),
                'mulligan' => ($e['fase'] ?? '') === 'mulligan',
                'turno'    => (int) ($e['turno'] ?? 1),
                'vida'     => (int) ($e['lados'][$lado]['vida'] ?? 0),
                'mao'      => count($e['lados'][$lado]['mao'] ?? []),
            ];
        }
        usort($lista, fn ($a, $b) => $b['minha'] <=> $a['minha']); // primeiro a que espera por ele

        return response()->json(['partidas' => $lista])->header('Cache-Control', 'no-store');
    }

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
            'insts' => ['nullable', 'array', 'max:20'], 'insts.*' => ['integer'],
            'ordem' => ['nullable', 'array', 'max:200'], 'ordem.*' => ['integer'],
            'outro' => ['nullable', 'integer'],             // atacante bloqueado / Lenda modelo da cópia
            'nome' => ['nullable', 'string', 'max:40'],
            'atk' => ['nullable', 'integer', 'min:-99', 'max:9999'],
            'vida' => ['nullable', 'integer', 'min:-99', 'max:9999'],
            'gratis' => ['nullable', 'boolean'],
            'efeito' => ['nullable', Rule::in(Gladio::EFEITOS)],          // feitiço: efeito escolhido por quem joga
            'valor' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'quanto' => ['nullable', 'integer', 'min:-9999', 'max:9999'], // shots / vida
            'cont' => ['nullable', 'integer', 'min:-99', 'max:99'],       // contador de uma carta
            'nota' => ['nullable', 'string', 'max:60'],                   // anotação de uma carta
            'palavra' => ['nullable', Rule::in(array_keys(Gladio::PALAVRAS))], // palavra-chave dada/tirada de uma Lenda
            'origem' => ['nullable', Rule::in(['mao', 'cemiterio', 'comando'])], // de onde a carta é jogada
            'qual' => ['nullable', 'string', 'max:20'],                     // qual habilidade da carta
            'energia' => ['nullable', 'integer', 'min:-99', 'max:99'], 'energia_max' => ['nullable', 'integer', 'min:-99', 'max:99'],
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
                $e = $p->estado;
                $acao = $request->input('acao');
                if ($p->jogo === 'gladio' && in_array($acao, ['pausar', 'retomar'], true)) { // só o mestre, jogando ou não
                    if (!$campanha->ehMestre($uid)) {
                        throw new \InvalidArgumentException('Só o mestre pode pausar a partida.');
                    }
                    $p->estado = Gladio::pausar(Gladio::completar($e), $acao === 'pausar');
                    $p->versao = (int) $p->versao + 1;
                    $p->save();

                    return $p;
                }
                if (!$lado) {
                    throw new \InvalidArgumentException('Você está só assistindo esta partida.');
                }
                if ($p->jogo === 'gladio' && !empty($e['pausa'])) {
                    throw new \InvalidArgumentException('A partida está pausada pelo mestre.');
                }

                if ($p->jogo === 'gladio') {
                    $e = Gladio::completar($e);
                    $inst = (int) $request->input('inst', 0);
                    $outro = $request->filled('outro') ? (int) $request->input('outro') : null;
                    // Só as ações que são regra do jogo. Tudo o que muda cartas, vida, energia, mão ou baralho acontece
                    // pelo efeito das cartas: não há mais ferramentas manuais (invocar sem pagar, ajustar vida/energia,
                    // comprar, buscar, sacrificar, shots à mão…), para ninguém quebrar a partida.
                    $manuais = ['shot', 'encerrar', 'comprar', 'embaralhar', 'reordenar', 'descartar', 'recuperar', 'ficha', 'ajustar',
                                'palavra', 'copiar', 'sacrificar', 'invocar', 'buscar', 'comando', 'energia', 'vida', 'ativar', 'equipar'];
                    if (in_array($acao, $manuais, true)) {
                        throw new \InvalidArgumentException('Isso só acontece pelo efeito das cartas.');
                    }
                    $e = match ($acao) {
                        // sempre pagando energia; do cemitério só formas hexomantes com Cristian Fernandez em campo
                        'jogar'      => Gladio::jogar($e, $lado, $inst, $request->input('alvo'), false, null, 0, $request->input('origem') === 'cemiterio' ? 'cemiterio' : 'mao'),
                        // habilidade escrita numa carta sua (Josael, Silhueta, Flavinho, Aporium…), pagando o custo dela
                        'habilidade' => Gladio::habilidade($e, $lado, $inst, (string) $request->input('qual', '')),
                        'atacar'     => Gladio::atacar($e, $lado, (array) $request->input('insts', []), $request->input('alvo') ?: null),
                        'revelar'    => Gladio::revelar($e, $lado, $inst),
                        // anotação de texto numa carta (não muda nada no jogo)
                        'marcar'     => Gladio::marcar($e, $lado, $inst, 0,
                            $request->has('nota') ? (string) $request->input('nota', '') : null),
                        'bloquear'   => Gladio::bloquear($e, $lado, $inst, $outro),
                        'defender'   => Gladio::defender($e, $lado),
                        'passar'     => Gladio::passar($e, $lado),
                        'desistir'   => Gladio::desistir($e, $lado),
                        'comandar'   => Gladio::comandar($e, $lado), // invocar o comandante pagando o custo + taxa
                        // resposta a uma escolha pedida por um efeito: alvo = instância ou 'j:<lado>'; vazio pula
                        'escolher'   => Gladio::escolher($e, $lado, $request->filled('alvo') ? (string) $request->input('alvo') : null),
                        'mulligan'   => Gladio::mulligan($e, $lado), // troca a mão inicial
                        'manter'     => Gladio::manter($e, $lado, (array) $request->input('insts', [])), // insts: cartas para o fundo
                        'tempo'      => Gladio::tempo($e, $lado), // acabou o tempo da vez: a mesa segue sozinha
                        default      => throw new \InvalidArgumentException('Ação desconhecida.'),
                    };
                    $e = Gladio::conferirVitorias($e); // Vassago e afins valem assim que a condição se cumpre, em qualquer ação
                    if ($bot = $this->ladoDoBot($p)) { // contra o bot: ele joga a vez dele logo em seguida
                        $e = GladioBot::agir($e, $bot);
                    }
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
                    if ($p->jogo === 'gladio') {
                        // o vencedor leva o pote inteiro: a aposta de todos os jogadores da mesa
                        $k = $venceu === null ? false : array_search($venceu, Gladio::LADOS, true);
                        $vencedorId = $k === false ? null : ($p->jogadores()[$k] ?? null);
                        $time = $e['fim']['equipe'] ?? null;
                        $ganham = $time === null ? array_filter([$vencedorId]) : array_values(array_filter($p->jogadores(),
                            fn ($id, $k) => ($e['equipes'][Gladio::LADOS[$k]] ?? null) === $time, ARRAY_FILTER_USE_BOTH));
                        $this->acertarBolao($campanha, $p, $time ?? $venceu); // bolão de quem assistiu
                        if ($p->aposta > 0 && $ganham) { // para a ficha que cada um vinculou ou para a carteira
                            $pote = (int) $p->aposta * count($p->jogadores());
                            foreach ($ganham as $n => $id) { // em equipe, divide igual (o que sobra da divisão vai para o primeiro)
                                $parte = intdiv($pote, count($ganham)) + ($n === 0 ? $pote % count($ganham) : 0);
                                $this->moverAposta($campanha, $p, (int) $id, $parte);
                            }
                        }
                    } else {
                        // Pela Dama: brancas ('b') é quem criou o desafio; empate não tem vencedor
                        $vencedorId = $venceu === null ? null : ($venceu === 'b' ? $p->jogador1_id : $p->jogador2_id);
                    }
                    $p->vencedor_id = $vencedorId ?: null; // vitória do bot: sem conta vencedora
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

    /** A conta dona do Gladio (config gladio.dono_cartas): só ela cria e edita cartas, a banlist e a galeria. */
    private function donoDasCartas(): bool
    {
        $email = mb_strtolower(trim((string) config('gladio.dono_cartas')));

        return $email !== '' && mb_strtolower((string) auth()->user()?->email) === $email;
    }

    private function exigirDonoDasCartas(): void
    {
        abort_unless($this->donoDasCartas(), 403, 'Só a conta oficial do Gladio cria e edita cartas.');
    }

    /**
     * Campanhas cujas cartas formam o catálogo oficial (as que a conta dona mestra). O catálogo vale para todas as
     * campanhas; sem conta dona cadastrada, cada campanha usa as próprias cartas.
     */
    private function campanhasOficiais(Campanha $campanha): array
    {
        if ($this->oficiais === null) {
            $dono = User::where('email', (string) config('gladio.dono_cartas'))->value('id');
            $this->oficiais = $dono ? Campanha::where('mestre_id', $dono)->orderBy('id')->pluck('id')->map(fn ($i) => (int) $i)->all() : [];
        }

        return $this->oficiais ?: [(int) $campanha->id];
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

    /** As fichas de $uid vinculadas à campanha que podem apostar o Dinheiro delas: [{ id, nome, dinheiro }]. */
    private function fichasApostaveis(Campanha $campanha, int $uid): array
    {
        if (!Schema::hasColumn('fichas', 'dinheiro')) {
            return [];
        }
        $ids = CampanhaFicha::where('campanha_id', $campanha->id)->pluck('ficha_id');

        return Personagem::withoutGlobalScopes()->whereIn('id', $ids)->where('usuario_id', $uid)->orderBy('nome')
            ->get(['id', 'nome', 'dinheiro'])->map(fn ($f) => ['id' => $f->id, 'nome' => (string) $f->nome, 'dinheiro' => (int) $f->dinheiro])->all();
    }

    /** Ficha escolhida para apostar (do próprio jogador e vinculada à campanha) ou null para a carteira da campanha. */
    private function fichaDaAposta(Campanha $campanha, int $uid, mixed $fichaId): ?int
    {
        if ($fichaId === null || $fichaId === '' || (int) $fichaId === 0) {
            return null;
        }
        foreach ($this->fichasApostaveis($campanha, $uid) as $f) {
            if ($f['id'] === (int) $fichaId) {
                return $f['id'];
            }
        }
        throw new \InvalidArgumentException('Escolha um personagem seu vinculado a esta campanha para apostar.');
    }

    /** Dinheiro disponível para apostar: o da ficha escolhida ou o da carteira da campanha. */
    private function saldoDaAposta(Campanha $campanha, int $uid, ?int $ficha): int
    {
        return $ficha === null ? $this->saldo($campanha, $uid)
            : (int) (Personagem::withoutGlobalScopes()->whereKey($ficha)->value('dinheiro') ?? 0);
    }

    /** Põe (+) ou tira (−) Dinheiro de quem apostou: da ficha vinculada à partida ou da carteira da campanha. */
    private function moverAposta(Campanha $campanha, JogoPartida $p, int $uid, int $quanto, bool $exigir = false): void
    {
        $ficha = (($p->config ?? [])['fichas'] ?? [])[(string) $uid] ?? null;
        if ($ficha !== null && Schema::hasColumn('fichas', 'dinheiro')
            && ($f = Personagem::withoutGlobalScopes()->lockForUpdate()->find((int) $ficha))) {
            if ($exigir && (int) $f->dinheiro + $quanto < 0) {
                throw new \InvalidArgumentException("{$f->nome} não tem mais Dinheiro suficiente para a aposta.");
            }
            $f->forceFill(['dinheiro' => max(0, (int) $f->dinheiro + $quanto)])->save();

            return;
        }
        $c = $this->carteira($campanha, $uid, true);
        if ($exigir && $c->dinheiro + $quanto < 0) {
            $nome = User::whereKey($uid)->value('name') ?? 'Um jogador';
            throw new \InvalidArgumentException("$nome não tem mais Dinheiro suficiente para a aposta.");
        }
        $c->dinheiro += $quanto;
        $c->save();
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

    /** Peças que um membro vê e pode escalar: as que ele criou e as do mestre. */
    private function pecasVisiveis(Campanha $campanha, int $uid)
    {
        return PelaDamaPeca::where('campanha_id', $campanha->id)->whereIn('user_id', array_unique([$uid, (int) $campanha->mestre_id]))->orderBy('nome')->get();
    }

    /** Tipos de peça que um membro pode escalar: os padrão, os que ele criou e os do mestre. */
    private function tiposDe(Campanha $campanha, int $uid): array
    {
        $tipos = PelaDama::tiposPadrao();
        foreach ($this->pecasVisiveis($campanha, $uid) as $peca) {
            $tipos[$peca->tipoId()] = $peca->paraJogo();
        }

        return $tipos;
    }

    /** Cartas de Gladio da campanha no formato do motor: [id => carta]. */
    private function catalogo(Campanha $campanha): array
    {
        return GladioCarta::whereIn('campanha_id', $this->campanhasOficiais($campanha))->get()
            ->mapWithKeys(fn ($c) => [(string) $c->id => $c->paraJogo()])->all();
    }

    /**
     * Lista de cartas do deck escolhido (uma entrada por cópia) ou null para jogar com 20 cartas sorteadas.
     * Cartas que o mestre apagou ficam de fora; se o deck ficar pequeno demais, a partida não começa.
     */
    private function deckDe(Campanha $campanha, int $uid, mixed $deckId, array $catalogo): ?array
    {
        if (!$deckId || !Schema::hasTable('gladio_decks')) {
            return null;
        }
        $deck = GladioDeck::where('campanha_id', $campanha->id)->where('user_id', $uid)->find((int) $deckId);
        if (!$deck) {
            throw new \InvalidArgumentException('Esse deck não existe mais. Escolha outro.');
        }
        $lista = $deck->lista($catalogo);
        if (count($lista) < GladioDeck::MIN) {
            throw new \InvalidArgumentException("O deck \"{$deck->nome}\" ficou com menos de " . GladioDeck::MIN . ' cartas (o mestre apagou algumas). Ajuste o deck.');
        }

        return $lista;
    }

    /** Comandante escolhido no deck (id da carta) ou null: sem deck ou sem escolha, o motor sorteia uma Lenda. */
    private function comandanteDe(Campanha $campanha, int $uid, mixed $deckId): ?string
    {
        if (!$deckId || !GladioDeck::temComandante()) {
            return null;
        }
        $id = GladioDeck::where('campanha_id', $campanha->id)->where('user_id', $uid)->whereKey((int) $deckId)->value('comandante_id');

        return $id ? (string) $id : null;
    }

    private function listaDecks(Campanha $campanha, int $uid): array
    {
        if (!Schema::hasTable('gladio_decks')) {
            return [];
        }

        return GladioDeck::where('campanha_id', $campanha->id)->where('user_id', $uid)->orderBy('nome')->get()
            ->map(fn (GladioDeck $d) => $d->paraLista())->all();
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

    private function pecaJson(PelaDamaPeca $p, int $uid): array
    {
        return ['id' => $p->id, 'tipo' => $p->tipoId(), 'nome' => $p->nome, 'simbolo' => $p->simbolo, 'regras' => $p->regras ?: [],
            'foto' => $p->fotoUrl(), 'minha' => (int) $p->user_id === $uid, 'mestre' => (int) $p->user_id === (int) $p->campanha?->mestre_id];
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
            ->get(['id', 'jogo', 'jogador1_id', 'jogador2_id', 'convidado_id', 'aposta', 'status', 'vencedor_id', 'config', 'updated_at'])
            // partidas que o jogador tirou do próprio histórico
            ->reject(fn ($p) => in_array($uid, array_map('intval', (array) ($p->config['ocultos'] ?? [])), true));

        return [
            'carteiras' => JogoCarteira::where('campanha_id', $campanha->id)->pluck('dinheiro', 'user_id'),
            'fichas'    => $this->fichasApostaveis($campanha, $uid), // personagens dele que podem apostar o próprio Dinheiro
            'partidas'  => $partidas->map(function ($p) use ($nome, $uid, $mestre, $campanha) {
                $gladio = $p->jogo === 'gladio';
                $cfg = $gladio ? $this->configMesa($p) : null;
                $jogadores = $gladio && $p->status === 'aberta' ? $cfg['jogadores'] : ($gladio ? $p->jogadores() : array_filter([$p->jogador1_id, $p->jogador2_id]));
                $estou = in_array($uid, array_map('intval', $jogadores), true);
                $vagas = $gladio ? $cfg['vagas'] : 2;
                $criador = (int) $p->jogador1_id === $uid;

                return [
                    'id'        => $p->id,
                    'jogo'      => $p->jogo,
                    'status'    => $p->status,
                    'aposta'    => (int) $p->aposta,
                    'j1'        => $nome($p->jogador1_id),
                    'j2'        => $nome($p->jogador2_id),
                    'jogadores' => array_values(array_map(fn ($id) => (int) $id === GladioBot::ID && !empty($p->config['bot']) ? (string) $p->config['bot']['nome'] : $nome($id), $jogadores)),
                    'vagas'     => $vagas,
                    'equipes'   => $gladio && $cfg['equipes'] ? array_map(fn ($k) => [
                        'letra' => self::LETRAS[$k],
                        'nomes' => array_values(array_map(fn ($id) => $nome($id), array_keys(array_filter($cfg['equipe'], fn ($x) => $x === $k)))),
                        'vaga'  => $p->status === 'aberta' && count(array_keys($cfg['equipe'], $k, true)) < intdiv($vagas, $cfg['equipes']),
                        'minha' => ($cfg['equipe'][(string) $uid] ?? null) === $k,
                    ], range(0, $cfg['equipes'] - 1)) : null,
                    'convidado' => $nome($p->convidado_id),
                    'vencedor'  => $nome($p->vencedor_id),
                    'meu'       => $estou,
                    'aceitar'   => $p->status === 'aberta' && !$estou && count($jogadores) < $vagas && (!$p->convidado_id || (int) $p->convidado_id === $uid),
                    'comecar'   => $gladio && $p->status === 'aberta' && $criador && count($jogadores) >= 2 && count($jogadores) < $vagas,
                    'sair'      => $gladio && $p->status === 'aberta' && $estou && !$criador,
                    'cancelar'  => ($p->status === 'aberta' && ($mestre || $criador)) || ($p->status === 'andamento' && $mestre),
                    'apagar'    => in_array($p->status, ['fim', 'cancelada'], true) && ($mestre || $estou || $criador),
                    'url'       => route('campanhas.jogos.partida', [$campanha, $p->id]),
                ];
            })->values(),
        ];
    }

    /** O bolão para a tela: em quem dá para apostar, o total em cada um, as apostas da pessoa e se ainda aceita apostas. */
    private function resumoBolao(JogoPartida $p, int $uid): array
    {
        $apostas = (array) (($p->config ?? [])['bolao'] ?? []);
        $alvos = $p->estado ? $this->alvosDoBolao($p) : [];
        $totais = [];
        foreach ($apostas as $a) {
            $totais[$a['alvo']] = ($totais[$a['alvo']] ?? 0) + (int) $a['v'];
        }

        return [
            'alvos'  => $alvos,
            'totais' => (object) $totais,
            'pote'   => array_sum($totais),
            'meu'    => array_values(array_map(fn ($a) => ['alvo' => $a['alvo'], 'v' => (int) $a['v']], array_filter($apostas, fn ($a) => (int) $a['u'] === $uid))),
            'aberto' => $p->status === 'andamento' && empty(($p->estado ?? [])['fim']) && (int) (($p->estado ?? [])['turno'] ?? 1) <= self::BOLAO_ATE_TURNO && !$p->ladoDe($uid),
            'ate'    => self::BOLAO_ATE_TURNO,
            'pago'   => !empty(($p->config ?? [])['bolao_pago']),
            'saldo'  => $p->campanha_id ? $this->saldo(Campanha::find($p->campanha_id), $uid) : 0,
        ];
    }

    /** Estado que o navegador recebe: no Gladio cada um só vê a própria mão; no Pela Dama vão os lances possíveis de quem joga. */
    private function dadosPartida(JogoPartida $p): array
    {
        $uid = (int) auth()->id();
        $lado = $p->ladoDe($uid);
        $e = $p->estado;
        if ($e && $p->jogo === 'gladio') {
            $e = Gladio::visao(Gladio::completar($e), $lado);
        } elseif ($e && $p->jogo === 'pela_dama') {
            $e['legais'] = $lado && $p->status === 'andamento' && ($e['vez'] ?? null) === $lado ? PelaDama::mapaLegais($e) : (object) [];
            // Rei protegido de xeque: jogadas que ainda faltam a cada cor para a proteção acabar
            $e['protecao'] = ['b' => max(0, PelaDama::PROTECAO - PelaDama::jogadas($e, 'b')), 'p' => max(0, PelaDama::PROTECAO - PelaDama::jogadas($e, 'p'))];
        }
        $p->loadMissing(['jogador1', 'jogador2']);
        // Gladio: os jogadores em roda ({ lado, nome }), na mesma ordem dos lados do motor
        $roda = $p->jogo === 'gladio'
            ? array_map(fn ($id, $k) => ['lado' => Gladio::LADOS[$k], 'nome' => $this->nomeJogador($p, (int) $id)],
                $p->jogadores(), array_keys($p->jogadores()))
            : [];

        return [
            'v'         => (int) $p->versao,
            'jogo'      => $p->jogo,
            'status'    => $p->status,
            'aposta'    => (int) $p->aposta,
            'lado'      => $lado,
            'mestre'    => $p->campanha_id ? (bool) \App\Models\Campanha::find($p->campanha_id)?->ehMestre($uid) : false, // pode pausar o Gladio
            'nomes'     => [
                'j1' => (string) optional($p->jogador1)->name,
                'j2' => (string) optional($p->jogador2)->name,
            ],
            'jogadores' => $roda,
            'chat'      => array_values((array) (($p->config ?? [])['chat'] ?? [])),
            'bolao'     => $p->jogo === 'gladio' ? $this->resumoBolao($p, $uid) : null,
            'vencedor'  => $p->vencedor_id ? ((int) $p->vencedor_id === (int) $p->jogador1_id ? 'j1' : 'j2') : null,
            'estado'    => $e,
        ];
    }
}
