<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Imagem da galeria da campanha, usada como arte das cartas de Gladio. */
class GladioImagem extends Model
{
    protected $table = 'gladio_imagens';

    protected $fillable = ['campanha_id', 'pasta', 'nome', 'caminho'];

    public function paraLista(): array
    {
        return [
            'id'    => $this->id,
            'pasta' => (string) $this->pasta,
            'nome'  => $this->nome,
            'url'   => route('campanhas.jogos.imagens.ver', [$this->campanha_id, $this->id]) . '?v=' . optional($this->updated_at)->timestamp,
        ];
    }
}
