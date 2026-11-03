<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_type_id')->constrained('shift_types')->onDelete('cascade');
            $table->integer('count')->default(1);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('requirements'); }
};
