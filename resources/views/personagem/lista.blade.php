@extends('layout')

@section('conteudo')
<style>
    /* Importar / baixar fichas (escopado para não afetar outras telas) */
    .importar-ficha { margin: 0 0 1.25rem; }
    .importar-ficha .dropzone {
        display: flex; flex-direction: column; align-items: center; gap: .4rem;
        padding: 1.25rem 1rem; text-align: center; cursor: pointer;
        border: 2px dashed rgba(189, 3, 3, .45); border-radius: 10px;
        background: rgba(255, 255, 255, .03);
        transition: background .15s, border-color .15s;
    }
    .importar-ficha .dropzone:hover,
    .importar-ficha .dropzone.arrastando {
        background: rgba(189, 3, 3, .12); border-color: rgba(189, 3, 3, .9);
    }
    .importar-ficha .dropzone strong { font-size: 1rem; }
    .importar-ficha .dropzone span { font-size: .85rem; opacity: .7; }
    .importar-ficha input[type="file"] { display: none; }
    .importar-ficha .importar-status { margin-top: .5rem; text-align: center; font-size: .9rem; opacity: .8; }

    .aviso-importacao {
        margin: 0 0 1rem; padding: .7rem .9rem; border-radius: 8px; font-size: .9rem;
        border: 1px solid rgba(34, 160, 90, .6); background: rgba(34, 160, 90, .12);
    }
    .aviso-importacao.erro { border-color: rgba(185, 28, 28, .7); background: rgba(185, 28, 28, .12); }
    .aviso-importacao ul { margin: .3rem 0 0; padding-left: 1.2rem; }

    .personagem-card .form-baixar { margin: 0; }
    .personagem-card .botao-baixar {
        display: inline-flex; align-items: center; gap: .3rem;
        margin-top: .6rem; padding: .3rem .7rem; cursor: pointer;
        font: inherit; font-size: .8rem; color: inherit;
        background: rgba(255, 255, 255, .04);
        border: 1px solid rgba(189, 3, 3, .45); border-radius: 6px;
    }
    .personagem-card .botao-baixar:hover { background: rgba(189, 3, 3, .2); }
</style>

<div class="container">
    <div class="card ficha-card">

        <div class="ficha-header">
            <span class="selo">△</span>
            <h1>Fichas de Agentes</h1>
            <p class="ficha-subtitulo">Arquivo da Organização</p>
        </div>

        <hr style="width:100%">

        @if(session('fichas_importadas'))
            <div class="aviso-importacao">
                @php $importadas = (array) session('fichas_importadas'); @endphp
                {{ count($importadas) === 1 ? 'Ficha importada com sucesso:' : count($importadas) . ' fichas importadas com sucesso:' }}
                <ul>
                    @foreach($importadas as $nomeImportado)
                        <li>{{ $nomeImportado }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('erros_importacao'))
            <div class="aviso-importacao erro">
                Alguns arquivos não foram importados:
                <ul>
                    @foreach((array) session('erros_importacao') as $erro)
                        <li>{{ $erro }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Enviar ficha: clique para escolher ou arraste o arquivo para cá --}}
        <form action="{{ route('personagens.importar') }}" method="POST" enctype="multipart/form-data"
              class="importar-ficha" id="form-importar">
            @csrf
            <label class="dropzone" id="dropzone" for="input-importar">
                <strong>Importar ficha</strong>
                <span>Arraste um arquivo de ficha (.json) para cá ou clique para escolher</span>
            </label>
            <input type="file" id="input-importar" name="arquivos[]" accept=".json,application/json" multiple>
            <p class="importar-status" id="importar-status" hidden></p>
        </form>

        @if($personagens->isEmpty())
            <p class="lista-vazia">Nenhuma ficha foi criada ainda.</p>
        @else
            <div class="lista-fichas-grid">
                @foreach($personagens as $personagem)
                    <a href="{{ route('personagens.show', $personagem->id) }}" class="personagem-card-link">
                        <div class="personagem-card">
                            <form action="{{ route('personagens.destroy', $personagem->id)}}" method="POST" onsubmit="return confirm('Tem certeza que deseja excluir esta ficha? Essa ação não pode ser desfeita.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="botao-fechar">X</button>
                            </form>
                            @if($personagem->profissao && $personagem->profissao !== 'Selecione')
                                <span class="personagem-card-tag">{{ $personagem->profissao }}</span>
                            @endif
                            <h3 class="personagem-card-nome">{{ $personagem->nome }}</h3>
                            <div class="personagem-card-info">
                                <span>{{ $personagem->raca ?? 'Raça não definida' }}</span>
                                <span>{{ $personagem->idade ?? '?' }} anos</span>
                                <span>Nível {{ $personagem->nivel_mundano ?? 1 }}</span>
                            </div>
                            <form action="{{ route('personagens.exportar', $personagem->id) }}" method="GET" class="form-baixar">
                                <button type="submit" class="botao-baixar" title="Baixar esta ficha como arquivo"><x-icone n="baixar"/> Baixar ficha</button>
                            </form>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

    </div>
</div>

<script>
    (function () {
        var form = document.getElementById('form-importar');
        var input = document.getElementById('input-importar');
        var zona = document.getElementById('dropzone');
        var status = document.getElementById('importar-status');
        if (!form || !input || !zona) return;

        function enviar() {
            if (!input.files || input.files.length === 0) return;
            status.hidden = false;
            status.textContent = 'Importando ' + input.files.length + ' arquivo(s)…';
            form.submit();
        }

        input.addEventListener('change', enviar);

        // Impede que o navegador abra o arquivo se ele for solto fora da área.
        ['dragover', 'drop'].forEach(function (evento) {
            window.addEventListener(evento, function (e) {
                if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1) {
                    e.preventDefault();
                }
            });
        });

        ['dragenter', 'dragover'].forEach(function (evento) {
            zona.addEventListener(evento, function (e) {
                e.preventDefault();
                zona.classList.add('arrastando');
            });
        });
        ['dragleave', 'drop'].forEach(function (evento) {
            zona.addEventListener(evento, function (e) {
                e.preventDefault();
                zona.classList.remove('arrastando');
            });
        });

        zona.addEventListener('drop', function (e) {
            var arquivos = e.dataTransfer ? e.dataTransfer.files : null;
            if (!arquivos || arquivos.length === 0) return;
            try {
                input.files = arquivos;
            } catch (erro) {
                var dt = new DataTransfer();
                Array.prototype.forEach.call(arquivos, function (a) { dt.items.add(a); });
                input.files = dt.files;
            }
            enviar();
        });
    })();
</script>
@endsection