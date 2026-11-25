<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casetas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre');
            $table->string('ubicacion')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('tarifas', function (Blueprint $table) {
            $table->id();
            $table->enum('categoria', ['moto', 'auto', 'minibus', 'camion', 'bus'])->unique();
            $table->decimal('monto_base', 10, 2);
            $table->unsignedTinyInteger('ejes_incluidos')->default(2);
            $table->decimal('monto_eje_adicional', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('placa', 10)->unique();          // normalizada: 1234ABC
            $table->string('propietario');
            $table->enum('categoria', ['moto', 'auto', 'minibus', 'camion', 'bus']);
            $table->unsignedTinyInteger('ejes')->default(2);
            $table->decimal('saldo', 10, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('pasos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caseta_id')->constrained('casetas');
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('placa_leida', 20)->nullable();       // texto crudo del OCR
            $table->string('placa', 10)->nullable()->index();   // normalizada
            $table->decimal('confianza_ocr', 5, 3)->nullable();
            $table->string('categoria', 20)->nullable();
            $table->unsignedTinyInteger('ejes')->nullable();
            $table->unsignedTinyInteger('pasajeros')->nullable();
            $table->decimal('monto', 10, 2)->default(0);
            $table->enum('estado', ['cobrado', 'alerta'])->default('cobrado');
            $table->string('imagen_url', 500)->nullable();
            $table->decimal('latencia_ms', 10, 1)->nullable();
            $table->json('datos_inferencia')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paso_id')->nullable()->constrained('pasos')->nullOnDelete();
            $table->foreignId('caseta_id')->nullable()->constrained('casetas')->nullOnDelete();
            $table->string('placa', 10)->nullable()->index();
            $table->enum('tipo', ['no_registrada', 'saldo_insuficiente', 'vehiculo_inactivo', 'placa_ilegible', 'inferencia_error']);
            $table->string('mensaje', 500);
            $table->enum('estado', ['pendiente', 'atendida', 'descartada'])->default('pendiente');
            $table->foreignId('atendida_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('atendida_en')->nullable();
            $table->timestamps();
        });

        // Configuracion editable desde el panel (p. ej. INFERENCE_URL de RunPod/Colab sin tocar el .env)
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->string('clave', 100)->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
        Schema::dropIfExists('alertas');
        Schema::dropIfExists('pasos');
        Schema::dropIfExists('vehiculos');
        Schema::dropIfExists('tarifas');
        Schema::dropIfExists('casetas');
    }
};
