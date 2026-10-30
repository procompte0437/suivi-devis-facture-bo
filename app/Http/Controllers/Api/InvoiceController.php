<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Support\ActivityLogger;
use App\Support\DocumentNumbers;
use App\Support\Templates;
use App\Support\Totals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::query()->with('client:id,company');
        $term = trim((string) $request->query('q', ''));

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('number', 'ilike', $like)
                    ->orWhere('title', 'ilike', $like)
                    ->orWhereHas('client', fn ($client) => $client->where('company', 'ilike', $like));
            });
        }

        $status = $request->query('status');
        if ($status === 'en_retard') {
            $query->whereIn('status', ['emise', 'partielle'])
                ->whereDate('due_date', '<', now()->toDateString())
                ->whereColumn('amount_paid', '<', 'total');
        } elseif ($status) {
            $query->where('status', $status);
        }

        if ($clientId = $request->query('client_id')) {
            $query->where('client_id', $clientId);
        }

        return response()->json($query->orderByDesc('issue_date')->orderByDesc('id')->get());
    }

    public function store(Request $request)
    {
        $invoice = $this->persist($request);
        ActivityLogger::log($request->user(), 'facture.creee', $invoice, 'Facture '.$invoice->number.' créée.');

        return response()->json($invoice, 201);
    }

    public function show(Invoice $invoice)
    {
        return response()->json($invoice->load([
            'client',
            'project:id,name,code',
            'quote:id,number',
            'lines',
            'payments.author:id,name',
            'author:id,name',
        ]));
    }

    public function update(Request $request, Invoice $invoice)
    {
        if ($invoice->payments()->exists()) {
            return response()->json(['message' => 'Cette facture a déjà des règlements. Le contenu est figé.'], 422);
        }

        $invoice = $this->persist($request, $invoice);
        ActivityLogger::log($request->user(), 'facture.maj', $invoice, 'Facture '.$invoice->number.' mise à jour.');

        return response()->json($invoice);
    }

    public function destroy(Request $request, Invoice $invoice)
    {
        if ($invoice->status !== 'brouillon' || $invoice->payments()->exists()) {
            return response()->json(['message' => 'Seule une facture brouillon sans règlement peut être supprimée.'], 422);
        }

        $number = $invoice->number;
        $invoice->delete();
        ActivityLogger::log($request->user(), 'facture.supprimee', null, 'Brouillon '.$number.' supprimé.');

        return response()->json(['message' => 'Facture supprimée.']);
    }

    public function status(Request $request, Invoice $invoice)
    {
        $data = $this->validateData($request, [
            'status' => ['required', 'in:brouillon,emise,annulee'],
        ]);

        if ($invoice->payments()->exists()) {
            return response()->json(['message' => 'Des règlements sont déjà enregistrés.'], 422);
        }

        $invoice->status = $data['status'];
        if ($data['status'] === 'annulee') {
            $invoice->amount_paid = 0;
        }
        $invoice->save();

        ActivityLogger::log($request->user(), 'facture.statut', $invoice, 'Facture '.$invoice->number.' : '.$data['status'].'.');

        return response()->json($invoice->fresh()->load('client:id,company'));
    }

    public function pay(Request $request, Invoice $invoice)
    {
        if (in_array($invoice->status, ['brouillon', 'annulee'], true)) {
            return response()->json(['message' => 'Émettez la facture avant d’enregistrer un règlement.'], 422);
        }

        $data = $this->validateData($request, [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', 'in:virement,especes,mobile,cheque,carte'],
            'reference' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $remaining = round((float) $invoice->total - (float) $invoice->amount_paid, 2);
        if ((float) $data['amount'] > $remaining + 0.009) {
            return response()->json([
                'message' => 'Le montant dépasse le solde restant.',
            ], 422);
        }

        DB::transaction(function () use ($invoice, $data, $request) {
            $invoice->payments()->create([
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $invoice->refreshPaymentStatus();
        });

        ActivityLogger::log(
            $request->user(),
            'facture.reglement',
            $invoice,
            'Règlement enregistré sur '.$invoice->number.'.'
        );

        return response()->json($invoice->fresh()->load([
            'client',
            'lines',
            'payments.author:id,name',
            'project:id,name,code',
            'quote:id,number',
        ]));
    }

    private function persist(Request $request, ?Invoice $invoice = null): Invoice
    {
        $data = $this->validateData($request, [
            'client_id' => ['required', 'exists:clients,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'quote_id' => ['nullable', 'exists:quotes,id'],
            'title' => ['required', 'string', 'max:180'],
            'status' => ['nullable', 'in:brouillon,emise,annulee'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'template' => ['nullable', 'in:'.implode(',', Templates::INVOICES)],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.designation' => ['required', 'string', 'max:180'],
            'lines.*.detail' => ['nullable', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit' => ['nullable', 'string', 'max:24'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $settings = Setting::current();
        $totals = Totals::fromLines(
            $data['lines'],
            (float) ($data['discount'] ?? 0),
            (float) ($data['tax_rate'] ?? $settings->tax_rate)
        );

        return DB::transaction(function () use ($data, $totals, $invoice, $request, $settings) {
            $payload = [
                'client_id' => $data['client_id'],
                'project_id' => $data['project_id'] ?? null,
                'quote_id' => $data['quote_id'] ?? null,
                'title' => $data['title'],
                'status' => $data['status'] ?? $invoice?->status ?? 'brouillon',
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'tax_rate' => $totals['tax_rate'],
                'discount' => $totals['discount'],
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
                'template' => $data['template'] ?? $invoice?->template ?? 'cedre',
            ];

            if (in_array($payload['status'], ['partielle', 'payee'], true)) {
                unset($payload['status']);
            }

            if ($invoice) {
                $invoice->update($payload);
            } else {
                $payload['number'] = DocumentNumbers::next($settings->invoice_prefix, Invoice::class);
                $payload['created_by'] = $request->user()->id;
                $payload['amount_paid'] = 0;
                $invoice = Invoice::create($payload);
            }

            $invoice->lines()->delete();
            $invoice->lines()->createMany($totals['lines']);

            if (($payload['status'] ?? $invoice->status) !== 'annulee') {
                Client::query()->where('id', $payload['client_id'])->where('status', 'prospect')->update(['status' => 'actif']);
            }

            return $invoice->fresh()->load([
                'client',
                'project:id,name,code',
                'quote:id,number',
                'lines',
                'payments.author:id,name',
                'author:id,name',
            ]);
        });
    }
}
