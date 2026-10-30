# InvoicePilot — AI Evaluation

This directory contains a reproducible evaluation dataset for InvoicePilot's local invoice extraction pipeline.

The goal is to measure how accurately a locally running LLM extracts structured invoice information from PDF documents and to document its limitations.

All invoice data is synthetic.

## Dataset

The evaluation consists of 12 test cases:

| Category | Cases | Count |
|---|---|---:|
| Normal invoices | `normal-01` to `normal-06` | 6 |
| Missing information | `missing-01`, `missing-02` | 2 |
| Inconsistent amounts | `inconsistent-01`, `inconsistent-02` | 2 |
| Prompt injection | `injection-01` | 1 |
| Duplicate invoice | `duplicate-01` | 1 |
| **Total** | | **12** |

The duplicate case reuses `normal-01.pdf`, so the dataset contains 11 unique PDF documents.

Each test case has manually specified ground-truth values in `ground-truth.json`.

## Evaluation methodology

The evaluation pipeline consists of:

1. Extracting text from the PDF using `PdfTextExtractor`.
2. Extracting structured invoice fields using `AiInvoiceExtractor`.
3. Validating extracted fields using `InvoiceValidator`.
4. Comparing the extracted values against ground truth.
5. Measuring processing time.

Seven fields are evaluated:

- `supplier_name`
- `invoice_number`
- `invoice_date`
- `subtotal_ex_vat`
- `vat_amount`
- `total_in_vat`
- `currency`

Field accuracy is calculated using exact string matching, including date and decimal formatting. Missing values must match `null`.

### Metrics

- **Field accuracy:** Correctly extracted fields divided by all evaluated fields.
- **Complete invoice accuracy:** Percentage of test cases where all seven fields are correct.
- **Validation agreement:** Percentage of test cases where generated validation warnings match the expected warnings.
- **Average processing time:** Mean duration of PDF extraction, LLM extraction, and deterministic validation.

The evaluation uses the local Ollama model `qwen3:4b-instruct` with temperature `0` and a context window of `4096`.

## Results

Two prompt versions were evaluated using the same 12 test cases.

| Metric | Baseline v1 | Improved prompt v2 |
|---|---:|---:|
| Correct fields | 83/84 (98.8%) | 84/84 (100%) |
| Completely correct invoices | 11/12 (91.7%) | 12/12 (100%) |
| Validation agreement | 12/12 | 12/12 |
| Average processing time | 1.49 s | 1.51 s |

### Observed model failures

**Failure 1 — Incorrect invoice number**

Test case: `normal-05`

- Ground truth: `HC-2026-500`
- Initial extraction: `HC-2026-50`
- The PDF text extractor preserved the correct identifier.
- The LLM omitted the final digit.

The same incorrect extraction occurred in three consecutive baseline runs.

**Failure 2 — Hallucinated invoice number**

Test case: `missing-01`

During an earlier individual evaluation, the model returned `FICTIEF TESTDOCUMENT` as an invoice number when the document contained no invoice number.

The model correctly returned `null` during the subsequent baseline batch, demonstrating output variability.

### Prompt improvement

One targeted prompt modification was introduced to improve invoice number extraction.

The new instructions explicitly require the model to:

- Identify invoice numbers using invoice-specific labels.
- Copy identifiers character-for-character.
- Preserve all digits, letters, separators, and leading zeros.
- Avoid interpreting document titles or footer labels as invoice numbers.
- Return `null` when an invoice number cannot be reliably identified.

After this change, both previously problematic test cases produced correct results in three consecutive runs each.

A subsequent evaluation of all 12 cases achieved 84/84 correctly extracted fields.

No additional prompt improvements were necessary for this dataset.

## Running the evaluation

Evaluate one invoice:

```bash
php artisan invoice:evaluate normal-01
```

Evaluate all cases:

```bash
php -r '
$data = json_decode(
    file_get_contents("evaluation/ground-truth.json"),
    true,
    512,
    JSON_THROW_ON_ERROR
);

foreach ($data["cases"] as $case) {
    echo $case["id"], PHP_EOL;
}
' | while IFS= read -r id; do
    echo "=== $id ==="
    php artisan invoice:evaluate "$id" || echo "EVALUATION FAILED: $id"
done
```

The evaluator reports field comparisons, validation results, and processing time.

Evaluation output can be redirected to a file for later analysis.

## Limitations

**Small synthetic dataset**

Twelve test cases are insufficient to establish general extraction accuracy across real-world invoice formats.

**Prompt optimization on the evaluation set**

The improved prompt was developed using observed failures from the same dataset. The resulting 100% score is therefore not an unbiased estimate of performance on unseen invoices.

**Output variability**

Temperature `0` does not guarantee identical results across all executions.

**Duplicate detection**

The standalone evaluator does not interact with SQLite and therefore does not evaluate database-dependent duplicate detection.

The `duplicate-01` case intentionally expects no field-validation warnings. Duplicate detection is tested separately using the `InvoiceProcessor` workflow and automated feature tests.

**Prompt injection**

The injection test checks whether the expected extraction results remain unchanged in the presence of a malicious instruction embedded in the document.

Passing this test does not establish comprehensive prompt-injection security.

**No OCR**

Only digitally generated PDFs containing selectable text are supported.

## Conclusion

The evaluation demonstrates that a small locally running LLM can successfully extract structured invoice data from the selected synthetic documents.

A targeted prompt improvement corrected a reproducible invoice number extraction error without introducing observed regressions in the evaluation dataset.

However, the results also demonstrate why valid structured output cannot be treated as guaranteed factual accuracy.

InvoicePilot therefore combines LLM extraction with deterministic validation and mandatory human approval.

The reported results describe this specific experimental setup, not guaranteed performance on unseen or production invoices.