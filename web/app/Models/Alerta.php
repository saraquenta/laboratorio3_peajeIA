<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alerta extends Model
{
    public const TIPOS = [
        'no_registrada' => 'Placa no registrada (posible evasión)',
        'saldo_insuficiente' => 'Saldo insuficiente',
        'vehiculo_inactivo' => 'Vehículo inactivo',
        'placa_ilegible' => 'Placa ilegible',
        'inferencia_error' => 'Error del servicio de inferencia',
    ];

    protected $table = 'alertas';

    protected $fillable = ['paso_id', 'caseta_id', 'placa', 'tipo', 'mensaje', 'estado', 'atendida_por', 'atendida_en'];

    protected function casts(): array
    {
        return ['atendida_en' => 'datetime'];
    }

    public function paso(): BelongsTo
    {
        return $this->belongsTo(Paso::class);
    }

    public function caseta(): BelongsTo
    {
        return $this->belongsTo(Caseta::class);
    }

    public function atendidaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'atendida_por');
    }

    public function resumen(): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'tipo_texto' => self::TIPOS[$this->tipo] ?? $this->tipo,
            'placa' => $this->placa,
            'mensaje' => $this->mensaje,
            'estado' => $this->estado,
            'caseta' => $this->caseta?->nombre,
            'paso_id' => $this->paso_id,
            'imagen_url' => $this->paso?->imagen_url,
            'fecha' => $this->created_at?->toIso8601String(),
        ];
    }
}
