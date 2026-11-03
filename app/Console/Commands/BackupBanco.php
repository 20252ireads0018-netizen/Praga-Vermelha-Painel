<?php

namespace App\Console\Commands;

use App\Support\Backup;
use Illuminate\Console\Command;

class BackupBanco extends Command
{
    protected $signature = 'backup:banco';
    protected $description = 'Gera um backup do banco em storage/app/backups (.sql.gz) e apaga os mais antigos';

    public function handle(): int
    {
        $nome = Backup::gerar();
        $this->info("Backup gerado: storage/app/backups/{$nome}");

        return self::SUCCESS;
    }
}
