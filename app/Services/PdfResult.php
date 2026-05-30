<?php

namespace App\Services;

use Illuminate\Http\Response;

/**
 * Thin wrapper around a rendered PDF byte string.
 * Provides download() / stream() so controller code stays the same
 * regardless of which PDF engine is used underneath.
 */
class PdfResult
{
    public function __construct(private readonly string $bytes) {}

    public function download(string $filename): Response
    {
        return response($this->bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function stream(string $filename): Response
    {
        return response($this->bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
