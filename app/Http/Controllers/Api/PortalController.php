<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;

/**
 * Espace client (acteur « Client » du cahier des charges) :
 * le client consulte ses devis, ses factures et l'avancement de ses projets,
 * et accepte ou refuse un devis qui lui a été envoyé (UC06).
 * Les brouillons ne lui sont jamais montrés.
 */
class PortalController extends Controller
{
    public function overview(Request $request)
    {
        $client = $request->user()->client;
        abort_unless($client, 404);

        $quotes = Quote::query()
            ->where('client_id', $client->id)
            ->where('status', '!=', 'brouillon')
            ->orderByDesc('issue_date')
            ->get();

        $invoices = Invoice::query()
            ->where('client_id', $client->id)
            ->where('status', '!=', 'brouillon')
            ->orderByDesc('issue_date')
            ->get();

        $projects = Project::query()
            ->where('client_id', $client->id)
            ->with('milestones')
            ->latest()
            ->get();

        return response()->json([
            'client' => $client,
            'quotes' => $quotes,
            'invoices' => $invoices,
            'projects' => $projects,
            'totals' => [
                'pending_quotes' => $quotes->where('display_status', 'envoye')->count(),
                'invoiced' => round((float) $invoices->where('status', '!=', 'annulee')->sum('total'), 2),
                'paid' => round((float) $invoices->sum('amount_paid'), 2),
                'balance' => round((float) $invoices->where('status', '!=', 'annulee')->sum('balance'), 2),
            ],
        ]);
    }

    public function quote(Request $request, Quote $quote)
    {
        $this->ownsDocument($request, $quote->client_id, $quote->status);

        return response()->json($quote->load('client', 'lines', 'project:id,name,code', 'decider:id,name'));
    }

    public function decide(Request $request, Quote $quote)
    {
        $this->ownsDocument($request, $quote->client_id, $quote->status);

        $data = $this->validateData($request, [
            'decision' => ['required', 'in:accepte,refuse'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($quote->display_status !== 'envoye') {
            return response()->json([
                'message' => $quote->display_status === 'expire'
                    ? 'Ce devis a expiré. Demandez une nouvelle proposition.'
                    : 'Une décision a déjà été enregistrée sur ce devis.',
            ], 422);
        }

        $quote->status = $data['decision'];
        $quote->decided_at = now();
        $quote->decided_by = $request->user()->id;
        $quote->decision_note = $data['note'] ?? null;
        $quote->save();

        $verb = $data['decision'] === 'accepte' ? 'accepté' : 'refusé';
        ActivityLogger::log($request->user(), 'devis.decision_client', $quote, 'Devis '.$quote->number.' '.$verb.' par le client.');

        return response()->json($quote->fresh()->load('client', 'lines', 'project:id,name,code', 'decider:id,name'));
    }

    public function invoice(Request $request, Invoice $invoice)
    {
        $this->ownsDocument($request, $invoice->client_id, $invoice->status);

        return response()->json($invoice->load('client', 'lines', 'payments', 'quote:id,number', 'project:id,name,code'));
    }

    public function project(Request $request, Project $project)
    {
        abort_unless($project->client_id === $request->user()->client_id, 404);

        return response()->json($project->load([
            'manager:id,name',
            'milestones',
            'updates' => fn ($query) => $query->with('author:id,name')->limit(20),
        ]));
    }

    private function ownsDocument(Request $request, int $clientId, string $status): void
    {
        abort_unless($clientId === $request->user()->client_id && $status !== 'brouillon', 404);
    }
}
