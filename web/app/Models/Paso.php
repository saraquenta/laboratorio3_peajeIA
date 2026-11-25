<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paso extends Model
{
    protected $table = 'pasos';

    protected $fillable = ['caseta_id', 'vehiculo_id', 'usuario_id', 'placa_leida', 'placa', 'confianza_ocr', 'categoria',
        'ejes', 'pasajeros', 'monto', 'estado', 'imagen_url', 'latencia_ms', 'datos_inferencia'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'confianza_ocr' => 'float', 'latencia_ms' => 'float',
            'datos_inferencia' => 'array', 'ejes' => 'integer', 'pasajeros' => 'integer'];
    }

    public function caseta(): BelongsTo
    {
        return $this->belongsTo(Caseta::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class);
    }

    /** Representacion compacta para el panel en tiempo real, la app movil y el hardware. */
    public function resumen(): array
    {
        return [
            'id' => $this->id,
            'placa' => $this->placa,
            'placa_leida' => $this->placa_leida,
            'confianza_ocr' => $this->confianza_ocr,
            'categoria' => $this->categoria,
            'ejes' => $this->ejes,
            'pasajeros' => $this->pasajeros,
            'monto' => (float) $this->monto,
            'estado' => $this->estado,
            'caseta' => $this->caseta?->nombre,
            'caseta_id' => $this->caseta_id,
            'propietario' => $this->vehiculo?->propietario,
            'registrado' => $this->vehiculo_id !== null,
            'imagen_url' => $this->imagen_url,
            'latencia_ms' => $this->latencia_ms,
            'fecha' => $this->created_at?->toIso8601String(),
        ];
    }
}
