<?php

namespace App\Http\Controllers;

use App\Support\CompraLivro;
use App\Support\Pix;
use App\Support\Promocao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Painel do administrador para ligar e encerrar a promoção relâmpago do livro do sistema. */
class PromocaoController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(Pix::admin($request->user()), 404);
        $cheio = CompraLivro::precoCheio();

        return view('admin-promocao', [
            'atual'   => Promocao::atual(),
            'ultima'  => Promocao::ultima(),
            'cheio'   => $cheio,
            'opcoes'  => collect(Promocao::FATORES)->map(fn ($nome, $f) => ['nome' => $nome, 'preco' => Promocao::aplicar($cheio, (float) $f)]),
        ]);
    }

    public function ligar(Request $request): RedirectResponse
    {
        abort_unless(Pix::admin($request->user()), 404);
        $d = $request->validate([
            'fator'   => 'required|in:' . implode(',', array_keys(Promocao::FATORES)),
            'minutos' => 'required|integer|min:' . Promocao::MINUTOS_MIN . '|max:' . Promocao::MINUTOS_MAX,
        ]);
        $p = Promocao::ligar((float) $d['fator'], (int) $d['minutos'], (int) $request->user()->id);

        return redirect()->route('admin.promocao')->with('ok', 'Promoção ligada: o livro sai por ' . CompraLivro::precoTexto() . ' até ' . date('H:i', $p['fim']) . '.');
    }

    public function encerrar(Request $request): RedirectResponse
    {
        abort_unless(Pix::admin($request->user()), 404);
        Promocao::encerrar();

        return redirect()->route('admin.promocao')->with('ok', 'Promoção encerrada: o livro voltou a ' . CompraLivro::precoTexto() . '.');
    }
}
