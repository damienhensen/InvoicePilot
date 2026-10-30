<?php

namespace App\Console\Commands;

use App\Services\AiInvoiceExtractor;
use App\Services\InvoiceValidator;
use App\Services\PdfTextExtractor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('invoice:evaluate {case=normal-01}')]
#[Description('Evaluate AI invoice extraction against ground truth')]
class EvaluateInvoice extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(
        PdfTextExtractor $pdfExtractor,
        AiInvoiceExtractor $aiExtractor,
        InvoiceValidator $validator,
    ): int {
        $dataset = json_decode(
            file_get_contents(base_path('evaluation/ground-truth.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $caseId = $this->argument('case');

        $case = collect($dataset['cases'])
            ->firstWhere('id', $caseId);

        if ($case === null) {
            $this->error("Test case not found: {$caseId}");

            return self::FAILURE;
        }

        $path = base_path('evaluation/' . $case['pdf']);

        // Measure the complete extraction and validation process.
        $start = hrtime(true);

        $text = $pdfExtractor->extract($path);
        $actual = $aiExtractor->extract($text);
        $warnings = $validator->validate($actual);

        $durationMs = (hrtime(true) - $start) / 1_000_000;

        // Compare each extracted field against ground truth.
        $rows = [];
        $correct = 0;

        foreach ($case['expected'] as $field => $expected) {
            $matches = array_key_exists($field, $actual)
                && $actual[$field] === $expected;

            if ($matches) {
                $correct++;
            }

            $rows[] = [
                $field,
                json_encode($expected, JSON_UNESCAPED_UNICODE),
                json_encode($actual[$field] ?? null, JSON_UNESCAPED_UNICODE),
                $matches ? 'PASS' : 'FAIL',
            ];
        }

        $this->table(
            ['Field', 'Expected', 'Actual', 'Result'],
            $rows
        );

        $total = count($case['expected']);

        $expectedWarnings = $case['expected_warnings'];

        sort($warnings);
        sort($expectedWarnings);

        $this->newLine();
        $this->line("Correct fields: {$correct}/{$total}");
        $this->line(
            'All fields correct: ' .
                ($correct === $total ? 'YES' : 'NO')
        );

        $this->line(
            'Validation correct: ' .
                ($warnings === $expectedWarnings ? 'YES' : 'NO')
        );

        $this->line(
            'Actual warnings: ' . json_encode($warnings)
        );

        $this->line(sprintf(
            'Processing time: %.2f seconds',
            $durationMs / 1000
        ));

        return self::SUCCESS;
    }
}
