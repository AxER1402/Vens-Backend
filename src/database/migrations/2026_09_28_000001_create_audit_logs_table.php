<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La bitácora de auditoría: quién entró, quién intentó entrar y no pudo, y
     * quién tocó lo que no se debería tocar a la ligera (cuentas, anulaciones,
     * desactivaciones, ajustes de la clínica).
     *
     * Solo se escribe y se lee: una fila de auditoría que se puede corregir no
     * sirve de prueba de nada, por eso no lleva updated_at.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Quién lo hizo. SET NULL y no CASCADE: borrar una cuenta no puede
            // borrar el rastro de lo que hizo. Por eso se copian también el
            // nombre y el correo del momento, que son lo que se lee en pantalla.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('usuario_nombre')->nullable();
            // En un intento fallido de inicio de sesión puede no haber cuenta:
            // entonces esto es el correo que se escribió.
            $table->string('usuario_correo')->nullable();
            $table->string('usuario_rol', 30)->nullable();

            $table->string('evento', 50);
            $table->string('categoria', 30);
            $table->string('descripcion', 500);

            // Sobre qué registro recayó ('user', 'invoice'…), si aplica.
            $table->string('sujeto_tipo', 50)->nullable();
            $table->unsignedBigInteger('sujeto_id')->nullable();

            // {campo: {antes, despues}} u otros datos del evento.
            $table->json('cambios')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['categoria', 'created_at']);
            $table->index(['evento', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
