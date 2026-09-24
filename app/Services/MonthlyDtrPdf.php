<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class MonthlyDtrPdf
{
    /** @param array<string, mixed> $dtr */
    public function download(array $dtr): Response
    {
        return $this->document($dtr)->download($this->filename($dtr));
    }

    /** @param array<string, mixed> $dtr */
    public function render(array $dtr): string
    {
        return $this->document($dtr)->output();
    }

    /** @param array<string, mixed> $dtr */
    public function filename(array $dtr): string
    {
        $safeEmployeeNumber = Str::of((string) $dtr['employee_number'])
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '_')
            ->trim('_-')
            ->value();

        if ($safeEmployeeNumber === '') {
            $safeEmployeeNumber = 'EMPLOYEE';
        }

        return "DTR_{$safeEmployeeNumber}_{$dtr['month']}.pdf";
    }

    /** @param array<string, mixed> $dtr */
    private function document(array $dtr): PDF
    {
        return PdfFacade::loadView('dtr.pdf', ['dtr' => $dtr])
            ->setPaper('a4', 'portrait');
    }
}
