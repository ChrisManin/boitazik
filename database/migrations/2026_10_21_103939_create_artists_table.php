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
        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sort_name')->nullable();
            // Nullable: manually entered artists have no MusicBrainz ID
            $table->uuid('mbid')->nullable()->unique();
            $table->string('spotify_id')->nullable();
            $table->char('country', 2)->nullable();
            $table->string('image_url')->nullable();
            $table->timestamp('enriched_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artists');
    }
};
