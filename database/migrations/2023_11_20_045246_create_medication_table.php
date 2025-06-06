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
        Schema::create('medication', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('treatment_id');
            $table->foreign('treatment_id')->references('id')->on('treatment');
            
            $table->timestamp('date');
            $table->string('description')->nullable();
            $table->string('dosage');
            $table->string('medicine_name');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication');
    }
};
