<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;

/**
 * Produit le guide utilisateur en PDF, côté serveur.
 * Le navigateur ne fait qu’enregistrer le fichier renvoyé.
 */
class GenerateurGuidePdf
{
    public static function reponse(): Response
    {
        $html = view('pdf.guide')->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $moteur = new Dompdf($options);
        $moteur->loadHtml($html);
        $moteur->setPaper('A4');
        $moteur->render();

        return response($moteur->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="guide-utilisateur-doukou.pdf"',
        ]);
    }
}
