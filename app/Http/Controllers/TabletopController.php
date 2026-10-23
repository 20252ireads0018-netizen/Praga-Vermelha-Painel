<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TabletopController extends Controller
{
    private const FORMAS   = ['circulo', 'quadrado', 'losango'];
    private const DESENHOS = ['pincel', 'circulo', 'quadrado', 'cone', 'triangulo'];

    /** Lista de mesas + formulário para criar uma nova. */
    public function index(): View
    {
        $mesas = Mesa::orderByDesc('updated_at')->get(['id', 'nome', 'updated_at']);

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

    public function show(Mesa $mesa): View
    {
        $estado = $mesa->estado ?: $this->estadoPadrao();

        return view('tabletop.show', compact('mesa', 'estado'));
    }

    /** Autosave: o JS manda o estado inteiro (string JSON) e o servidor valida tudo antes de gravar. */
    public function update(Request $request, Mesa $mesa): JsonResponse
    {
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
        $mesa->delete();

        return redirect()->route('tabletop.index');
    }

    /* ------------------------------------------------------------------ */

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
        return is_string($v) && preg_match('/^[A-Za-z0-9._-]{1,60}$/', $v) ? $v : Str::lower(Str::random(10));
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
            if ($src !== '' && preg_match('/^[A-Za-z0-9._-]{1,60}$/', $k)) {
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
            $out['modelos'][] = [
                'id'    => $this->id($m['id'] ?? null),
                'nome'  => Str::limit(trim((string) ($m['nome'] ?? '')) ?: 'Peça', 24, ''),
                'forma' => in_array($m['forma'] ?? '', self::FORMAS, true) ? $m['forma'] : 'circulo',
                'cor'   => $this->cor($m['cor'] ?? null, '#a78bfa'),
                'tam'   => (int) round($this->num($m['tam'] ?? 1, 1, 4, 1)),
                'iz'    => round($this->num($m['iz'] ?? 1, 1, 5, 1), 2),
                'img'   => $this->img($m['img'] ?? ''),
            ];
        }

        return $out;
    }
}