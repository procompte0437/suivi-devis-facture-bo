<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Setting;
use App\Support\ActivityLogger;
use App\Support\DocumentNumbers;
use App\Support\Templates;
use App\Support\Totals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller
{
    public function index(Request $request)
    {
        $query = Quote::query()->with('client:id,company')->withCount('lines');
        $this->applyFilters($query, $request);

        return response()->json($query->orderByDesc('issue_date')->orderByDesc('id')->get());
    }

    public function store(Request $request)
    {
        $quote = $this->persist($request);
        ActivityLogger::log($request->user(), 'devis.cree', $quote, 'Devis '.$quote->number.' créé.');

        return response()->json($quote, 201);
    }

    public function show(Quote $quote)
    {
        return response()->json($quote->load([
            'client',
            'project:id,name,code',
            'lines',
            'invoice:id,quote_id,number,status,total',
            'author:id,name',
        ]));
    }

    public function update(Request $request, Quote $quote)
    {
        if ($quote->invoice()->exists() && $request->input('status') === 'refuse') {
            return response()->json(['message' => 'Ce devis a déjà une facture. Il ne peut plus être refusé.'], 422);
        }

        $quote = $this->persist($request, $quote);
        ActivityLogger::log($request->user(), 'devis.maj', $quote, 'Devis '.$quote->number.' mis à jour.');

        return response()->json($quote);
    }

    public function destroy(Request $request, Quote $quote)
    {
        if ($quote->status !== 'brouillon' || $quote->invoice()->exists()) {
            return response()->json(['message' => 'Seul un brouillon sans facture peut être supprimé.'], 422);
        }

        $number = $quote->number;
        $quote->delete();
        ActivityLogger::log($request->user(), 'devis.supprime', null, 'Brouillon '.$number.' supprimé.');

        return response()->json(['message' => 'Devis supprimé.']);
    }

    public function status(Request $request, Quote $quote)
    {
        $data = $this->validateData($request, [
            'status' => ['required', 'in:brouillon,envoye,accepte,refuse'],
        ]);

        if ($quote->invoice()->exists() && $data['status'] === 'refuse') {
            return response()->json(['message' => 'Ce devis est déjà facturé.'], 422);
        }

        $quote->status = $data['status'];
        $quote->decided_at = in_array($data['status'], ['accepte', 'refuse'], true) ? now() : null;
        $quote->save();

        if ($data['status'] !== 'refuse') {
            $this->promoteClient($quote->client_id);
        }

        ActivityLogger::log($request->user(), 'devis.statut', $quote, 'Devis '.$quote->number.' passé en '.$data['status'].'.');

        return response()->json($quote->fresh()->load('client:id,company', 'invoice:id,quote_id,number'));
    }

    public function convert(Request $request, Quote $quote)
    {
        $data = $this->validateData($request, [
            'create_project' => ['nullable', 'boolean'],
            'due_date' => ['nullable', 'date'],
        ]);

        if ($quote->invoice()->exists()) {
            return response()->json(['message' => 'Une facture existe déjà pour ce devis.'], 422);
        }

        if ($quote->status === 'refuse') {
            return response()->json(['message' => 'Un devis refusé ne se transforme pas en facture.'], 422);
        }

        $settings = Setting::current();

        $invoice = DB::transaction(function () use ($request, $quote, $data, $settings) {
            $quote->load('lines');

            if ($quote->status !== 'accepte') {
                $quote->status = 'accepte';
                $quote->decided_at = now();
                $quote->save();
            }

            $projectId = $quote->project_id;
            if (! empty($data['create_project']) && ! $projectId) {
                $project = Project::create([
                    'code' => DocumentNumbers::code('PRJ', Project::class),
                    'client_id' => $quote->client_id,
                    'manager_id' => $request->user()->id,
                    'name' => $quote->title,
                    'description' => 'Ouvert depuis le devis '.$quote->number.'.',
                    'status' => 'cadrage',
                    'progress' => 8,
                    'budget' => $quote->total,
                    'start_date' => now()->toDateString(),
                ]);
                foreach (['Cadrage', 'Production', 'Livraison'] as $index => $title) {
                    $project->milestones()->create([
                        'title' => $title,
                        'done' => $index === 0,
                        'position' => $index,
                    ]);
                }
                $projectId = $project->id;
                $quote->project_id = $projectId;
                $quote->save();
            }

            $invoice = Invoice::create([
                'number' => DocumentNumbers::next($settings->invoice_prefix, Invoice::class),
                'client_id' => $quote->client_id,
                'project_id' => $projectId,
                'quote_id' => $quote->id,
                'created_by' => $request->user()->id,
                'title' => $quote->title,
                'status' => 'emise',
                'issue_date' => now()->toDateString(),
                'due_date' => $data['due_date'] ?? now()->addDays(30)->toDateString(),
                'tax_rate' => $quote->tax_rate,
                'discount' => $quote->discount,
                'subtotal' => $quote->subtotal,
                'tax_amount' => $quote->tax_amount,
                'total' => $quote->total,
                'notes' => $quote->notes,
                'template' => 'cedre',
                'company_id' => $quote->company_id,
            ]);

            foreach ($quote->lines as $line) {
                $invoice->lines()->create($line->only([
                    'position', 'designation', 'detail', 'quantity', 'unit', 'unit_price', 'line_total',
                ]));
            }

            return $invoice;
        });

        $this->promoteClient($quote->client_id);
        ActivityLogger::log(
            $request->user(),
            'devis.facture',
            $invoice,
            'Devis '.$quote->number.' transformé en facture '.$invoice->number.'.'
        );

        return response()->json($invoice->load('client', 'lines', 'quote:id,number'), 201);
    }

    private function persist(Request $request, ?Quote $quote = null): Quote
    {
        $data = $this->validateData($request, [
            'client_id' => ['required', 'exists:clients,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:180'],
            'status' => ['nullable', 'in:brouillon,envoye,accepte,refuse'],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'template' => ['nullable', 'in:'.implode(',', Templates::QUOTES)],
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

        return DB::transaction(function () use ($data, $totals, $quote, $request, $settings) {
            $payload = [
                'client_id' => $data['client_id'],
                'project_id' => $data['project_id'] ?? null,
                'title' => $data['title'],
                'status' => $data['status'] ?? 'brouillon',
                'issue_date' => $data['issue_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'tax_rate' => $totals['tax_rate'],
                'discount' => $totals['discount'],
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
                'template' => $data['template'] ?? $quote?->template ?? 'nomade',
            ];

            if (in_array($payload['status'], ['accepte', 'refuse'], true)) {
                $payload['decided_at'] = $quote?->decided_at ?? now();
            } else {
                $payload['decided_at'] = null;
            }

            if ($quote) {
                $quote->update($payload);
            } else {
                $payload['number'] = DocumentNumbers::next($settings->quote_prefix, Quote::class);
                $payload['created_by'] = $request->user()->id;
                $quote = Quote::create($payload);
            }

            $quote->lines()->delete();
            $quote->lines()->createMany($totals['lines']);

            if ($payload['status'] !== 'refuse') {
                $this->promoteClient($payload['client_id']);
            }

            return $quote->fresh()->load([
                'client',
                'project:id,name,code',
                'lines',
                'invoice:id,quote_id,number,status,total',
                'author:id,name',
            ]);
        });
    }

    private function promoteClient(int $clientId): void
    {
        Client::query()->where('id', $clientId)->where('status', 'prospect')->update(['status' => 'actif']);
    }

    private function applyFilters($query, Request $request): void
    {
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
        if ($status === 'expire') {
            $query->where('status', 'envoye')->whereDate('valid_until', '<', now()->toDateString());
        } elseif ($status === 'envoye') {
            $query->where('status', 'envoye')->where(function ($builder) {
                $builder->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()->toDateString());
            });
        } elseif ($status) {
            $query->where('status', $status);
        }

        if ($clientId = $request->query('client_id')) {
            $query->where('client_id', $clientId);
        }
    }
}
