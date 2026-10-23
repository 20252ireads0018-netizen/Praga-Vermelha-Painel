<?php

namespace App\Http\Controllers;

class MaldicaoController extends Controller
{
    // GET /maldicoes  -> lista o catálogo (view: maldicoes/index.blade.php)
    public function index()
    {
        $maldicoes = config('maldicoes', []);

        return view('maldicoes.index', compact('maldicoes'));
    }

    // GET /maldicoes/{slug} -> página de uma maldição (view: maldicoes/show.blade.php)
    public function show(string $slug)
    {
        $catalogo = config('maldicoes', []);
        $maldicao = $catalogo[$slug] ?? null;

        abort_if($maldicao === null, 404);

        return view('maldicoes.show', [
            'maldicao' => $maldicao,
            'slug'     => $slug,
        ]);
    }
}