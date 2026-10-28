<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PersonagemController;
use App\Http\Controllers\FichaArquivoController;
use App\Http\Controllers\LivroController;
use App\Http\Controllers\HistoriaController;
use App\Http\Controllers\MaldicaoController;
use App\Http\Controllers\TabletopController;
use App\Http\Controllers\CampanhaController;
use App\Http\Controllers\CampanhaCatalogoController;
use App\Http\Controllers\CampanhaRolagemController;
use App\Http\Controllers\CampanhaIniciativaController;
use App\Http\Controllers\CampanhaJogoController;

Route::get('/', [PersonagemController::class, 'home']);

// ---------- Autenticação ----------
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('login.attempt');

Route::get('/cadastro', [AuthController::class, 'showRegister'])->name('register');
Route::post('/cadastro', [AuthController::class, 'register'])
    ->middleware('throttle:10,1')
    ->name('register.store');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ---------- Área da conta (exige login) ----------
Route::middleware('auth')->group(function () {

    // Fichas
    Route::get('/fichas', [PersonagemController::class, 'fichas'])->name('fichas.create');
    Route::post('/fichafeita', [PersonagemController::class, 'fichafeita'])->name('fichafeita.store');
    Route::get('/personagens', [PersonagemController::class, 'index'])->name('personagens.index');

    // Baixar / importar ficha (arquivo .json). "importar" fica antes de /personagens/{id}.
    Route::post('/personagens/importar', [FichaArquivoController::class, 'importar'])
        ->middleware('throttle:20,1')
        ->name('personagens.importar');
    Route::get('/personagens/{id}/exportar', [FichaArquivoController::class, 'exportar'])
        ->whereNumber('id')
        ->name('personagens.exportar');

    Route::get('/personagens/{id}', [PersonagemController::class, 'show'])->name('personagens.show');
    Route::put('/personagens/{id}', [PersonagemController::class, 'update'])->name('personagens.update');
    Route::delete('/personagens/{id}', [PersonagemController::class, 'destroy'])->name('personagens.destroy');

    // Tabletop (mesa virtual) -> resources/views/tabletop/{index,show}.blade.php
    Route::get('/tabletop', [TabletopController::class, 'index'])->name('tabletop.index');
    Route::post('/tabletop', [TabletopController::class, 'store'])->name('tabletop.store');
    Route::get('/tabletop/{mesa}', [TabletopController::class, 'show'])->name('tabletop.show');
    Route::put('/tabletop/{mesa}', [TabletopController::class, 'update'])->name('tabletop.update'); // autosave (JSON)
    Route::delete('/tabletop/{mesa}', [TabletopController::class, 'destroy'])->name('tabletop.destroy');

    // Salas online do Tabletop: o dono abre/encerra a sala; qualquer pessoa logada com o código entra e joga junto
    Route::post('/tabletop/entrar', [TabletopController::class, 'entrar'])
        ->middleware('throttle:20,1')
        ->name('tabletop.entrar');
    Route::post('/tabletop/{mesa}/sala', [TabletopController::class, 'abrirSala'])->name('tabletop.sala.abrir');
    Route::delete('/tabletop/{mesa}/sala', [TabletopController::class, 'encerrarSala'])->name('tabletop.sala.encerrar');
    Route::get('/tabletop/sala/{codigo}', [TabletopController::class, 'sala'])
        ->where('codigo', '[A-Za-z0-9]{1,20}')->name('tabletop.sala');
    Route::get('/tabletop/sala/{codigo}/estado', [TabletopController::class, 'salaEstado'])
        ->where('codigo', '[A-Za-z0-9]{1,20}')->name('tabletop.sala.estado');   // consulta rápida: tem novidade?
    Route::get('/tabletop/sala/{codigo}/pesado', [TabletopController::class, 'salaPesado'])
        ->where('codigo', '[A-Za-z0-9]{1,20}')->name('tabletop.sala.pesado');   // fundo do mapa e imagens das peças
    Route::post('/tabletop/sala/{codigo}/alterar', [TabletopController::class, 'salaAlterar'])
        ->where('codigo', '[A-Za-z0-9]{1,20}')->name('tabletop.sala.alterar');  // manda só o que mudou

    // Chave de acesso às maldições não catalogadas (registrada antes de /maldicoes/{slug})
    Route::post('/maldicoes/chave', [MaldicaoController::class, 'salvarChave'])
        ->middleware('throttle:20,1')->name('maldicoes.chave');

    // Campanhas: mestre, jogadores, fichas, tabletops e efeitos sonoros
    Route::prefix('/campanhas')->name('campanhas.')->group(function () {
        Route::get('/', [CampanhaController::class, 'index'])->name('index');
        Route::post('/', [CampanhaController::class, 'store'])->name('store');
        Route::get('/{campanha}', [CampanhaController::class, 'show'])->name('show');
        Route::put('/{campanha}', [CampanhaController::class, 'update'])->name('update');
        Route::delete('/{campanha}', [CampanhaController::class, 'destroy'])->name('destroy');

        // Jogadores
        Route::post('/{campanha}/convidar', [CampanhaController::class, 'convidar'])
            ->middleware('throttle:20,1')->name('convidar');
        Route::post('/{campanha}/aceitar', [CampanhaController::class, 'aceitar'])->name('aceitar');
        Route::post('/{campanha}/recusar', [CampanhaController::class, 'recusar'])->name('recusar');
        Route::delete('/{campanha}/sair', [CampanhaController::class, 'sair'])->name('sair');
        Route::delete('/{campanha}/jogadores/{user}', [CampanhaController::class, 'removerJogador'])->name('jogadores.remover');

        // Fichas (e foto da ficha)
        Route::post('/{campanha}/fichas', [CampanhaController::class, 'vincularFicha'])->name('fichas.vincular');
        Route::get('/{campanha}/fichas-estado', [CampanhaController::class, 'fichasEstado'])->name('fichas.estado'); // polling: mestre vê todas, jogador só as suas
        Route::delete('/{campanha}/fichas/{vinculo}', [CampanhaController::class, 'desvincularFicha'])->name('fichas.desvincular');
        Route::get('/{campanha}/fichas/{vinculo}/ver', [CampanhaController::class, 'verFicha'])->name('fichas.ver'); // ficha completa (mestre ou dono)
        Route::get('/{campanha}/fichas/{vinculo}/foto', [CampanhaController::class, 'fotoFicha'])->name('fichas.foto');
        Route::post('/{campanha}/fichas/{vinculo}/foto', [CampanhaController::class, 'enviarFotoFicha'])
            ->middleware('throttle:20,1')->name('fichas.foto.enviar');
        Route::post('/{campanha}/fichas/{vinculo}/vitais', [CampanhaController::class, 'atualizarVitais'])
            ->middleware('throttle:60,1')->name('fichas.vitais'); // PV/Aura/Sanidade pela miniatura (mestre ou dono)
        // Imagem de fundo do banner (mestre envia/remove; membros veem)
        Route::get('/{campanha}/banner', [CampanhaController::class, 'banner'])->name('banner');
        Route::post('/{campanha}/chave-maldicoes', [CampanhaController::class, 'salvarChaveMaldicoes'])->name('chave-maldicoes');
        Route::post('/{campanha}/banner', [CampanhaController::class, 'enviarBanner'])
            ->middleware('throttle:20,1')->name('banner.enviar');
        Route::delete('/{campanha}/banner', [CampanhaController::class, 'removerBanner'])->name('banner.remover');

        // Tabletops
        Route::post('/{campanha}/mesas', [CampanhaController::class, 'vincularMesa'])->name('mesas.vincular');
        Route::post('/{campanha}/mesas/nova', [CampanhaController::class, 'criarMesa'])->name('mesas.criar');
        Route::delete('/{campanha}/mesas/{mesa}', [CampanhaController::class, 'desvincularMesa'])->name('mesas.desvincular');
        Route::post('/{campanha}/mesas/{mesa}/sala', [TabletopController::class, 'salaCampanha'])
            ->middleware('throttle:60,1')->name('mesas.sala'); // painel rápido: sala ao vivo da mesa (o mestre abre na hora)

        // Jogos da campanha: Gladio (cartas e apostas) e Pela Dama (xadrez com peças customizadas)
        Route::get('/{campanha}/jogos', [CampanhaJogoController::class, 'index'])->name('jogos.index');
        Route::get('/{campanha}/jogos/lobby', [CampanhaJogoController::class, 'lobby'])->name('jogos.lobby'); // polling do lobby
        Route::post('/{campanha}/jogos/dinheiro', [CampanhaJogoController::class, 'dinheiro'])->name('jogos.dinheiro');
        Route::post('/{campanha}/jogos/cartas', [CampanhaJogoController::class, 'salvarCarta'])
            ->middleware('throttle:60,1')->name('jogos.cartas.salvar');
        Route::delete('/{campanha}/jogos/cartas/{carta}', [CampanhaJogoController::class, 'excluirCarta'])->name('jogos.cartas.excluir');
        Route::post('/{campanha}/jogos/imagens', [CampanhaJogoController::class, 'enviarImagens'])
            ->middleware('throttle:30,1')->name('jogos.imagens.enviar'); // galeria das cartas
        Route::get('/{campanha}/jogos/imagens/{imagem}', [CampanhaJogoController::class, 'verImagem'])->name('jogos.imagens.ver');
        Route::delete('/{campanha}/jogos/imagens/{imagem}', [CampanhaJogoController::class, 'excluirImagem'])->name('jogos.imagens.excluir');
        Route::post('/{campanha}/jogos/pecas', [CampanhaJogoController::class, 'salvarPeca'])
            ->middleware('throttle:60,1')->name('jogos.pecas.salvar');
        Route::delete('/{campanha}/jogos/pecas/{peca}', [CampanhaJogoController::class, 'excluirPeca'])->name('jogos.pecas.excluir');
        Route::post('/{campanha}/jogos/partidas', [CampanhaJogoController::class, 'criarPartida'])
            ->middleware('throttle:30,1')->name('jogos.partidas.criar');
        Route::get('/{campanha}/jogos/partidas/{partida}', [CampanhaJogoController::class, 'partida'])->name('jogos.partida');
        Route::get('/{campanha}/jogos/partidas/{partida}/estado', [CampanhaJogoController::class, 'estado'])->name('jogos.estado'); // polling
        Route::post('/{campanha}/jogos/partidas/{partida}/aceitar', [CampanhaJogoController::class, 'aceitar'])->name('jogos.aceitar');
        Route::post('/{campanha}/jogos/partidas/{partida}/cancelar', [CampanhaJogoController::class, 'cancelar'])->name('jogos.cancelar');
        Route::post('/{campanha}/jogos/partidas/{partida}/acao', [CampanhaJogoController::class, 'acao'])
            ->middleware('throttle:120,1')->name('jogos.acao');

        // Sessão
        Route::post('/{campanha}/sessao/iniciar', [CampanhaController::class, 'iniciarSessao'])->name('sessao.iniciar');
        Route::post('/{campanha}/sessao/encerrar', [CampanhaController::class, 'encerrarSessao'])->name('sessao.encerrar');
        Route::get('/{campanha}/estado', [CampanhaController::class, 'estado'])->name('estado'); // polling dos jogadores

        // Pote de dados (rolagens públicas da campanha)
        Route::get('/{campanha}/rolagens', [CampanhaRolagemController::class, 'index'])->name('rolagens.index'); // polling: novas rolagens
        Route::post('/{campanha}/rolagens', [CampanhaRolagemController::class, 'store'])
            ->middleware('throttle:40,1')->name('rolagens.store');
        Route::delete('/{campanha}/rolagens', [CampanhaRolagemController::class, 'limpar'])->name('rolagens.limpar'); // limpa o histórico do pote
        // Potes secretos (criador + convidados; o mestre vê todos)
        Route::post('/{campanha}/potes', [CampanhaRolagemController::class, 'criarPote'])
            ->middleware('throttle:20,1')->name('potes.criar');
        Route::delete('/{campanha}/potes/{pote}', [CampanhaRolagemController::class, 'excluirPote'])
            ->whereNumber('pote')->name('potes.excluir');

        // Disputa de catálogo (descrever maldições restritas)
        Route::get('/{campanha}/catalogos', [CampanhaCatalogoController::class, 'index'])->name('catalogos.index');
        Route::post('/{campanha}/catalogos/recalcular', [CampanhaCatalogoController::class, 'recalcular'])
            ->middleware('throttle:6,1')->name('catalogos.recalcular');
        Route::post('/{campanha}/catalogos', [CampanhaCatalogoController::class, 'store'])
            ->middleware('throttle:10,1')->name('catalogos.store');
        Route::delete('/{campanha}/catalogos/{catalogo}', [CampanhaCatalogoController::class, 'destroy'])
            ->whereNumber('catalogo')->name('catalogos.destroy');

        // Rastreador de iniciativa
        Route::get('/{campanha}/iniciativas', [CampanhaIniciativaController::class, 'index'])->name('iniciativas.index');
        Route::post('/{campanha}/iniciativas', [CampanhaIniciativaController::class, 'store'])
            ->middleware('throttle:20,1')->name('iniciativas.store');
        Route::delete('/{campanha}/iniciativas', [CampanhaIniciativaController::class, 'limpar'])->name('iniciativas.limpar');
        Route::delete('/{campanha}/iniciativas/{iniciativa}', [CampanhaIniciativaController::class, 'destroy'])
            ->whereNumber('iniciativa')->name('iniciativas.destroy');

        // Efeitos sonoros
        Route::post('/{campanha}/sons', [CampanhaController::class, 'enviarSom'])->name('sons.enviar');
        Route::post('/{campanha}/sons/parar', [CampanhaController::class, 'pararSom'])->name('sons.parar');
        Route::post('/{campanha}/sons/padrao', [CampanhaController::class, 'adicionarSonsPadrao'])->name('sons.padrao'); // efeitos coringa
        Route::get('/{campanha}/sons/{som}/arquivo', [CampanhaController::class, 'arquivoSom'])->name('sons.arquivo');
        Route::post('/{campanha}/sons/{som}/tocar', [CampanhaController::class, 'tocarSom'])->name('sons.tocar');
        Route::delete('/{campanha}/sons/{som}', [CampanhaController::class, 'excluirSom'])->name('sons.excluir');
    });
});

// ---------- Conteúdo público ----------

// Maldições (catálogo em config/maldicoes.php)
Route::get('/maldicoes', [MaldicaoController::class, 'index'])->name('maldicoes.index');
Route::get('/maldicoes/{slug}', [MaldicaoController::class, 'show'])->name('maldicoes.show');

// Livro do sistema (leitor de PDF com chave de acesso própria)
Route::get('/livro', [LivroController::class, 'index'])->name('livro.index');
Route::get('/livro/acesso', [LivroController::class, 'acesso'])->name('livro.acesso');
Route::post('/livro/acesso', [LivroController::class, 'verificar'])
    ->middleware('throttle:10,1')
    ->name('livro.verificar');
Route::get('/livro/players', [LivroController::class, 'players'])->name('livro.players');
Route::get('/livro/pdf/{versao}', [LivroController::class, 'pdf'])
    ->whereIn('versao', ['completo', 'players'])
    ->name('livro.pdf');
Route::post('/livro/sair', [LivroController::class, 'sair'])->name('livro.sair');

Route::get('/historias/{slug}', [HistoriaController::class, 'ler'])->name('historias.ler');