<?php

namespace App\Services;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

final class PdfTextExtractor
{
    private const MAX_SIZE = 3 * 1024 * 1024; // 3 MB

    public function extract(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("File not found or unreadable: $path");
        }

        $size = filesize($path);

        if ($size === false || $size === 0 || $size > self::MAX_SIZE) {
            throw new RuntimeException("File size exceeds the maximum allowed size (3 MB): $path");
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if ($mime !== 'application/pdf') {
            throw new RuntimeException("File is not a PDF: $path");
        }

        try {
            $pdf = (new Parser())->parseFile($path);
            $pageCount = count($pdf->getPages());

            if ($pageCount < 1 || $pageCount > 3) {
                throw new RuntimeException("Invalid number of pages in the PDF: $path");
            }

            $text = trim($pdf->getText());
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException("Error parsing PDF: $path", 0, $e);
        }

        if ($text === '') {
            throw new RuntimeException("PDF is empty: $path");
        }

        return $text;
    }
}
