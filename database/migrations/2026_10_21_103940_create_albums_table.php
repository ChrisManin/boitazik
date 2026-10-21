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
        // An album is a MusicBrainz release-group: the work, not a physical edition
        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type')->default('album');
            $table->unsignedSmallInteger('first_release_year')->nullable();
            // Nullable: manually entered albums have no MusicBrainz ID
            $table->uuid('mbid')->nullable()->unique();
            $table->string('spotify_id')->nullable();
            $table->string('cover_url')->nullable();
            $table->timestamp('enriched_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};
