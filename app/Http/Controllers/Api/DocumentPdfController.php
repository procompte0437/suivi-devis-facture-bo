<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Quote;
use App\Support\GenerateurDocumentPdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Téléchargement des PDF. Le fichier est produit par le serveur.
 */
class DocumentPdfController extends Controller
{
    public function devis(Request $request, Quote $quote): Response
    {
        $this->autoriser($request, $quote->client_id, $quote->status);

        return GenerateurDocumentPdf::reponse($quote, 'devis');
    }

    public function facture(Request $request, Invoice $invoice): Response
    {
        $this->autoriser($request, $invoice->client_id, $invoice->status);

        return GenerateurDocumentPdf::reponse($invoice, 'facture');
    }

    /**
     * L’équipe voit les pièces de son entreprise (filtre déjà appliqué).
     * Le client ne reçoit que ses pièces déjà émises.
     */
    private function autoriser(Request $request, int $clientId, string $statut): void
    {
        if ($request->user()->role !== 'client') {
            return;
        }

        abort_unless($clientId === $request->user()->client_id && $statut !== 'brouillon', 404);
    }
}
