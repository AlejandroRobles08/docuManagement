<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('document_type');
            $table->string('document_number');

            $table->string('disk')->default('public');
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            // Hash del contenido del archivo: permite detectar que el mismo
            // archivo (byte a byte) ya fue subido antes, sea para la misma
            // persona o para otra distinta.
            $table->string('file_hash', 64)->index();

            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->string('risk_level')->default('bajo');
            $table->json('risk_reasons')->nullable();
            // Marca cuando un miembro del staff revisó manualmente un documento
            // de riesgo alto y decidió continuar de todas formas.
            $table->boolean('authenticity_confirmed_by_staff')->default(false);

            $table->timestamps();

            $table->unique(['document_type', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
