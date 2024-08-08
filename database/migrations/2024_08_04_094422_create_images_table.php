<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('entry_id');
            $table->foreign('entry_id')->references('id')->on('entries')->onDelete('cascade');

            $table->string('collection');
            $table->string('filename');
            $table->string('path')->index();
            $table->string('mime_type')->nullable();
            $table->string('thumbhash')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('dimensions');
            $table->json('custom_properties')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
