<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_human_requests', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 50)->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('contact_id');
            $table->string('reason', 32);
            $table->boolean('blocks_assistant')->default(false);
            // Equals contact_id while the request is open and NULL after, so a contact has one open request at most.
            $table->unsignedBigInteger('open_contact_id')->nullable()->unique();
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->string('resolution', 32)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'resolved_at', 'opened_at'], 'human_requests_org_open_index');
            $table->index('contact_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_human_requests');
    }
};
