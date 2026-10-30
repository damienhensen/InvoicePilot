<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>InvoicePilot</title>
    <style>
        body {
            font-family: system-ui, sans-serif;
            max-width: 780px;
            margin: 60px auto;
            padding: 0 20px;
            line-height: 1.6;
        }

        form {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        pre {
            padding: 20px;
            background: #f4f4f4;
            border-radius: 8px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .error {
            color: #b91c1c;
        }
    </style>
</head>

<body>
    <h1>InvoicePilot</h1>
    <p>Upload an invoice to extract its text.</p>

    <form method="POST" action="{{ route('invoices.process') }}" enctype="multipart/form-data">
        @csrf

        <input type="file" name="invoice" accept=".pdf,application/pdf" required>

        <button type="submit">Process invoice</button>
    </form>

    @error('invoice')
        <p class="error">{{ $message }}</p>
    @enderror

    @isset($text)
        <h2>Extracted text</h2>
        <pre>{{ $text }}</pre>
    @endisset

    @isset($invoice)
        <h2>Extracted invoice</h2>

        <p>Status: <strong>{{ $status }}</strong></p>

        <table>
            @foreach ($invoice as $field => $value)
                <tr>
                    <th>{{ $field }}</th>
                    <td>{{ $value ?? '—' }}</td>
                </tr>
            @endforeach
        </table>

        @if (!empty($warnings))
            <h3>Validation warnings</h3>
            <ul>
                @foreach ($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        @else
            <p>All automatic checks passed.</p>
        @endif
    @endisset

    @isset($savedInvoice)
        <p>
            Saved as invoice #{{ $savedInvoice->id }}
        </p>
    @endisset
</body>

</html>
