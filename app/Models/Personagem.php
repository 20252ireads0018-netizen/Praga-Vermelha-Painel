<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\PertenceAoUsuario;

class Personagem extends Model
{
    use PertenceAoUsuario;
    protected $table = 'fichas';

    protected $fillable = [
        'usuario_id', 'nome', 'raca', 'maldicoes', 'idade',
        'profissao', 'nivel_mundano', 'experiencia_mundana', 'porte_fisico', 'be',
        'forca', 'constituicao', 'destreza', 'conhecimento', 'carisma', 'percepcao', 'alma', 'sorte',
        'habilidades', 'itens', 'descricao',
        'vida_atual', 'vida_maxima', 'sanidade_atual', 'sanidade_maxima', 'aura_atual', 'aura_maxima',
        'vitais_extras', 'anotacoes',
    ];

    protected $casts = [
        'habilidades' => 'array',
        'itens' => 'array',
        'vitais_extras' => 'array',
        'anotacoes' => 'array',
    ];

    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_id');
    }
}