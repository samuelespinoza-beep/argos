<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una "foto" por oferta y por dia. Comparando dos dias se obtienen las
     * ventas reales del dia (vendidos es acumulado) y los cambios de precio.
     */
    public function up(): void
    {
        Schema::create('oferta_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oferta_id')->constrained('ofertas')->cascadeOnDelete();
            $table->foreignId('corrida_id')->nullable()->constrained('corridas')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('precio_oferta', 10, 2)->nullable();
            $table->decimal('precio_normal', 10, 2)->nullable();
            $table->unsignedSmallInteger('descuento_pct')->nullable();
            $table->unsignedInteger('vendidos')->nullable();    // acumulado segun el sitio
            $table->unsignedInteger('valoraciones')->nullable();
            $table->decimal('estrellas', 3, 1)->nullable();     // 0 a 5
            $table->unsignedInteger('stock_maximo')->nullable();
            $table->string('estado', 40)->nullable();           // disponible | agotado...
            $table->date('fecha_termino')->nullable();
            $table->jsonb('extra')->nullable();                 // campos propios de cada sitio
            $table->timestamps();

            $table->unique(['oferta_id', 'fecha']);              // si corre 2 veces el mismo dia, se actualiza
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oferta_snapshots');
    }
};
