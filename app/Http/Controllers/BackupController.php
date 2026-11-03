<?php

namespace App\Http\Controllers;

use App\Support\Backup;
use App\Support\Pix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Painel de backups do banco: só os administradores (mesma lista do Pix) veem, geram e baixam. */
class BackupController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(Pix::admin($request->user()), 404);

        return view('admin-backup', ['backups' => Backup::lista(), 'manter' => Backup::MANTER]);
    }

    public function gerar(Request $request): RedirectResponse
    {
        abort_unless(Pix::admin($request->user()), 404);

        try {
            $nome = Backup::gerar();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.backup')->withErrors(['backup' => 'O backup falhou: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.backup')->with('ok', "Backup {$nome} gerado.");
    }

    public function baixar(Request $request, string $arquivo): BinaryFileResponse
    {
        abort_unless(Pix::admin($request->user()), 404);
        $caminho = Backup::caminho($arquivo);
        abort_unless($caminho, 404);

        return response()->download($caminho, $arquivo, ['Content-Type' => 'application/gzip']);
    }
}
