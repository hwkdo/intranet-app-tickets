<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_ticket_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_request_id');
            $table->foreign('ticket_request_id', 'ticket_req_attach_fk')
                ->references('id')
                ->on('intranet_app_ticket_requests')
                ->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_ticket_request_attachments');
    }
};
