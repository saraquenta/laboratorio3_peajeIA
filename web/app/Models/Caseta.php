<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caseta extends Model
{
    protected $table = 'casetas';

    protected $fillable = ['codigo', 'nombre', 'ubicacion', 'activa'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function pasos(): HasMany
    {
        return $this->hasMany(Paso::class);
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class);
    }
}
