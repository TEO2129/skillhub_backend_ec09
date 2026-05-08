<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('formation_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('note')->unsigned();
            $table->text('commentaire')->nullable();
            $table->timestamps();

            // Contrainte d'unicité avec user_id 
            $table->unique(['user_id', 'formation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};