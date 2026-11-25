<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tarifa extends Model
{
    public const CATEGORIAS = ['moto', 'auto', 'minibus', 'camion', 'bus'];

    public const NOMBRES = ['moto' => 'Moto', 'auto' => 'Auto', 'minibus' => 'Minibús', 'camion' => 'Camión', 'bus' => 'Bus'];

    protected $table = 'tarifas';

    protected $fillable = ['categoria', 'monto_base', 'ejes_incluidos', 'monto_eje_adicional'];

    protected function casts(): array
    {
        return ['monto_base' => 'decimal:2', 'monto_eje_adicional' => 'decimal:2', 'ejes_incluidos' => 'integer'];
    }

    /** Tarifa = base + (ejes por encima de los incluidos) x monto por eje adicional. */
    public function calcular(?int $ejes): float
    {
        $extra = max(0, (int) ($ejes ?? $this->ejes_incluidos) - $this->ejes_incluidos);

        return round((float) $this->monto_base + $extra * (float) $this->monto_eje_adicional, 2);
    }
}
