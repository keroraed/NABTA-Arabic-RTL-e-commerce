<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only party ledger. Deliberately NOT a double-entry general ledger
     * with a chart of accounts — that is overkill here and a common source of
     * bugs when built by non-accountants.
     *
     * Convention (enforced in App\Services\Accounting\PartyLedger):
     *   مدين / debit  = the party owes us less / we paid them
     *   دائن / credit = we owe them more
     *   supplier balance owed by us = SUM(credit) - SUM(debit)
     *   customer balance owed to us = SUM(debit) - SUM(credit)
     *
     * Two columns rather than one signed column, because that maps directly onto
     * the Arabic statement layout with no conversion at render time.
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->date('entry_date');
            $table->string('description');
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->nullableMorphs('reference');
            $table->foreignId('cash_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['party_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
