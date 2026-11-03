<?php

namespace Tests\Feature;

use Tests\TestCase;

class VideosTest extends TestCase
{
    public function test_aba_de_videos_e_publica_e_so_lista_os_que_tem_arquivo(): void
    {
        config(['session.driver' => 'array', 'cache.default' => 'array']);
        $existe = 'videos/teste-existe.mp4';
        @mkdir(public_path('videos'), 0777, true);
        file_put_contents(public_path($existe), str_repeat('x', 2048));
        config(['videos' => [
            ['id' => 'tem', 'titulo' => 'Vídeo que existe', 'descricao' => 'd', 'tema' => 'Teste', 'arquivo' => $existe, 'capa' => 'videos/x.jpg'],
            ['id' => 'falta', 'titulo' => 'Vídeo sem arquivo', 'descricao' => 'd', 'tema' => 'Teste', 'arquivo' => 'videos/nao-existe.mp4', 'capa' => 'videos/y.jpg'],
        ]]);
        try {
            $this->get('/videos-explicativos')->assertOk() // sem login
                ->assertSee('Vídeos explicativos')->assertSee('Vídeo que existe')->assertDontSee('Vídeo sem arquivo')
                ->assertSee('download="tem.mp4"', false);
        } finally {
            @unlink(public_path($existe));
        }
    }
}
