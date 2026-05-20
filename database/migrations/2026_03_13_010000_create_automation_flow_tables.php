<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_flows', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('goal_preset')->default('sales_qualification');
            $table->string('channel')->default('whatsapp');
            $table->string('trigger_type')->default('incoming_whatsapp_message');
            $table->string('status')->default('draft');
            $table->json('graph_json')->nullable();
            $table->json('ui_json')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamp('last_published_at')->nullable();
            $table->boolean('has_unpublished_changes')->default(true);
            $table->unsignedBigInteger('runs_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'auto_flows_org_status_idx');
            $table->index(['organization_id', 'channel'], 'auto_flows_org_channel_idx');
        });

        Schema::create('automation_flow_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('automation_flow_id')->constrained('automation_flows')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('label')->nullable();
            $table->json('graph_json');
            $table->json('ui_json')->nullable();
            $table->json('compiled_json');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['automation_flow_id', 'version_number'], 'automation_flow_versions_unique_version');
            $table->index(['organization_id', 'published_at'], 'auto_flow_versions_org_pub_idx');
        });

        Schema::table('automation_flows', function (Blueprint $table) {
            $table
                ->foreign('current_version_id')
                ->references('id')
                ->on('automation_flow_versions')
                ->nullOnDelete();
        });

        Schema::create('automation_flow_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('automation_flow_id')->constrained('automation_flows')->cascadeOnDelete();
            $table->foreignId('automation_flow_version_id')->constrained('automation_flow_versions')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('chat_id')->nullable()->constrained('chats')->nullOnDelete();
            $table->string('status')->default('active');
            $table->string('current_node_id')->nullable();
            $table->string('waiting_node_id')->nullable();
            $table->string('waiting_for')->nullable();
            $table->json('state_json')->nullable();
            $table->json('last_input_json')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('next_resume_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'auto_flow_runs_org_status_idx');
            $table->index(['contact_id', 'status'], 'auto_flow_runs_contact_status_idx');
            $table->index(['next_resume_at', 'status'], 'auto_flow_runs_resume_status_idx');
        });

        Schema::create('automation_flow_run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_flow_run_id')->constrained('automation_flow_runs')->cascadeOnDelete();
            $table->foreignId('automation_flow_id')->constrained('automation_flows')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('node_id');
            $table->string('node_type');
            $table->string('status');
            $table->json('input_json')->nullable();
            $table->json('output_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['automation_flow_run_id', 'occurred_at'], 'auto_flow_steps_run_time_idx');
            $table->index(['automation_flow_id', 'node_id'], 'auto_flow_steps_flow_node_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_flow_run_steps');
        Schema::dropIfExists('automation_flow_runs');

        Schema::table('automation_flows', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('automation_flow_versions');
        Schema::dropIfExists('automation_flows');
    }
};
