<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Database backups (replacement for AdminSettings::generateBackup / database_list / deleteBackup).
 *
 * Differences from the old code: no hard-coded credentials (uses the app's own connection), files live in
 * storage/app/backups (never inside the web root), file names are validated (no path traversal),
 * NULL values stay NULL, and the dump streams to disk in batches (the ledger table has ~1M rows).
 */
class BackupService
{
    public function dir(): string
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        return $dir;
    }

    /** @return string[] file names, newest first */
    public function list(): array
    {
        $files = [];
        foreach (scandir($this->dir()) as $f) {
            if ($this->validName($f)) {
                $files[$f] = filemtime($this->dir().DIRECTORY_SEPARATOR.$f);
            }
        }
        arsort($files);

        return array_keys($files);
    }

    public function validName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_\-]+\.sql$/', $name);
    }

    public function path(string $name): ?string
    {
        if (! $this->validName($name)) {
            return null;
        }
        $p = $this->dir().DIRECTORY_SEPARATOR.$name;

        return is_file($p) ? $p : null;
    }

    public function delete(string $name): bool
    {
        $p = $this->path($name);

        return $p !== null && unlink($p);
    }

    public function generate(): string
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $name = $db.'_backup_'.date('d-m-Y_H_i_s').'.sql';
        $path = $this->dir().DIRECTORY_SEPARATOR.$name;
        $fh = fopen($path, 'wb');
        if (! $fh) {
            throw new RuntimeException('Cannot write backup file');
        }
        $pdo = DB::connection()->getPdo();

        fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n");
        foreach (DB::select("SHOW FULL TABLES WHERE Table_Type != 'VIEW'") as $row) {
            $table = (string) array_values((array) $row)[0];
            $q = '`'.str_replace('`', '``', $table).'`';
            $create = (array) DB::selectOne("SHOW CREATE TABLE {$q}");
            fwrite($fh, "\n\nDROP TABLE IF EXISTS {$q};\n".array_values($create)[1].";\n\n");

            $cols = DB::select("SHOW COLUMNS FROM {$q}");
            $pk = null;
            $pks = array_values(array_filter($cols, fn ($c) => $c->Key === 'PRI'));
            if (count($pks) === 1 && str_contains($pks[0]->Type, 'int')) {
                $pk = $pks[0]->Field;
            }

            $writeBatch = function (array $rows) use ($fh, $q, $pdo) {
                if (! $rows) {
                    return;
                }
                $vals = [];
                foreach ($rows as $r) {
                    $vals[] = '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values((array) $r))).')';
                }
                fwrite($fh, "INSERT INTO {$q} VALUES ".implode(",\n", $vals).";\n");
            };

            if ($pk !== null) {
                $last = null;
                do {
                    $query = DB::table($table)->orderBy($pk)->limit(1000);
                    if ($last !== null) {
                        $query->where($pk, '>', $last);
                    }
                    $rows = $query->get()->all();
                    $writeBatch($rows);
                    $last = $rows ? end($rows)->{$pk} : null;
                } while (count($rows) === 1000);
            } else {
                foreach (array_chunk(DB::table($table)->get()->all(), 1000) as $chunk) {
                    $writeBatch($chunk);
                }
            }
        }
        fwrite($fh, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);

        return $name;
    }
}
