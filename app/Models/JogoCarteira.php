<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Dinheiro de um membro dentro da campanha (apostado no Gladio). */
class JogoCarteira extends Model
{
    protected $table = 'jogo_carteiras';

    protected $fillable = ['campanha_id', 'user_id', 'dinheiro'];

    protected $casts = ['dinheiro' => 'integer'];
}
