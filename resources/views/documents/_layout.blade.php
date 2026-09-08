{{-- Tata letak dokumen cetak. Sengaja polos: yang dicetak adalah isinya, dan
     PDF yang penuh hiasan justru lebih sulit dibaca saat diarsipkan. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $document }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { color: #555; font-size: 10px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f4f4f4; }
        .footer { margin-top: 24px; font-size: 10px; color: #555; }
    </style>
</head>
<body>
<h1>{{ $document }}</h1>
<div class="meta">
    Pengajuan #{{ $submission->id }}
    &middot; Dicetak {{ $printedAt->translatedFormat('d F Y H:i') }}
    oleh {{ $printedBy->name }}
</div>

@yield('isi')

<div class="footer">
    Dokumen ini dihasilkan SIMPAS. Berkasnya dihapus otomatis
    {{ \App\Support\Reporting\ExportRetention::HOURS }} jam setelah dibuat.
</div>
</body>
</html>
