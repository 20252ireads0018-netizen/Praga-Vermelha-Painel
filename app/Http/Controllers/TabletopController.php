<?php

namespace App\Http\Controllers;

use App\Models\Campanha;
use App\Models\Mesa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TabletopController extends Controller
{
    private const FORMAS   = ['circulo', 'quadrado', 'losango'];
    private const DESENHOS = ['pincel', 'circulo', 'quadrado', 'cone', 'triangulo'];
    private const ID_REGEX = '/^[A-Za-z0-9._-]{1,60}$/';

    /* ================================================================== */
    /*  Mesas (modo normal)                                                */
    /* ================================================================== */

    /** Lista de mesas + formulários para criar uma nova / entrar numa sala. */
    public function index(): View
    {
        // Mesas com sala aberta por OUTRA pessoa não aparecem: para entrar nelas é preciso o código.
        $mesas = Mesa::where(function ($q) {
                $q->whereNull('codigo')->orWhere('sala_dono', Auth::id());
            })
            ->orderByDesc('updated_at')
            ->get(['id', 'nome', 'updated_at', 'codigo', 'sala_dono']);

        return view('tabletop.index', compact('mesas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:80'],
        ]);

        $mesa = Mesa::create([
            'nome'   => trim($dados['nome']),
            'estado' => $this->estadoPadrao(),
        ]);

        return redirect()->route('tabletop.show', $mesa);
    }

    public function show(Mesa $mesa): View|RedirectResponse
    {
        // Sala aberta por outra pessoa: só se entra pelo código.
        if ($mesa->codigo && !$this->ehDono($mesa)) {
            return redirect()->route('tabletop.index')
                ->withErrors(['codigo' => 'Essa mesa tem uma sala aberta. Entre usando o código da sala.']);
        }

        return $this->renderizar($mesa, false);
    }

    /** Autosave: o JS manda o estado inteiro (string JSON) e o servidor valida tudo antes de gravar. */
    public function update(Request $request, Mesa $mesa): JsonResponse
    {
        // Com sala aberta quem manda é a sincronização por patches; gravar o estado inteiro apagaria o que os outros fizeram.
        if ($mesa->codigo) {
            return response()->json(['ok' => false, 'erro' => 'Sala aberta.'], 409);
        }

        $request->validate([
            'estado' => ['required', 'string', 'max:8000000'],
            'nome'   => ['nullable', 'string', 'max:80'],
        ]);

        $bruto = json_decode($request->input('estado'), true);
        if (!is_array($bruto)) {
            return response()->json(['ok' => false, 'erro' => 'Estado inválido.'], 422);
        }

        $mesa->estado = $this->sanitizar($bruto);

        $nome = trim((string) $request->input('nome', ''));
        if ($nome !== '') {
            $mesa->nome = Str::limit($nome, 80, '');
        }

        $mesa->save();

        return response()->json(['ok' => true]);
    }

    public function destroy(Mesa $mesa): RedirectResponse
    {
        if ($mesa->codigo && !$this->ehDono($mesa)) {
            abort(403);
        }

        Cache::forget($this->chavePresenca($mesa));
        $mesa->delete();

        return redirect()->route('tabletop.index');
    }

    /* ================================================================== */
    /*  Salas online                                                       */
    /*  Sem WebSocket: o navegador manda "patches" (só o que mudou) e      */
    /*  pergunta "mudou?" a cada ~1 s. `versao` sobe a cada alteração.     */
    /* ================================================================== */

    /** O dono da mesa abre a sala: gera um código para os outros entrarem. */
    public function abrirSala(Mesa $mesa): JsonResponse
    {
        if ($mesa->codigo) {
            if (!$this->ehDono($mesa)) {
                return response()->json(['ok' => false, 'erro' => 'Essa mesa já tem uma sala aberta por outra pessoa.'], 403);
            }

            return response()->json(['ok' => true, 'codigo' => $mesa->codigo]);
        }

        $mesa->codigo    = $this->codigoNovo();
        $mesa->sala_dono = Auth::id();
        $mesa->versao    = 1;
        $mesa->save();

        return response()->json(['ok' => true, 'codigo' => $mesa->codigo]);
    }

    /** Só o dono encerra. A mesa continua como estava; quem estava dentro é mandado para a lista. */
    public function encerrarSala(Mesa $mesa): JsonResponse
    {
        if ($mesa->codigo && !$this->ehDono($mesa)) {
            return response()->json(['ok' => false, 'erro' => 'Só quem abriu a sala pode encerrá-la.'], 403);
        }

        $mesa->codigo    = null;
        $mesa->sala_dono = null;
        $mesa->versao    = 1;
        $mesa->save();

        Cache::forget($this->chavePresenca($mesa));

        return response()->json(['ok' => true]);
    }

    /** Formulário "Entrar na sala": valida o código e manda para a sala. */
    public function entrar(Request $request): RedirectResponse
    {
        $dados  = $request->validate(['codigo' => ['required', 'string', 'max:20']]);
        $codigo = Str::upper(trim($dados['codigo']));

        if (!preg_match('/^[A-Z0-9]{1,20}$/', $codigo) || !Mesa::where('codigo', $codigo)->exists()) {
            return back()->withErrors(['codigo' => 'Sala não encontrada. Confira o código e tente de novo.'])->withInput();
        }

        return redirect()->route('tabletop.sala', $codigo);
    }

    /** Página da sala para quem entrou pelo código. */
    public function sala(string $codigo): View|RedirectResponse
    {
        $mesa = Mesa::where('codigo', Str::upper($codigo))->first();

        if (!$mesa) {
            return redirect()->route('tabletop.index')
                ->withErrors(['codigo' => 'Essa sala não existe mais.']);
        }

        if ($this->ehDono($mesa)) {
            return redirect()->route('tabletop.show', $mesa);
        }

        return $this->renderizar($mesa, true);
    }

    /** Consulta rápida: se nada mudou, devolve só a versão e quem está online (não toca no estado pesado). */
    public function salaEstado(Request $request, string $codigo): JsonResponse
    {
        $leve = $this->mesaDaSala($codigo, ['id', 'codigo', 'sala_dono', 'versao']);
        $on   = $this->presenca($leve, $request->query('c'));

        $v = (int) $request->query('v', 0);
        if ($v === (int) $leve->versao) {
            return $this->json(['v' => $v, 'on' => $on]);
        }

        $mesa = Mesa::findOrFail($leve->id);

        return $this->json($this->resposta($mesa, $this->estadoDe($mesa), $on));
    }

    /** Fundo do mapa (?mapa=1) e imagens de peças (?imgs=a,b,c): só quando o navegador precisa. */
    public function salaPesado(Request $request, string $codigo): JsonResponse
    {
        $mesa   = $this->mesaDaSala($codigo);
        $estado = $this->estadoDe($mesa);

        if ($request->boolean('mapa')) {
            return $this->json(['mapa' => (string) ($estado['mapa'] ?? '')]);
        }

        $imgs = [];
        $ids  = array_slice(explode(',', (string) $request->query('imgs', '')), 0, 10);
        foreach ($ids as $id) {
            if (preg_match(self::ID_REGEX, $id) && isset($estado['imgs'][$id])) {
                $imgs[$id] = $estado['imgs'][$id];
            }
        }

        return $this->json(['imgs' => (object) $imgs]);
    }

    /** Recebe um patch, soma ao estado da sala (com a linha trancada) e devolve o estado já somado. */
    public function salaAlterar(Request $request, string $codigo): JsonResponse
    {
        $request->validate([
            'patch' => ['required', 'string', 'max:8000000'],
            'c'     => ['nullable', 'string', 'max:20'],
        ]);

        $patch = json_decode($request->input('patch'), true);
        if (!is_array($patch)) {
            return response()->json(['ok' => false, 'erro' => 'Patch inválido.'], 422);
        }

        [$mesa, $estado] = DB::transaction(function () use ($codigo, $patch) {
            $mesa = Mesa::where('codigo', Str::upper($codigo))->lockForUpdate()->first();
            if (!$mesa) {
                abort(404);
            }

            $estado = $this->aplicarPatch($this->estadoDe($mesa), $patch, $this->ehDono($mesa), $mesa);

            $mesa->estado = $estado;
            $mesa->versao = (int) $mesa->versao + 1;
            $mesa->save();

            return [$mesa, $estado];
        });

        $on = $this->presenca($mesa, $request->input('c'));

        return $this->json($this->resposta($mesa, $estado, $on));
    }

    /**
     * Painel rápido da campanha (só mover peças): devolve a sala ao vivo da mesa vinculada.
     * Se ainda não há sala, o mestre da campanha abre uma na hora; o jogador espera o mestre.
     */
    public function salaCampanha(Campanha $campanha, Mesa $mesa): JsonResponse
    {
        $uid = Auth::id();
        abort_unless($campanha->temMembro($uid), 403, 'Você não participa desta campanha.');
        abort_unless((int) $mesa->campanha_id === (int) $campanha->id, 404);

        if (!$mesa->codigo && $campanha->ehMestre($uid)) {
            $mesa->codigo    = $this->codigoNovo();
            $mesa->sala_dono = $uid;
            $mesa->versao    = 1;
            $mesa->save();
        }

        if (!$mesa->codigo) {
            return $this->json(['ok' => true, 'codigo' => null]);
        }

        $cod = $mesa->codigo;

        return $this->json([
            'ok'     => true,
            'codigo' => $cod,
            'url'    => [
                'estado'  => route('tabletop.sala.estado', $cod),
                'alterar' => route('tabletop.sala.alterar', $cod),
                'pesado'  => route('tabletop.sala.pesado', $cod),
                'abrir'   => $this->ehDono($mesa) ? route('tabletop.show', $mesa) : route('tabletop.sala', $cod),
            ],
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Sala: auxiliares                                                  */
    /* ------------------------------------------------------------------ */

    private function ehDono(Mesa $mesa): bool
    {
        return $mesa->sala_dono !== null && (int) $mesa->sala_dono === (int) Auth::id();
    }

    private function mesaDaSala(string $codigo, array $colunas = ['*']): Mesa
    {
        $mesa = Mesa::where('codigo', Str::upper($codigo))->first($colunas);
        if (!$mesa) {
            abort(404);
        }

        return $mesa;
    }

    private function json(array $dados): JsonResponse
    {
        return response()->json($dados)->header('Cache-Control', 'no-store');
    }

    private function codigoNovo(): string
    {
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // sem 0/O, 1/I/L
        do {
            $codigo = '';
            for ($i = 0; $i < 6; $i++) {
                $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
        } while (Mesa::where('codigo', $codigo)->exists());

        return $codigo;
    }

    private function hashMapa(array $estado): string
    {
        $mapa = (string) ($estado['mapa'] ?? '');

        return $mapa === '' ? '' : md5($mapa);
    }

    /** Dados que o JS recebe em CFG.sala. */
    private function dadosSala(Mesa $mesa, array $estado): array
    {
        $cod = $mesa->codigo;

        return [
            'codigo' => $cod,
            'dono'   => $this->ehDono($mesa),
            'v'      => (int) $mesa->versao,
            'mapaH'  => $this->hashMapa($estado),
            'imgs'   => array_map('strval', array_keys((array) $estado['imgs'])),
            'url'    => [
                'alterar' => route('tabletop.sala.alterar', $cod),
                'estado'  => route('tabletop.sala.estado', $cod),
                'pesado'  => route('tabletop.sala.pesado', $cod),
                'sair'    => route('tabletop.index'),
            ],
        ];
    }

    /** Resposta padrão das consultas e dos patches. O fundo e as imagens vão separados (rota "pesado"). */
    private function resposta(Mesa $mesa, array $estado, array $on): array
    {
        return [
            'v'      => (int) $mesa->versao,
            'on'     => $on,
            'estado' => [
                'pecas'   => array_values($estado['pecas'] ?? []),
                'desenho' => array_values($estado['desenho'] ?? []),
                'cols'    => (int) ($estado['cols'] ?? 16),
                'rows'    => (int) ($estado['rows'] ?? 12),
                'cel'     => (int) ($estado['cel'] ?? 40),
                'grade'   => ($estado['grade'] ?? true) !== false,
                'nomes'   => ($estado['nomes'] ?? true) !== false,
            ],
            'imgs'   => array_map('strval', array_keys((array) ($estado['imgs'] ?? []))),
            'mapaH'  => $this->hashMapa($estado),
        ];
    }

    /** Quem está online: cada aba manda um id (c); some depois de ~20 s sem falar com o servidor. */
    private function chavePresenca(Mesa $mesa): string
    {
        return 'tabletop:on:' . $mesa->id;
    }

    private function presenca(Mesa $mesa, mixed $cid): array
    {
        $agora = time();
        $chave = $this->chavePresenca($mesa);
        $lista = Cache::get($chave, []);
        $lista = is_array($lista) ? $lista : [];

        if (is_string($cid) && preg_match('/^[A-Za-z0-9]{1,20}$/', $cid)) {
            $u = Auth::user();
            $nome = (string) ($u->name ?? $u->nome ?? 'Jogador');
            $lista[$cid] = [
                'n' => Str::limit(trim($nome) ?: 'Jogador', 24, ''),
                'd' => $this->ehDono($mesa),
                't' => $agora,
            ];
        }

        $lista = array_filter($lista, fn ($x) => is_array($x) && ($agora - ($x['t'] ?? 0)) <= 20);
        Cache::put($chave, $lista, 300);

        return array_values(array_map(fn ($x) => ['n' => $x['n'], 'd' => (bool) $x['d']], $lista));
    }

    /** Soma o patch ao estado. Só o dono muda linhas, colunas, casa, grade, fundo, nome e "Minhas peças". */
    private function aplicarPatch(array $e, array $p, bool $dono, Mesa $mesa): array
    {
        $ids = fn (mixed $v): array => array_flip(array_filter(is_array($v) ? $v : [], fn ($i) => is_string($i) && preg_match(self::ID_REGEX, $i)));

        // peças: up = criadas/alteradas, del = removidas
        if (is_array($p['pecas'] ?? null)) {
            $del = $ids($p['pecas']['del'] ?? []);
            $up  = [];
            foreach (array_slice(is_array($p['pecas']['up'] ?? null) ? $p['pecas']['up'] : [], 0, 200) as $x) {
                if (is_array($x) && is_string($x['id'] ?? null) && preg_match(self::ID_REGEX, $x['id'])) {
                    $up[$x['id']] = $x;
                }
            }
            $novas = [];
            foreach ($e['pecas'] as $q) {
                $id = (string) ($q['id'] ?? '');
                if (isset($del[$id])) {
                    continue;
                }
                if (isset($up[$id])) {
                    $novas[] = $up[$id];
                    unset($up[$id]);
                } else {
                    $novas[] = $q;
                }
            }
            foreach ($up as $id => $x) {
                if (!isset($del[$id])) {
                    $novas[] = $x;
                }
            }
            $e['pecas'] = $novas;
        }

        // desenho: add = traços novos (um traço nunca muda depois de feito), del = apagados
        if (is_array($p['desenho'] ?? null)) {
            $del   = $ids($p['desenho']['del'] ?? []);
            $lista = array_values(array_filter($e['desenho'], fn ($i) => !isset($del[(string) ($i['id'] ?? '')])));
            $tem   = [];
            foreach ($lista as $i) {
                $tem[(string) ($i['id'] ?? '')] = true;
            }
            foreach (array_slice(is_array($p['desenho']['add'] ?? null) ? $p['desenho']['add'] : [], 0, 200) as $x) {
                if (is_array($x) && is_string($x['id'] ?? null) && preg_match(self::ID_REGEX, $x['id']) && !isset($tem[$x['id']])) {
                    $lista[] = $x;
                    $tem[$x['id']] = true;
                }
            }
            $e['desenho'] = $lista;
        }

        // imagens de peças novas (id => data URL)
        if (is_array($p['imgs'] ?? null)) {
            $n = 0;
            foreach ($p['imgs'] as $k => $v) {
                $k = (string) $k;
                if ($n >= 10) {
                    break;
                }
                if (preg_match(self::ID_REGEX, $k) && $this->img($v) !== '') {
                    $e['imgs'][$k] = $v;
                    $n++;
                }
            }
        }

        if ($dono) {
            if (is_array($p['cfg'] ?? null)) {
                foreach (['cols', 'rows', 'cel', 'grade', 'nomes'] as $k) {
                    if (array_key_exists($k, $p['cfg'])) {
                        $e[$k] = $p['cfg'][$k];
                    }
                }
            }
            if (array_key_exists('mapa', $p)) {
                $e['mapa'] = is_string($p['mapa']) ? $p['mapa'] : '';
            }
            if (is_array($p['modelos'] ?? null)) {
                $e['modelos'] = $p['modelos'];
            }
            if (is_string($p['nome'] ?? null) && trim($p['nome']) !== '') {
                $mesa->nome = Str::limit(trim($p['nome']), 80, '');
            }
        }

        // limpa imagens sem peça quando o limite (120) está chegando
        if (count($e['imgs']) > 100) {
            $usadas = [];
            foreach ($e['pecas'] as $q) {
                if (is_array($q) && !empty($q['img'])) {
                    $usadas[(string) $q['img']] = true;
                }
            }
            $e['imgs'] = array_intersect_key($e['imgs'], $usadas);
        }

        return $this->sanitizar($e);
    }

    /** Estado salvo da mesa, sempre completo e com `imgs` como array. */
    private function estadoDe(Mesa $mesa): array
    {
        $e = $mesa->estado;
        if (is_string($e)) {
            $e = json_decode($e, true);
        }
        $e = is_array($e) && $e ? array_merge($this->estadoPadrao(), $e) : $this->estadoPadrao();

        foreach (['pecas', 'desenho', 'modelos', 'imgs'] as $k) {
            $e[$k] = is_array($e[$k] ?? null) ? $e[$k] : [];
        }

        return $e;
    }

    private function renderizar(Mesa $mesa, bool $convidado): View
    {
        $estado = $this->estadoDe($mesa);
        $sala   = $mesa->codigo ? $this->dadosSala($mesa, $estado) : null;

        if ($convidado) {
            $estado['modelos'] = []; // "Minhas peças" é do dono
        }

        return view('tabletop.show', compact('mesa', 'estado', 'sala', 'convidado'));
    }

    /* ================================================================== */
    /*  Validação do estado                                                */
    /* ================================================================== */

    private function estadoPadrao(): array
    {
        return [
            'v' => 1, 'cols' => 16, 'rows' => 12, 'cel' => 40,
            'grade' => true, 'nomes' => true,
            'pecas' => [], 'desenho' => [], 'imgs' => (object) [], 'mapa' => '', 'modelos' => [],
        ];
    }

    private function num(mixed $v, float $min, float $max, float $padrao): float
    {
        $n = is_numeric($v) ? (float) $v : $padrao;

        return max($min, min($max, $n));
    }

    private function cor(mixed $v, string $padrao): string
    {
        return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : $padrao;
    }

    private function id(mixed $v): string
    {
        return is_string($v) && preg_match(self::ID_REGEX, $v) ? $v : Str::lower(Str::random(10));
    }

    /** Aceita só data URL de imagem com base64 de verdade (sem regex gigante em string grande). */
    private function img(mixed $v): string
    {
        if (!is_string($v) || strlen($v) > 2_500_000) {
            return '';
        }
        if (!preg_match('#^data:image/(png|jpe?g|webp|gif);base64,#', $v, $m)) {
            return '';
        }
        $b64 = substr($v, strlen($m[0]));

        return $b64 !== '' && strspn($b64, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=') === strlen($b64)
            ? $v : '';
    }

    private function sanitizar(array $e): array
    {
        $out = $this->estadoPadrao();
        $out['cols']  = (int) round($this->num($e['cols'] ?? 16, 4, 60, 16));
        $out['rows']  = (int) round($this->num($e['rows'] ?? 12, 4, 60, 12));
        $out['cel']   = (int) round($this->num($e['cel'] ?? 40, 20, 80, 40));
        $out['grade'] = ($e['grade'] ?? true) !== false;
        $out['nomes'] = ($e['nomes'] ?? true) !== false;
        $out['mapa']  = $this->img($e['mapa'] ?? '');

        // imagens das peças (id => data URL)
        $imgs = [];
        foreach (array_slice(is_array($e['imgs'] ?? null) ? $e['imgs'] : [], 0, 120, true) as $k => $v) {
            $k = (string) $k;
            $src = $this->img($v);
            if ($src !== '' && preg_match(self::ID_REGEX, $k)) {
                $imgs[$k] = $src;
            }
        }
        $out['imgs'] = (object) $imgs;

        // peças
        foreach (array_slice(is_array($e['pecas'] ?? null) ? $e['pecas'] : [], 0, 200) as $p) {
            if (!is_array($p)) {
                continue;
            }
            $tam = (int) round($this->num($p['tam'] ?? 1, 1, 4, 1));
            $peca = [
                'id'    => $this->id($p['id'] ?? null),
                'nome'  => Str::limit(trim((string) ($p['nome'] ?? '')) ?: 'Peça', 24, ''),
                'x'     => (int) round($this->num($p['x'] ?? 0, 0, max(0, $out['cols'] - $tam), 0)),
                'y'     => (int) round($this->num($p['y'] ?? 0, 0, max(0, $out['rows'] - $tam), 0)),
                'tam'   => $tam,
                'forma' => in_array($p['forma'] ?? '', self::FORMAS, true) ? $p['forma'] : 'circulo',
                'cor'   => $this->cor($p['cor'] ?? null, '#a78bfa'),
            ];
            if (!empty($p['img']) && isset($imgs[(string) $p['img']])) {
                $peca['img'] = (string) $p['img'];
                $peca['iz']  = round($this->num($p['iz'] ?? 1, 1, 5, 1), 2);
                $lim         = ($peca['iz'] - 1) * 50; // deslocamento da imagem (% da peça), limitado pelo zoom
                $peca['ix']  = round($this->num($p['ix'] ?? 0, -$lim, $lim, 0), 1);
                $peca['iy']  = round($this->num($p['iy'] ?? 0, -$lim, $lim, 0), 1);
            }
            $out['pecas'][] = $peca;
        }

        // desenho (pincel + formas)
        foreach (array_slice(is_array($e['desenho'] ?? null) ? $e['desenho'] : [], 0, 600) as $it) {
            if (!is_array($it) || !in_array($it['t'] ?? '', self::DESENHOS, true)) {
                continue;
            }
            $item = [
                'id' => $this->id($it['id'] ?? null),
                't'  => $it['t'],
                'c'  => $this->cor($it['c'] ?? null, '#f472b6'),
                'w'  => round($this->num($it['w'] ?? 3, 1, 24, 3), 1),
            ];
            if ($it['t'] === 'pincel') {
                $d = (string) ($it['d'] ?? '');
                if ($d === '' || strlen($d) > 60000 || !preg_match('/^[MLQl0-9. -]+$/', $d)) {
                    continue;
                }
                $item['d'] = $d;
                $item['p'] = array_values(array_map(
                    fn ($n) => round((float) $n, 1),
                    array_slice(array_filter(is_array($it['p'] ?? null) ? $it['p'] : [], 'is_numeric'), 0, 8000)
                ));
            } else {
                $item['f'] = !empty($it['f']);
                foreach (['x1', 'y1', 'x2', 'y2'] as $k) {
                    $item[$k] = round($this->num($it[$k] ?? 0, -5000, 5000, 0), 1);
                }
            }
            $out['desenho'][] = $item;
        }

        // "Minhas peças"
        foreach (array_slice(is_array($e['modelos'] ?? null) ? $e['modelos'] : [], 0, 60) as $m) {
            if (!is_array($m)) {
                continue;
            }
            $iz  = round($this->num($m['iz'] ?? 1, 1, 5, 1), 2);
            $lim = ($iz - 1) * 50;
            $out['modelos'][] = [
                'id'    => $this->id($m['id'] ?? null),
                'nome'  => Str::limit(trim((string) ($m['nome'] ?? '')) ?: 'Peça', 24, ''),
                'forma' => in_array($m['forma'] ?? '', self::FORMAS, true) ? $m['forma'] : 'circulo',
                'cor'   => $this->cor($m['cor'] ?? null, '#a78bfa'),
                'tam'   => (int) round($this->num($m['tam'] ?? 1, 1, 4, 1)),
                'iz'    => $iz,
                'ix'    => round($this->num($m['ix'] ?? 0, -$lim, $lim, 0), 1),
                'iy'    => round($this->num($m['iy'] ?? 0, -$lim, $lim, 0), 1),
                'img'   => $this->img($m['img'] ?? ''),
            ];
        }

        return $out;
    }
}