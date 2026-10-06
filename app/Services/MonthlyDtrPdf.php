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
        $employee = $dtr['employee'];
        $firstName = $this->sanitizeName((string) $employee->first_name);
        $lastName = $this->sanitizeName((string) $employee->last_name);
        $name = $firstName !== '' && $lastName !== ''
            ? $lastName.'_'.$firstName
            : $this->sanitizeName($dtr['employee_name']);

        if ($name === '') {
            $name = 'EMPLOYEE';
        }

        return "DTR_{$name}_{$dtr['month']}.pdf";
    }

    private function sanitizeName(string $name): string
    {
        return Str::of($name)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->value();
    }

    /** @param array<string, mixed> $dtr */
    private function document(array $dtr): PDF
    {
        return PdfFacade::loadView('dtr.pdf', ['dtr' => $dtr])
            ->setPaper('a4', 'portrait');
    }
}
