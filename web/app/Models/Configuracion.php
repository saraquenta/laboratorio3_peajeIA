<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $primaryKey = 'clave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['clave', 'valor'];

    /** Valor guardado en BD; si no existe, el de respaldo (normalmente del .env). */
    public static function obtener(string $clave, $respaldo = null)
    {
        try {
            $valor = static::find($clave)?->valor;
        } catch (\Throwable) {
            $valor = null; // BD aun sin migrar
        }

        return ($valor === null || $valor === '') ? $respaldo : $valor;
    }

    public static function guardar(string $clave, ?string $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }
}
