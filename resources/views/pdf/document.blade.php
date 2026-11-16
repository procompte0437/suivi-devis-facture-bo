{{-- Feuille PDF d’un devis ou d’une facture. Produite uniquement par le serveur. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1c1a16; font-size: 12px; }
        h1 { font-size: 22px; margin: 0; color: {{ $couleur }}; }
        .haut { width: 100%; margin-bottom: 24px; }
        .haut td { vertical-align: top; }
        .droite { text-align: right; }
        .logo { height: 48px; }
        table.lignes { width: 100%; border-collapse: collapse; margin-top: 18px; }
        table.lignes th { text-align: left; font-size: 10px; letter-spacing: 0.04em; text-transform: uppercase; color: #5c564c; border-bottom: 1px solid #d9d2c5; padding: 6px 4px; }
        table.lignes td { border-bottom: 1px solid #eee8dc; padding: 7px 4px; vertical-align: top; }
        .num { text-align: right; }
        .totaux { width: 260px; margin-left: auto; margin-top: 14px; }
        .totaux td { padding: 4px 0; }
        .total { font-size: 15px; font-weight: bold; color: {{ $couleur }}; }
        .notes { margin-top: 22px; color: #5c564c; white-space: pre-wrap; }
        .pied { margin-top: 28px; font-size: 10px; color: #6d675c; }
    </style>
</head>
<body>
    <table class="haut">
        <tr>
            <td>
                @if ($logo)
                    <img class="logo" src="{{ $logo }}" alt="">
                @endif
                <h1>{{ $reglages->company_name }}</h1>
                <div>{{ $reglages->tagline }}</div>
                <div>{{ collect([$reglages->address, $reglages->city, $reglages->country])->filter()->implode(' · ') }}</div>
                <div>{{ collect([$reglages->email, $reglages->phone])->filter()->implode(' · ') }}</div>
                @if ($reglages->tax_id)
                    <div>{{ $reglages->tax_id }}</div>
                @endif
            </td>
            <td class="droite">
                <h1>{{ $estFacture ? 'Facture' : 'Devis' }}</h1>
                <div>{{ $document->number }}</div>
                <div>Émis le {{ optional($document->issue_date)->format('d/m/Y') }}</div>
                <div>{{ $estFacture ? 'Échéance' : 'Valable jusqu’au' }} {{ optional($estFacture ? $document->due_date : $document->valid_until)->format('d/m/Y') ?: '—' }}</div>
            </td>
        </tr>
    </table>

    <p>
        <strong>{{ $document->client?->company }}</strong><br>
        {{ $document->client?->contact_name }}<br>
        {{ collect([$document->client?->address, $document->client?->city])->filter()->implode(' · ') }}
    </p>
    <p><strong>{{ $document->title }}</strong></p>

    <table class="lignes">
        <thead>
            <tr>
                <th>Désignation</th>
                <th class="num">Qté</th>
                <th>Unité</th>
                <th class="num">Prix</th>
                <th class="num">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($document->lines as $ligne)
                <tr>
                    <td>
                        {{ $ligne->designation }}
                        @if ($ligne->detail)
                            <br><span style="color:#6d675c">{{ $ligne->detail }}</span>
                        @endif
                    </td>
                    <td class="num">{{ number_format((float) $ligne->quantity, 2, ',', ' ') }}</td>
                    <td>{{ $ligne->unit }}</td>
                    <td class="num">{{ number_format((float) $ligne->unit_price, 0, ',', ' ') }}</td>
                    <td class="num">{{ number_format((float) $ligne->line_total, 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totaux">
        <tr><td>Sous-total</td><td class="num">{{ number_format((float) $document->subtotal, 0, ',', ' ') }} {{ $reglages->currency }}</td></tr>
        <tr><td>Remise</td><td class="num">{{ number_format((float) $document->discount, 0, ',', ' ') }} {{ $reglages->currency }}</td></tr>
        <tr><td>TVA {{ $document->tax_rate }} %</td><td class="num">{{ number_format((float) $document->tax_amount, 0, ',', ' ') }} {{ $reglages->currency }}</td></tr>
        <tr class="total"><td>Total</td><td class="num">{{ number_format((float) $document->total, 0, ',', ' ') }} {{ $reglages->currency }}</td></tr>
        @if ($estFacture)
            <tr><td>Réglé</td><td class="num">{{ number_format((float) $document->amount_paid, 0, ',', ' ') }} {{ $reglages->currency }}</td></tr>
            <tr><td>Solde</td><td class="num">{{ number_format((float) $document->balance, 0, ',', ' ') }} {{ $reglages->currency }}</td></tr>
        @endif
    </table>

    @if ($document->notes)
        <div class="notes">{{ $document->notes }}</div>
    @endif
    @if ($reglages->payment_note)
        <div class="notes">{{ $reglages->payment_note }}</div>
    @endif
    <div class="pied">{{ $reglages->footer }}</div>
</body>
</html>
