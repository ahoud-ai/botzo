<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_widget_sessions', function (Blueprint $table) {
            // "widget" for the website widget, "api" for conversations opened through the Mochi API.
            $table->string('source', 16)->default('widget')->after('organization_id');
            // The app's own id for its user (secret keys only), so a user keeps one conversation.
            $table->string('external_user_id', 191)->nullable()->after('source');
            $table->unsignedBigInteger('organization_api_key_id')->nullable()->after('external_user_id');

            $table->unique(['web_widget_id', 'external_user_id'], 'web_widget_sessions_widget_user_unique');
        });
    }

    public function down(): void
    {
        Schema::table('web_widget_sessions', function (Blueprint $table) {
            $table->dropUnique('web_widget_sessions_widget_user_unique');
            $table->dropColumn(['source', 'external_user_id', 'organization_api_key_id']);
        });
    }
};
