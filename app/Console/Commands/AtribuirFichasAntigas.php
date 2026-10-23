<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AtribuirFichasAntigas extends Command
{
    protected $signature = 'fichas:atribuir {email : E-mail da conta que vai receber as fichas sem dono}';

    protected $description = 'Atribui a uma conta todas as fichas e mesas que ainda não têm dono (user_id nulo).';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Nenhuma conta encontrada com esse e-mail.');
            return self::FAILURE;
        }

        $models = array_filter([
            \App\Models\Personagem::class,
            \App\Models\Mesa::class,
        ], 'class_exists');

        foreach ($models as $classe) {
            $total = $classe::withoutGlobalScopes()
                ->whereNull('user_id')
                ->update(['user_id' => $user->id]);

            $this->info(class_basename($classe) . ": {$total} registro(s) atribuído(s) a {$user->email}.");
        }

        return self::SUCCESS;
    }
}