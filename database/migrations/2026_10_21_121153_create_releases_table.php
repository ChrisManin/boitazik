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
        // A release is a MusicBrainz release: one physical edition (pressing) of an album
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained()->cascadeOnDelete();
            $table->string('format');
            $table->string('label')->nullable();
            $table->string('catalog_number')->nullable();
            $table->char('country', 2)->nullable();
            $table->unsignedSmallInteger('release_year')->nullable();
            $table->string('barcode')->nullable()->index();
            // Nullable: manually entered releases have no MusicBrainz ID
            $table->uuid('mbid')->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
