<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\Party;
use App\Services\Accounting\PartyLedger;
use App\Services\Accounting\Treasury;
use App\Services\Pos\PluExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Printable reports. Blade pages, not JSON — they are read and printed, and a
 * print stylesheet is the whole delivery mechanism the shop needs.
 */
class ReportController extends Controller
{
    /**
     * كشف حساب — the running-balance statement, which is the layout an
     * Egyptian shop expects and the reason the ledger stores debit and credit
     * as two columns rather than one signed number.
     */
    public function partyStatement(Request $request, Party $party, PartyLedger $ledger): View
    {
        $from = $request->date('from');
        $to = $request->date('to');

        return view('reports.party-statement', [
            'party' => $party,
            'from' => $from,
            'to' => $to,
            'statement' => $ledger->statement($party, $from, $to),
        ]);
    }

    /**
     * حركة الخزينة. The report asserts opening + in - out = closing and says so
     * loudly if it ever does not, rather than printing a number nobody can trust.
     */
    public function treasury(Request $request, Treasury $treasury): View
    {
        $from = $request->date('from');
        $to = $request->date('to');

        $accounts = CashAccount::query()->orderBy('name')->get()->map(fn (CashAccount $a) => [
            'account' => $a,
            'report' => $treasury->report($a, $from, $to),
            'movements' => $a->movements()
                ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to))
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->get(),
        ]);

        return view('reports.treasury', [
            'accounts' => $accounts,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * Phase 1 of the scale integration: the PLU file for the ACLAS PC software.
     *
     * Downloaded from admin and imported by hand, which unblocks the shop while
     * the vendor protocol needed for a direct push is still outstanding.
     */
    public function pluExport(PluExporter $exporter): StreamedResponse
    {
        $filename = $exporter->filename();
        $csv = $exporter->toCsv();

        return response()->streamDownload(
            function () use ($csv) {
                // BOM so Excel and the ACLAS importer read the Arabic correctly.
                echo "\xEF\xBB\xBF".$csv;
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
