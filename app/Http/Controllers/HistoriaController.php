<?php

namespace App\Http\Controllers;

use App\Support\Plano;
use Illuminate\Http\Request;

class HistoriaController extends Controller
{
    /**
     * Livros lidos no leitor. O PDF fica em public/pdf/.
     * Sumário: cada item é [título, página inicial, página final|null].
     */
    private function livros(): array
    {
        $cavaleiro = [
            1, 9, 17, 21, 29, 45, 48, 64, 67, 68, 74, 77, 82, 91, 97,
            114, 118, 130, 132, 139, 141, 148, 149, 154, 157, 161, 170, 174, 177,
        ];
        $titulosCavaleiro = [];
        foreach ($cavaleiro as $i => $inicio) {
            // O PDF tem dois "Capítulo 12" (páginas 77 e 82)
            $n = $i < 12 ? $i + 1 : ($i == 12 ? '12 (parte 2)' : $i);
            $titulosCavaleiro[] = ['Capítulo ' . $n, $inicio];
        }

        $carne = [1, 6, 21, 29, 40, 61, 76, 93];
        $titulosCarne = [];
        foreach ($carne as $i => $inicio) {
            $titulosCarne[] = ['Capítulo ' . ($i + 1), $inicio];
        }

        return [
            'o-cavaleiro-negro' => [
                'titulo'  => 'O Cavaleiro Negro',
                'pdf'     => 'pdf/cavaleiro-negro.pdf',
                'chave'   => 'cavaleiro-negro',
                'sumario' => $this->montarSumario($titulosCavaleiro),
            ],
            'o-mundo-de-carne' => [
                'titulo'  => 'O Mundo de Carne',
                'pdf'     => 'pdf/mundo-de-carne.pdf',
                'chave'   => 'mundo-de-carne',
                'sumario' => $this->montarSumario($titulosCarne),
            ],
        ];
    }

    private function montarSumario(array $itens): array
    {
        $saida = [];
        foreach ($itens as $i => [$titulo, $inicio]) {
            $proximo = $itens[$i + 1][1] ?? null;
            $saida[] = [
                't' => $titulo,
                'a' => $inicio,
                'b' => $proximo ? $proximo - 1 : null,
            ];
        }
        return $saida;
    }

    // Os livros de histórias são info-produtos: abrem com a compra do livro do sistema (ou a chave dele na sessão)
    private function liberado(): bool
    {
        return session('livro_completo') === true || Plano::comprouLivro(auth()->user());
    }

    public function ler(string $slug)
    {
        $livros = $this->livros();
        abort_unless(isset($livros[$slug]), 404);
        if (!$this->liberado()) {
            return redirect()->route('planos.index')
                ->with('limite', '"' . $livros[$slug]['titulo'] . '" faz parte dos livros em info-produto: compre o livro do sistema para liberar todos.');
        }

        return view('ler', ['livro' => $livros[$slug] + ['url' => route('historias.pdf', $slug)]]);
    }

    // Entrega o PDF só para quem tem acesso. Fica em storage/app/historias/ (fora do public);
    // enquanto o arquivo não for movido para lá, usa a cópia antiga de public/pdf/.
    public function pdf(Request $request, string $slug)
    {
        $livros = $this->livros();
        abort_unless(isset($livros[$slug]), 404);
        abort_unless($this->liberado(), 403);

        $nome    = basename($livros[$slug]['pdf']);
        $arquivo = storage_path('app/historias/' . $nome);
        if (!is_file($arquivo)) {
            $arquivo = public_path($livros[$slug]['pdf']);
        }
        abort_unless(is_file($arquivo), 404);

        if ($request->has('baixar')) {
            return response()->download($arquivo, $nome);
        }

        return response()->file($arquivo, ['Content-Type' => 'application/pdf']);
    }
}