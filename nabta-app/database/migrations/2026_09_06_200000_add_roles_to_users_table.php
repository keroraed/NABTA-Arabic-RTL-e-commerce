<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default(Role::Cashier->value)->after('email');

            // NULL means "use the role's defaults". A non-null array is an
            // explicit override for this one person, which is what lets an admin
            // give a trusted cashier stock access without inventing a new role.
            $table->json('permissions')->nullable()->after('role');

            $table->boolean('is_active')->default(true)->after('permissions');
        });

        // Whoever exists before this migration is the shop owner.
        DB::table('users')->update(['role' => Role::Admin->value]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'permissions', 'is_active']);
        });
    }
};
