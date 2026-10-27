<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos "fijos" de cada oferta (una fila por oferta y sitio). Lo que cambia
     * dia a dia (precio, vendidos...) va en oferta_snapshots.
     */
    public function up(): void
    {
        Schema::create('ofertas', function (Blueprint $table) {
            $table->id();
            $table->string('sitio', 40);
            $table->string('external_id', 40);                  // id de la oferta en el sitio
            $table->string('titulo', 500);
            $table->string('marca')->nullable();
            $table->string('categoria')->nullable();
            $table->string('subcategoria')->nullable();
            $table->string('tipo', 40)->nullable();             // producto | servicio | viajes (si el sitio lo da)
            $table->string('url', 1000);
            $table->string('imagen', 1000)->nullable();
            $table->boolean('activa')->default(true);           // false = ya no aparece en el sitio
            $table->date('primera_vez_vista');
            $table->date('ultima_vez_vista');
            $table->timestamps();

            $table->unique(['sitio', 'external_id']);
            $table->index(['sitio', 'activa']);
            $table->index('marca');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofertas');
    }
};
