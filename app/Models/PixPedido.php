<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pedido de compra do livro do sistema por Pix direto (ver App\Support\Pix). */
class PixPedido extends Model
{
    protected $table = 'pix_pedidos';

    protected $fillable = ['user_id', 'codigo', 'valor', 'status', 'pagador', 'informado_em', 'decidido_em', 'decidido_por'];

    protected $casts = [
        'valor'        => 'decimal:2',
        'informado_em' => 'datetime',
        'decidido_em'  => 'datetime',
    ];

    /** Ainda em aberto: esperando o pagamento ou a conferência. */
    public const ABERTOS = ['aguardando', 'informado'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
