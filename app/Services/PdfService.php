<?php

namespace App\Services;

use Mpdf\Mpdf;

class PdfService
{
    /**
     * Render a Blade view to a PDF with full Arabic/RTL support via mPDF.
     *
     * mPDF has a built-in Arabic text shaper and BiDi algorithm, so Arabic
     * renders correctly without any pre-processing or special fonts.
     */
    public static function arabic(string $view, array $data = [], string $format = 'A4'): PdfResult
    {
        $html = view($view, $data)->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => $format,
            'margin_top'    => 0,
            'margin_right'  => 0,
            'margin_bottom' => 0,
            'margin_left'   => 0,
            'default_font'  => 'dejavusans',
        ]);

        // Tell mPDF that the default paragraph direction is RTL (Arabic/Hebrew)
        $mpdf->SetDirectionality('rtl');

        $mpdf->WriteHTML($html);

        return new PdfResult($mpdf->Output('', 'S'));
    }
}
