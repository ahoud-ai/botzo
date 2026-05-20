<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_flow_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('automation_flow_id')->constrained('automation_flows')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('media_kind')->default('document');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->json('meta_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['automation_flow_id', 'media_kind'], 'auto_flow_assets_flow_kind_idx');
            $table->index(['organization_id', 'created_at'], 'auto_flow_assets_org_created_idx');
        });

        Schema::create('automation_flow_node_secrets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('automation_flow_id')->constrained('automation_flows')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('node_id');
            $table->string('node_type');
            $table->longText('payload_json');
            $table->timestamps();

            $table->unique(['automation_flow_id', 'node_id', 'node_type'], 'auto_flow_node_secret_unique_idx');
            $table->index(['organization_id', 'node_type'], 'auto_flow_node_secret_org_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_flow_node_secrets');
        Schema::dropIfExists('automation_flow_assets');
    }
};
