<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("shift_change_requests", function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("schedule_cell_id");
            $table->unsignedBigInteger("requester_staff_id");
            $table->unsignedBigInteger("target_staff_id")->nullable();
            $table->enum("type", ["swap", "give", "change"])->default("change");
            $table->string("status", 20)->default("pending");
            $table->date("request_date");
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("shift_change_requests");
    }
};
