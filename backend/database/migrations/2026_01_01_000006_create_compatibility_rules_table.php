<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('compatibility_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff1_id')->constrained('staff')->onDelete('cascade');
            $table->foreignId('staff2_id')->constrained('staff')->onDelete('cascade');
            $table->enum('rule_type', ['must_together', 'must_separate', 'can_together', 'can_separate']);
            $table->timestamps();
            $table->unique(['staff1_id', 'staff2_id', 'rule_type']);
        });
    }
    public function down(): void { Schema::dropIfExists('compatibility_rules'); }
};
