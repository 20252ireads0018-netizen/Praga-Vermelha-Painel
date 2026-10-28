<?php

namespace App\Console\Commands;

use App\Models\Personagem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ReparaFichas extends Command
{
    protected $signature = 'fichas:reparar';
    protected $description = 'Roda migrations pendentes e recupera a ameaça das fichas a partir do campo maldicoes';

    public function handle(): int
    {
        Artisan::call('migrate', ['--force' => true]);

        $corrigidas = 0;

        // withoutGlobalScopes: o trait PertenceAoUsuario pode filtrar por usuário logado,
        // o que não existe quando o comando roda no cron.
        Personagem::withoutGlobalScopes()->where('ameaca', 0)->each(function (Personagem $p) use (&$corrigidas) {
            if (!preg_match_all('/Amea[çc]a\s*(\d+)/iu', (string) $p->maldicoes, $m)) {
                return;
            }
            $maior = (int) max($m[1]);
            if ($maior > 0) {
                $p->ameaca = $maior;
                $p->save();
                $corrigidas++;
            }
        });

        $this->info("Fichas corrigidas: {$corrigidas}");
        return self::SUCCESS;
    }
}