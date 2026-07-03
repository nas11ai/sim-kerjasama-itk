<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('budget_component_id')->constrained()->onUpdate('cascade')->onDelete('restrict');
            $table->string('item_name');
            $table->integer('volume');       // BR-BUD-04: volume > 0 (divalidasi di application layer)
            $table->string('unit');          // satuan bebas (UX: input satuan berupa text)
            $table->bigInteger('unit_price'); // rupiah bulat — konsisten dengan schemes.max_budget
            $table->bigInteger('total');      // volume * unit_price, dipersist untuk SUM grand total & audit trail
            $table->softDeletes();
            $table->timestamps();

            $table->index('form_submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_line_items');
    }
};
