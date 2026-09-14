<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una "persona" es la entidad que en realidad queremos evitar duplicar.
     * Cada persona puede tener, a lo largo del tiempo, más de un documento
     * de identificación registrado (por ejemplo: renovó su INE, o subió su
     * pasaporte además de su CURP), por eso viven en tablas separadas.
     */
    public function up(): void
    {
        // Nombre de tabla "people" (no "persons"): es el plural irregular que
        // Eloquent espera por convención para el modelo Person, y también el
        // que infiere Blueprint::foreignId('person_id')->constrained() en la
        // migración de documents.
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            // CURP es el identificador único de personas físicas en México;
            // no todos los documentos la traen, así que es opcional, pero
            // cuando existe es el criterio de duplicado más confiable.
            $table->string('curp', 18)->nullable()->unique();
            $table->date('birth_date')->nullable();
            $table->timestamps();

            $table->index(['full_name', 'birth_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
