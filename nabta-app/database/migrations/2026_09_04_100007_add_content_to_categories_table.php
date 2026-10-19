<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The categories landing page shows a longer heading, a badge and a paragraph
     * per category. That copy was hard-coded in categories.html; it belongs on the
     * row so the client can edit it in admin without a deploy.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('name_ar');
            $table->string('badge_ar')->nullable()->after('title_ar');
            $table->text('description_ar')->nullable()->after('badge_ar');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['title_ar', 'badge_ar', 'description_ar']);
        });
    }
};
