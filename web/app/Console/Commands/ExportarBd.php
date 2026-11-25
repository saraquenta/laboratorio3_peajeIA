<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * php artisan peaje:exportar-bd [--dir=ruta]
 * Exporta las tablas del sistema a .sql (INSERTs portables: MySQL, PostgreSQL y SQLite) y .json,
 * para entregarlas en la carpeta de recursos / Google Drive. No exporta tokens de sesion ni de API.
 */
class ExportarBd extends Command
{
    protected $signature = 'peaje:exportar-bd {--dir= : carpeta de salida (por defecto storage/app/exports)}';

    protected $description = 'Exporta la base de datos del peaje a SQL y JSON';

    private const TABLAS = ['usuarios', 'casetas', 'tarifas', 'vehiculos', 'pasos', 'alertas', 'configuraciones'];

    public function handle(): int
    {
        $dir = $this->option('dir') ?: storage_path('app/exports');
        File::ensureDirectoryExists($dir);
        $base = $dir.DIRECTORY_SEPARATOR.'peaje_bd_'.now()->format('Ymd_His');
        $pdo = DB::connection()->getPdo();

        $sql = ["-- Sistema Inteligente de Control de Peaje (IAM-811) - exportacion ".now()->toIso8601String(),
            '-- Motor de origen: '.DB::connection()->getDriverName().'. Ejecutar despues de: php artisan migrate', ''];
        $json = ['exportado' => now()->toIso8601String(), 'tablas' => []];

        foreach (self::TABLAS as $tabla) {
            $filas = DB::table($tabla)->get()->map(fn ($f) => (array) $f)->all();
            if ($tabla === 'configuraciones') {   // no exportar el token de inferencia
                $filas = array_values(array_filter($filas, fn ($f) => ! str_contains(strtoupper($f['clave']), 'TOKEN')));
            }
            if ($tabla === 'usuarios') {
                $filas = array_map(fn ($f) => array_merge($f, ['remember_token' => null]), $filas);
            }
            $json['tablas'][$tabla] = $filas;
            $sql[] = "-- {$tabla}: ".count($filas).' filas';
            foreach ($filas as $f) {
                $cols = implode(', ', array_keys($f));
                $vals = implode(', ', array_map(fn ($v) => match (true) {
                    $v === null => 'NULL',
                    is_bool($v) => $v ? 'TRUE' : 'FALSE',
                    is_int($v) || is_float($v) => (string) $v,
                    default => $pdo->quote((string) $v),
                }, array_values($f)));
                $sql[] = "INSERT INTO {$tabla} ({$cols}) VALUES ({$vals});";
            }
            $sql[] = '';
        }

        File::put($base.'.sql', implode("\n", $sql));
        File::put($base.'.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        foreach ($json['tablas'] as $t => $f) {
            $this->line(str_pad($t, 16).count($f).' filas');
        }
        $this->info("Exportado: {$base}.sql y {$base}.json");

        return self::SUCCESS;
    }
}
