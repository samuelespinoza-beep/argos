<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corridas', function (Blueprint $table) {
            $table->id();
            $table->string('sitio', 40)->index();
            $table->string('estado', 20)->default('en_curso');
            $table->timestamp('iniciada_at');
            $table->timestamp('terminada_at')->nullable();
            $table->unsignedInteger('ofertas_encontradas')->default(0);
            $table->unsignedInteger('ofertas_nuevas')->default(0);
            $table->unsignedInteger('ofertas_retiradas')->default(0);
            $table->unsignedInteger('errores')->default(0);
            $table->text('mensaje')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corridas');
    }
};
