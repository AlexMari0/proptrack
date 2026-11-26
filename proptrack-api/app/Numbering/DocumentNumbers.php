<?php

namespace App\Numbering;

use Illuminate\Support\Facades\DB;

/**
 * Human-facing document numbers: PREFIX-YYYY-NNNN, restarting each year.
 *
 * Numbers come from a locked counter row, so they are never reused, even
 * after a document is deleted, and concurrent requests can't take the same one.
 */
class DocumentNumbers
{
    public const INVOICE = 'INV';
    public const TICKET  = 'TKT';

    public function next(string $prefix, ?int $year = null): string
    {
        $year ??= (int) now()->year;

        $number = DB::transaction(function () use ($prefix, $year) {
            DB::table('document_sequences')->insertOrIgnore([
                'prefix' => $prefix, 'year' => $year, 'last_number' => 0,
            ]);

            $last = DB::table('document_sequences')
                ->where(['prefix' => $prefix, 'year' => $year])
                ->lockForUpdate()
                ->value('last_number');

            DB::table('document_sequences')
                ->where(['prefix' => $prefix, 'year' => $year])
                ->update(['last_number' => $last + 1]);

            return $last + 1;
        });

        return sprintf('%s-%d-%04d', $prefix, $year, $number);
    }
}
