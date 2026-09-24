<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouvelle commande reçue</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #FAF9F6; color: #0B0B0B; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #E7E4DE; border-radius: 12px; overflow: hidden; padding: 24px; }
        .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #B89B5E; }
        .header h1 { font-size: 22px; color: #0B0B0B; margin: 10px 0 0 0; }
        .badge { display: inline-block; background-color: #FEF3C7; color: #D97706; font-size: 11px; font-weight: bold; padding: 4px 12px; rounded: 20px; text-transform: uppercase; margin-top: 8px; }
        .section { margin-top: 20px; }
        .section-title { font-size: 14px; font-weight: bold; text-transform: uppercase; color: #B89B5E; border-bottom: 1px solid #E7E4DE; padding-bottom: 6px; margin-bottom: 12px; }
        .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .info-grid td { padding: 6px 0; font-size: 14px; }
        .info-grid td.label { color: #6B6B6B; width: 35%; }
        .info-grid td.value { font-weight: bold; color: #0B0B0B; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .items-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: #6B6B6B; border-bottom: 1px solid #E7E4DE; padding: 8px 4px; }
        .items-table td { padding: 10px 4px; border-bottom: 1px solid #FAF9F6; font-size: 13px; }
        .total-row { font-weight: bold; font-size: 16px; color: #B89B5E; }
        .btn-container { text-align: center; margin-top: 28px; }
        .btn { display: inline-block; background-color: #0B0B0B; color: #FAF9F6; text-decoration: none; font-size: 13px; font-weight: bold; padding: 14px 28px; border-radius: 8px; text-transform: uppercase; tracking-wider: 1px; }
        .footer { text-align: center; font-size: 11px; color: #9CA3AF; margin-top: 24px; border-top: 1px solid #E7E4DE; padding-top: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 style="margin:0; color:#B89B5E; font-size:24px;">MAISON I&M</h2>
            <h1>🎉 Nouvelle Commande Reçue !</h1>
            <div class="badge">Commande #{{ $order->id }} • En Attente</div>
        </div>

        <div class="section">
            <div class="section-title">Informations du Client</div>
            <table class="info-grid">
                <tr>
                    <td class="label">Nom complet :</td>
                    <td class="value">{{ $order->customer->name ?? 'Client' }}</td>
                </tr>
                <tr>
                    <td class="label">Téléphone :</td>
                    <td class="value">{{ $order->customer->phone ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Ville :</td>
                    <td class="value">{{ $order->customer->city ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Adresse :</td>
                    <td class="value">{{ $order->customer->address ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Paiement :</td>
                    <td class="value" style="color:#059669;">Paiement à la livraison (COD)</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Détails de la Commande</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th style="text-align:center;">Qté</th>
                        <th style="text-align:right;">Prix</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td><strong>{{ $item->product->name ?? 'Produit #'.$item->product_id }}</strong></td>
                            <td style="text-align:center;">{{ $item->quantity }}</td>
                            <td style="text-align:right;">{{ number_format($item->price * $item->quantity, 2) }} MAD</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="2" style="padding-top:12px;">Total de la commande :</td>
                        <td style="text-align:right; padding-top:12px;">{{ number_format($order->total, 2) }} MAD</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="btn-container">
            <a href="{{ config('app.url') }}/admin/orders" class="btn">Accéder au Dashboard Admin</a>
        </div>

        <div class="footer">
            Cet e-mail automatique a été envoyé par le système d'administration Maison I&M.
        </div>
    </div>
</body>
</html>
