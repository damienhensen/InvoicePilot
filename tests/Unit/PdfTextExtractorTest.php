<?php

namespace Tests\Unit;

use App\Services\PdfTextExtractor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PdfTextExtractorTest extends TestCase
{
    private PdfTextExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new PdfTextExtractor();
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__) . '/Fixtures/' . $name;
    }

    public function test_selectable_invoice_text_is_extracted(): void
    {
        $text = $this->extractor->extract($this->fixture('valid-invoice.pdf'));

        $this->assertStringContainsString('ZaanTech Diensten B.V.', $text);
        $this->assertStringContainsString('ZTD-2026-041', $text);
    }

    public function test_missing_file_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found or unreadable');

        $this->extractor->extract($this->fixture('does-not-exist.pdf'));
    }

    public function test_non_pdf_content_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'invoice-');
        if ($path === false) {
            $this->fail('Could not create test file.');
        }
        try {
            file_put_contents($path, 'not actually a PDF');
            $this->expectException(RuntimeException::class);
            $this->extractor->extract($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_oversized_file_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'invoice-');
        if ($path === false) {
            $this->fail('Could not create test file.');
        }
        try {
            file_put_contents($path, str_repeat('x', 3 * 1024 * 1024 + 1));
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('3 MB');
            $this->extractor->extract($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_more_than_three_pages_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid number of pages in the PDF');
        $this->extractor->extract($this->fixture('four-pages.pdf'));
    }

    public function test_blank_pdf_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PDF is invalid');
        $this->extractor->extract($this->fixture('blank.pdf'));
    }
}
