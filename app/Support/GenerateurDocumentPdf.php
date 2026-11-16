<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Setting;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;

/**
 * Construit le PDF d’un devis ou d’une facture côté serveur.
 * Le navigateur ne fait qu’enregistrer le fichier renvoyé.
 */
class GenerateurDocumentPdf
{
    /** Couleur d’accent selon le modèle choisi dans l’atelier. */
    private const COULEURS = [
        'nomade' => '#1e6b45',
        'ligne' => '#1c1a16',
        'canopee' => '#143728',
        'soleil' => '#8a6a28',
        'chantier' => '#1e6b45',
        'cedre' => '#1e6b45',
        'artisans' => '#8a6a28',
        'csmart' => '#143728',
        'atelier' => '#1c1a16',
        'iris' => '#1e6b45',
    ];

    public static function reponse(Model $document, string $nature): Response
    {
        $document->loadMissing(['client', 'lines', 'company']);
        $reglages = Setting::current();
        $html = view('pdf.document', [
            'document' => $document,
            'nature' => $nature,
            'reglages' => $reglages,
            'couleur' => self::COULEURS[$document->template] ?? '#1e6b45',
            'logo' => self::logo($document),
            'estFacture' => $document instanceof Invoice,
        ])->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $moteur = new Dompdf($options);
        $moteur->loadHtml($html);
        $moteur->setPaper('A4');
        $moteur->render();

        $nom = ($document->number ?: ($nature === 'facture' ? 'facture' : 'devis')).'.pdf';

        return response($moteur->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nom.'"',
        ]);
    }

    /** Le logo de l’entreprise, encodé pour rester dans le fichier PDF. */
    private static function logo(Model $document): ?string
    {
        $chemin = $document->company?->logo_path;
        if (! $chemin) {
            return null;
        }

        $fichier = storage_path('app/public/'.$chemin);
        if (! is_file($fichier)) {
            return null;
        }

        $extension = strtolower(pathinfo($fichier, PATHINFO_EXTENSION));
        $type = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:'.$type.';base64,'.base64_encode((string) file_get_contents($fichier));
    }
}
