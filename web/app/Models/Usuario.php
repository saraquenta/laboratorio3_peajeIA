<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Usuario de la lista blanca. Solo inicia sesion con Google (sin contrasena propia).
 */
class Usuario extends Authenticatable
{
    use HasApiTokens, Notifiable;

    public const ADMIN = 'administrador';
    public const OPERADOR = 'operador';

    protected $table = 'usuarios';

    protected $fillable = ['nombre', 'email_google', 'google_id', 'avatar_url', 'rol', 'activo', 'ultimo_acceso'];

    protected $hidden = ['remember_token', 'google_id'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'ultimo_acceso' => 'datetime'];
    }

    public function esAdmin(): bool
    {
        return $this->rol === self::ADMIN;
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function pasos(): HasMany
    {
        return $this->hasMany(Paso::class);
    }

    /** Busca un usuario autorizado (activo) por su correo de Google. */
    public static function autorizado(?string $email): ?self
    {
        if (! $email) {
            return null;
        }

        return static::where('email_google', mb_strtolower(trim($email)))->where('activo', true)->first();
    }
}
