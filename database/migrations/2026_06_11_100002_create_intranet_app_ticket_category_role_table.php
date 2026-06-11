<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_ticket_category_role', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_category_id');
            $table->foreign('ticket_category_id', 'ticket_cat_role_cat_fk')
                ->references('id')
                ->on('intranet_app_ticket_categories')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->unique(['ticket_category_id', 'role_id'], 'ticket_cat_role_unique');
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_ticket_category_role');
    }
};
