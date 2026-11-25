<?php

namespace App\Console\Commands;

use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * php artisan peaje:sembrar-si-vacio
 * Para despliegues automaticos (Render/Docker): ejecuta los seeders solo si la tabla usuarios esta vacia,
 * asi los redeploys no duplican ni borran datos. Si el disco es efimero, repone las fotos semilla.
 */
class SembrarSiVacio extends Command
{
    protected $signature = 'peaje:sembrar-si-vacio';

    protected $description = 'Siembra la BD solo si esta vacia y repone las evidencias semilla';

    public function handle(): int
    {
        if (Usuario::count() === 0) {
            $this->info('Tabla usuarios vacía: ejecutando seeders…');
            $this->call('db:seed', ['--force' => true]);
        } else {
            $this->info('La BD ya tiene datos: se omiten los seeders.');
            // Con ADMIN_EMAIL nuevo en el .env se agrega sin tocar el resto
            foreach (config('peaje.admin_emails') as $e) {
                Usuario::firstOrCreate(['email_google' => mb_strtolower($e)], ['rol' => Usuario::ADMIN, 'activo' => true]);
            }
        }
        foreach (array_keys(DatabaseSeeder::FOTOS) as $archivo) {
            $ruta = "evidencias/semilla/{$archivo}";
            $origen = database_path("seeders/evidencias/{$archivo}");
            if (is_file($origen) && ! Storage::disk('public')->exists($ruta)) {
                Storage::disk('public')->put($ruta, file_get_contents($origen));
            }
        }

        return self::SUCCESS;
    }
}
