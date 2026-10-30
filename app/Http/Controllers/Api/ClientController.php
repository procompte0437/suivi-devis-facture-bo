<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Support\ActivityLogger;
use App\Support\DocumentNumbers;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::query()
            ->withCount(['quotes', 'invoices', 'projects'])
            ->withSum(['invoices as billed' => fn ($builder) => $builder->where('status', '!=', 'annulee')], 'total');

        $term = trim((string) $request->query('q', ''));
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('company', 'ilike', $like)
                    ->orWhere('contact_name', 'ilike', $like)
                    ->orWhere('city', 'ilike', $like)
                    ->orWhere('code', 'ilike', $like);
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->orderBy('company')->get());
    }

    public function store(Request $request)
    {
        $data = $this->payload($request);
        $data['code'] = DocumentNumbers::code('CLI', Client::class);

        $client = Client::create($data);
        ActivityLogger::log($request->user(), 'client.cree', $client, 'Client '.$client->company.' ouvert.');

        return response()->json($client, 201);
    }

    public function show(Client $client)
    {
        return response()->json([
            'client' => $client->loadCount(['quotes', 'invoices', 'projects']),
            'quotes' => $client->quotes()->with('project:id,name')->latest('issue_date')->limit(12)->get(),
            'invoices' => $client->invoices()->latest('issue_date')->limit(12)->get(),
            'projects' => $client->projects()->with('manager:id,name')->latest()->get(),
            'revenue' => round((float) $client->invoices()->where('status', '!=', 'annulee')->sum('total'), 2),
            'collected' => round((float) Invoice::query()->where('client_id', $client->id)->sum('amount_paid'), 2),
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $client->update($this->payload($request));
        ActivityLogger::log($request->user(), 'client.maj', $client, 'Fiche '.$client->company.' mise à jour.');

        return response()->json($client);
    }

    public function destroy(Request $request, Client $client)
    {
        if ($client->quotes()->exists() || $client->invoices()->exists() || $client->projects()->exists()) {
            return response()->json([
                'message' => 'Ce client a déjà des pièces ou des projets. Passez-le en veille plutôt que de le supprimer.',
            ], 422);
        }

        ActivityLogger::log($request->user(), 'client.supprime', null, 'Client '.$client->company.' retiré.');
        $client->delete();

        return response()->json(['message' => 'Client supprimé.']);
    }

    private function payload(Request $request): array
    {
        return $this->validateData($request, [
            'company' => ['required', 'string', 'max:160'],
            'contact_name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:80'],
            'tax_id' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:prospect,actif,veille'],
        ]);
    }
}
