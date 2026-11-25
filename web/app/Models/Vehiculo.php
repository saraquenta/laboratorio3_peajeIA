<?php

namespace App\Models;

use App\Services\PlacaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehiculo extends Model
{
    protected $table = 'vehiculos';

    protected $fillable = ['placa', 'propietario', 'categoria', 'ejes', 'saldo', 'activo'];

    protected function casts(): array
    {
        return ['saldo' => 'decimal:2', 'activo' => 'boolean', 'ejes' => 'integer'];
    }

    /** La placa siempre se guarda normalizada (mayusculas, sin guiones ni espacios). */
    public function setPlacaAttribute($valor): void
    {
        $this->attributes['placa'] = PlacaService::limpiar((string) $valor);
    }

    public function pasos(): HasMany
    {
        return $this->hasMany(Paso::class);
    }
}
