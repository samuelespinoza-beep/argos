<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
            $table->unsignedInteger('vendidos')->nullable();
            $table->unsignedInteger('valoraciones')->nullable();
            $table->decimal('estrellas', 3, 1)->nullable();
            $table->unsignedInteger('stock_maximo')->nullable();
            $table->string('estado', 40)->nullable();
            $table->date('fecha_termino')->nullable();
            $table->jsonb('extra')->nullable();
            $table->timestamps();

            $table->unique(['oferta_id', 'fecha']);
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oferta_snapshots');
    }
};
