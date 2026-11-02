<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofertas', function (Blueprint $table) {
            $table->id();
            $table->string('sitio', 40);
            $table->string('external_id', 40);
            $table->string('titulo', 500);
            $table->string('marca')->nullable();
            $table->string('categoria')->nullable();
            $table->string('subcategoria')->nullable();
            $table->string('tipo', 40)->nullable();
            $table->string('url', 1000);
            $table->string('imagen', 1000)->nullable();
            $table->boolean('activa')->default(true);
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
