<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A collection item is one copy owned by a user; the catalog itself is shared
        Schema::create('collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Restrict: an album someone owns cannot be removed from the catalog
            $table->foreignId('album_id')->constrained()->restrictOnDelete();
            // Nullable: the exact edition may be unknown
            $table->foreignId('release_id')->nullable()->constrained()->nullOnDelete();
            $table->string('condition')->nullable();
            $table->date('acquired_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_items');
    }
};
