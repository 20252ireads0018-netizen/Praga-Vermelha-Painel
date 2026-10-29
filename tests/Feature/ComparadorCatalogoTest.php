<?php

namespace Tests\Feature;

use App\Support\ComparadorCatalogo as C;
use Tests\TestCase;

/**
 * Calibragem da disputa de catálogos com o catálogo de verdade (config/maldicoes): rígida com textos fracos,
 * justa com quem descreve bem a maldição.
 */
class ComparadorCatalogoTest extends TestCase
{
    private function cabecalho(string $slug): string
    {
        $item = config('maldicoes')[$slug];
        $meta = collect(config('maldicoes_lista'))->firstWhere('slug', $slug);

        return "{$meta['nome']}: {$item['raridade']}\n{$meta['nome']}, {$meta['categoria']}, {$meta['orgao']}, {$meta['apelido']}\nDescrição:\n\n";
    }

    public function test_copia_fiel_chega_ao_topo_mesmo_sem_ponto_final(): void
    {
        foreach (['visao-astral', 'anti-sobrenaturalismo', 'imortalidade'] as $slug) {
            $real = C::textoReal(config('maldicoes')[$slug]);
            $this->assertGreaterThanOrEqual(97, C::comparar($this->cabecalho($slug) . $real, $slug)['pontuacao'], $slug);
            // colado em blocos, com quebras de linha e sem ponto no fim das linhas
            $blocos = $this->cabecalho($slug) . "Conceitos Primitivos:\n\nAmeaça 0:\n\n" . preg_replace('/\.\s+/u', "\n\n", $real);
            $r = C::comparar($blocos, $slug);
            $this->assertGreaterThanOrEqual(97, $r['pontuacao'], $slug);
            $this->assertSame(1.0, (float) $r['penalidades']['lista_palavras'], "$slug: quebra de linha conta como fim de frase");
        }
    }

    public function test_textos_fracos_continuam_baixos(): void
    {
        $slug = 'visao-astral';
        $real = C::textoReal(config('maldicoes')[$slug]);
        $cab = $this->cabecalho($slug);

        // só o cabeçalho, ou o texto de outra maldição
        $this->assertLessThanOrEqual(10, C::comparar($cab, $slug)['pontuacao']);
        $this->assertLessThanOrEqual(15, C::comparar($cab . C::textoReal(config('maldicoes')['imortalidade']), $slug)['pontuacao']);
        // as palavras certas embaralhadas não são uma descrição
        $palavras = preg_split('/\s+/u', $real);
        mt_srand(7);
        shuffle($palavras);
        $this->assertLessThanOrEqual(50, C::comparar(implode(' ', $palavras), $slug)['pontuacao']);
        // metade do texto, sem as regras (números de dano e chance), fica longe do topo
        $metade = implode(' ', array_slice($palavras = preg_split('/\s+/u', $real), 0, intdiv(count($palavras), 2)));
        $this->assertLessThan(80, C::comparar($cab . $metade, $slug)['pontuacao']);
    }
}
