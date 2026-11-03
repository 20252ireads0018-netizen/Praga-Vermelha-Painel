<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backup do banco em SQL compactado (.sql.gz), sem depender de mysqldump nem de shell (a HostGator não dá).
 * Os arquivos ficam em storage/app/backups, fora da pasta pública; só o administrador baixa pelo painel /admin/backup
 * (ou pelo FTP). Um backup automático por dia é gerado depois de uma resposta qualquer do site (sem cron).
 * Restaurar: importar o .sql.gz no phpMyAdmin (aba Importar) do banco vazio ou do banco atual (as tabelas são recriadas).
 */
class Backup
{
    /** Quantos backups ficam guardados (os mais antigos são apagados). */
    public const MANTER = 14;

    /** Intervalo do backup automático, em segundos. */
    public const INTERVALO = 86400;

    private const LOTE = 500;

    public static function pasta(): string
    {
        return storage_path('app/backups');
    }

    /** Lista os backups do mais novo para o mais antigo: [['nome', 'bytes', 'quando'], ...]. */
    public static function lista(): array
    {
        $arquivos = glob(self::pasta() . '/banco-*.sql.gz') ?: [];
        rsort($arquivos);

        return array_map(fn ($a) => ['nome' => basename($a), 'bytes' => filesize($a), 'quando' => filemtime($a)], $arquivos);
    }

    /** Caminho de um backup pelo nome, ou null se o nome não for de um backup existente (impede ../ e afins). */
    public static function caminho(string $nome): ?string
    {
        if (!preg_match('/^banco-\d{8}-\d{6}\.sql\.gz$/', $nome)) {
            return null;
        }
        $caminho = self::pasta() . '/' . $nome;

        return is_file($caminho) ? $caminho : null;
    }

    /** Gera um backup agora e devolve o nome do arquivo. */
    public static function gerar(): string
    {
        @set_time_limit(600);
        $pasta = self::pasta();
        if (!is_dir($pasta)) {
            mkdir($pasta, 0750, true);
        }
        // a pasta nunca deve ser servida, mesmo se alguém a mover para dentro de public/
        if (!is_file($pasta . '/.htaccess')) {
            file_put_contents($pasta . '/.htaccess', "Require all denied\nDeny from all\n");
        }

        // conecta antes de abrir o arquivo: sem banco, não sobra arquivo pela metade
        $con = DB::connection();
        $mysql = $con->getDriverName() === 'mysql';
        $pdo = $con->getPdo();

        $nome = 'banco-' . date('Ymd-His') . '.sql.gz';
        $temp = $pasta . '/.' . $nome . '.parcial';
        $gz = gzopen($temp, 'wb6');
        if ($gz === false) {
            throw new \RuntimeException('Não foi possível criar o arquivo de backup.');
        }

        try {
            if ($mysql) {
                // retrato consistente de todas as tabelas no mesmo instante, sem travar o site
                $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
                $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            }
            self::escreverDump($gz);
        } catch (\Throwable $e) {
            gzclose($gz);
            @unlink($temp);
            throw $e;
        } finally {
            if ($mysql) {
                $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                $pdo->exec('COMMIT');
            }
        }
        gzclose($gz);
        rename($temp, $pasta . '/' . $nome);
        self::podar();

        return $nome;
    }

    /** Backup automático: no máximo um por INTERVALO, um processo por vez, e nunca derruba a requisição. */
    public static function automatico(): void
    {
        try {
            $pasta = self::pasta();
            // uma consulta de data de arquivo por requisição; a lista só é lida quando o dia virou
            if (time() - (int) @filemtime($pasta . '/.trava') < self::INTERVALO) {
                return;
            }
            if (!is_dir($pasta)) {
                mkdir($pasta, 0750, true);
            }
            $trava = fopen($pasta . '/.trava', 'c');
            if (!$trava || !flock($trava, LOCK_EX | LOCK_NB)) {
                return;
            }
            try {
                clearstatcache();
                $ultimo = self::lista()[0]['quando'] ?? 0;
                if (time() - $ultimo >= self::INTERVALO) {
                    self::gerar();
                }
            } finally {
                flock($trava, LOCK_UN);
                fclose($trava);
                // marca a tentativa (com ou sem sucesso) para não insistir a cada requisição
                @touch($pasta . '/.trava');
            }
        } catch (\Throwable $e) {
            Log::warning('Backup automático falhou: ' . $e->getMessage());
        }
    }

    private static function podar(): void
    {
        foreach (array_slice(self::lista(), self::MANTER) as $velho) {
            @unlink(self::pasta() . '/' . $velho['nome']);
        }
        foreach (glob(self::pasta() . '/.*.parcial') ?: [] as $sobra) {
            if (filemtime($sobra) < time() - 3600) {
                @unlink($sobra);
            }
        }
    }

    /** @param resource $gz */
    private static function escreverDump($gz): void
    {
        $con = DB::connection();
        $pdo = $con->getPdo();
        $mysql = $con->getDriverName() === 'mysql';
        $q = fn (string $id) => $mysql ? '`' . str_replace('`', '``', $id) . '`' : '"' . str_replace('"', '""', $id) . '"';

        gzwrite($gz, "-- Backup do Praga Vermelha RPG em " . date('Y-m-d H:i:s') . "\n"
            . ($mysql ? "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n" : "PRAGMA foreign_keys=OFF;\n\n"));

        $tabelas = $mysql
            ? array_map(fn ($l) => array_values((array) $l)[0], $con->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
            : array_map(fn ($l) => $l->name, $con->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"));

        foreach ($tabelas as $tabela) {
            $criar = $mysql
                ? array_values((array) $con->selectOne('SHOW CREATE TABLE ' . $q($tabela)))[1]
                : $con->selectOne('SELECT sql FROM sqlite_master WHERE name = ?', [$tabela])->sql;
            gzwrite($gz, "DROP TABLE IF EXISTS {$q($tabela)};\n{$criar};\n\n");

            // linha a linha (sem buffer no MySQL) e gravado em lotes: tabelas grandes não ficam inteiras na memória
            $stmt = $pdo->query('SELECT * FROM ' . $q($tabela));
            $lote = [];
            $colunas = null;
            while (($linha = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
                $colunas ??= implode(', ', array_map($q, array_keys($linha)));
                $lote[] = '(' . implode(', ', array_map(fn ($v) => self::valor($pdo, $v), $linha)) . ')';
                if (count($lote) === self::LOTE) {
                    gzwrite($gz, "INSERT INTO {$q($tabela)} ({$colunas}) VALUES\n" . implode(",\n", $lote) . ";\n");
                    $lote = [];
                }
            }
            $stmt->closeCursor();
            if ($lote) {
                gzwrite($gz, "INSERT INTO {$q($tabela)} ({$colunas}) VALUES\n" . implode(",\n", $lote) . ";\n");
            }
            gzwrite($gz, "\n");
        }

        gzwrite($gz, $mysql ? "SET FOREIGN_KEY_CHECKS=1;\n" : "PRAGMA foreign_keys=ON;\n");
    }

    private static function valor(\PDO $pdo, mixed $v): string
    {
        if ($v === null) {
            return 'NULL';
        }
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }
        $v = (string) $v;
        // binário (fotos guardadas no banco etc.) vai em hexadecimal para não corromper o arquivo
        if ($v !== '' && !mb_check_encoding($v, 'UTF-8')) {
            return "X'" . bin2hex($v) . "'";
        }

        return $pdo->quote($v);
    }
}
