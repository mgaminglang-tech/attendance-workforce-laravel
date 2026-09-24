<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class MonthlyDtrPdf
{
    /** @param array<string, mixed> $dtr */
    public function download(array $dtr): Response
    {
        $safeEmployeeNumber = Str::of((string) $dtr['employee_number'])
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '_')
            ->trim('_-')
            ->value();

        if ($safeEmployeeNumber === '') {
            $safeEmployeeNumber = 'EMPLOYEE';
        }

        $filename = "DTR_{$safeEmployeeNumber}_{$dtr['month']}.pdf";

        return Pdf::loadView('dtr.pdf', ['dtr' => $dtr])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }
}
