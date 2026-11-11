<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Setting;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\DocumentNumbers;
use App\Support\Totals;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $settings = Setting::create([
            'company_name' => 'DOUKOU',
            'tagline' => 'Devis, factures et suivi de projets',
            'address' => 'Quartier Louis, face à la baie',
            'city' => 'Libreville',
            'country' => 'Gabon',
            'email' => 'contact@atelier-oree.ga',
            'phone' => '+241 01 76 44 20',
            'tax_id' => 'NIF 2026 00481 L',
            'tax_rate' => 18,
            'currency' => 'XAF',
            'quote_prefix' => 'DEV',
            'invoice_prefix' => 'FAC',
            'payment_note' => "Virement BGFIBank Libreville\nRIB 40021 00001 12345678901 42\nIntitulé : DOUKOU",
            'footer' => 'Devis valable jusqu’à la date indiquée. Acompte de 40 % à l’ouverture du projet.',
        ]);

        $aurore = $this->user('Aurore Blanc', 'aurore.blanc@oree.fr', 'admin', 'Directrice', '+241 06 22 18 40');
        $marc = $this->user('Marc Essono', 'marc.essono@oree.fr', 'commercial', 'Chargé de projets', '+241 06 44 90 12');
        $lea = $this->user('Léa Nguema', 'lea.nguema@oree.fr', 'comptable', 'Comptable', '+241 06 11 73 08');
        $this->user('Yannick Ondo', 'yannick.ondo@oree.fr', 'chef_projet', 'Chef de projet', '+241 06 30 52 77');
        $this->user('Paul Moussavou', 'paul.moussavou@oree.fr', 'commercial', 'Ancien collaborateur', null, false);

        $clients = [
            'olamba' => $this->client('CLI-001', 'Hôtel Olamba', 'Nadège Obame', 'n.obame@olamba.ga', 'Libreville', 'actif'),
            'clinique' => $this->client('CLI-002', 'Clinique Les Flamboyants', 'Dr Hervé Mba', 'direction@flamboyants.ga', 'Libreville', 'actif'),
            'lagune' => $this->client('CLI-003', 'Studio Lagune', 'Inès Koumba', 'ines@studiolagune.ga', 'Port-Gentil', 'actif'),
            'okoume' => $this->client('CLI-004', 'Coopérative Bois d’Okoumé', 'Jean-Paul Ndong', 'jp.ndong@okoume.ga', 'Oyem', 'actif'),
            'meridien' => $this->client('CLI-005', 'Agence Méridien', 'Sarah Bekale', 'sarah@meridien.ga', 'Libreville', 'prospect'),
            'canopee' => $this->client('CLI-006', 'Fondation Canopée', 'Amina Nzoghe', 'amina@canopee.ga', 'Libreville', 'actif'),
            'nka' => $this->client('CLI-007', 'Maison Nka', 'Chloé Moussavou', 'chloe@maison-nka.ga', 'Franceville', 'veille'),
            'ngounie' => $this->client('CLI-008', 'BTP Ngounié', 'Serge Boukinda', 's.boukinda@btp-ngounie.ga', 'Mouila', 'actif'),
        ];

        // Compte client de démonstration : la clinique a un devis envoyé en attente de réponse.
        $this->user('Hervé Mba', 'direction@flamboyants.ga', 'client', 'Directeur, Clinique Les Flamboyants', null)
            ->update(['client_id' => $clients['clinique']->id]);

        $projects = [
            'olamba' => $this->project('PRJ-001', $clients['olamba'], $aurore, 'Identité et suites Olamba', 'en_cours', 62, 7800000, [
                ['Cadrage', true], ['Direction artistique', true], ['Déclinaisons', false], ['Livraison', false],
            ]),
            'clinique' => $this->project('PRJ-002', $clients['clinique'], $marc, 'Signalétique de la clinique', 'en_cours', 41, 4200000, [
                ['Relevé des espaces', true], ['Plan de signes', true], ['Fabrication', false], ['Pose', false],
            ]),
            'lagune' => $this->project('PRJ-003', $clients['lagune'], $marc, 'Parcours de réservation', 'cadrage', 18, 6100000, [
                ['Atelier de cadrage', true], ['Prototype', false], ['Mise en ligne', false],
            ]),
            'okoume' => $this->project('PRJ-004', $clients['okoume'], $aurore, 'Catalogue bois 2026', 'livre', 100, 2400000, [
                ['Prises de vue', true], ['Mise en page', true], ['Impression', true],
            ]),
            'canopee' => $this->project('PRJ-005', $clients['canopee'], $aurore, 'Scénographie Canopée', 'pause', 28, 9600000, [
                ['Intention', true], ['Maquette', false], ['Chantier', false],
            ]),
            'ngounie' => $this->project('PRJ-006', $clients['ngounie'], $marc, 'Vitrine du siège', 'en_cours', 74, 5300000, [
                ['Esquisse', true], ['Plans', true], ['Chantier', false], ['Réception', false],
            ]),
        ];

        $this->quote($settings, $aurore, $clients['okoume'], $projects['okoume'], 'Catalogue et prises de vue', 'accepte', now()->subMonths(4)->startOfMonth()->addDays(2), now()->subMonths(4)->startOfMonth()->addDays(20), now()->subMonths(4)->startOfMonth()->addDays(6), [
            ['Prises de vue essence', 2, 'j', 450000],
            ['Mise en page du catalogue', 1, 'forfait', 900000],
        ]);

        $this->invoiceMonth($settings, $lea, $clients['okoume'], $projects['okoume'], 'Catalogue bois — solde', 5, 1800000, true);
        $this->invoiceMonth($settings, $lea, $clients['olamba'], null, 'Accompagnement éditorial', 4, 2400000, true);
        $this->invoiceMonth($settings, $lea, $clients['canopee'], null, 'Étude de parcours', 3, 1600000, true);
        $this->invoiceMonth($settings, $lea, $clients['ngounie'], null, 'Diagnostic du siège', 2, 2100000, true);
        $this->invoiceMonth($settings, $lea, $clients['clinique'], $projects['clinique'], 'Acompte signalétique', 1, 1500000, false);

        $olambaQuote = $this->quote($settings, $aurore, $clients['olamba'], $projects['olamba'], 'Identité et déclinaisons', 'accepte', now()->startOfMonth()->addDays(2), now()->startOfMonth()->addDays(28), now()->startOfMonth()->addDays(6), [
            ['Conception de l’identité', 1, 'forfait', 2400000],
            ['Charte et déclinaisons', 1, 'forfait', 1600000],
            ['Direction de production', 6, 'j', 280000],
        ]);

        $olambaInvoice = $this->invoiceFromQuote($settings, $lea, $olambaQuote, now()->startOfMonth()->addDays(7), now()->addDays(12));
        $this->pay($olambaInvoice, $lea, round($olambaInvoice->total * 0.4, 2), now()->startOfMonth()->addDays(9), 'virement', 'VIR-OLAMBA-40');

        $this->quote($settings, $marc, $clients['clinique'], $projects['clinique'], 'Signalétique intérieure', 'envoye', now()->subDays(12), now()->addDays(18), null, [
            ['Plan de signes', 1, 'forfait', 780000],
            ['Fabrication et pose', 1, 'forfait', 2600000],
        ]);

        $this->quote($settings, $marc, $clients['lagune'], $projects['lagune'], 'Parcours de réservation', 'brouillon', now()->subDays(1), now()->addDays(30), null, [
            ['Ateliers et prototype', 1, 'forfait', 1900000],
            ['Intégration', 8, 'j', 220000],
        ]);

        $this->quote($settings, $marc, $clients['meridien'], null, 'Refonte du site vitrine', 'refuse', now()->subDays(20), now()->subDays(2), now()->subDays(9), [
            ['Direction de création', 1, 'forfait', 1200000],
            ['Intégration', 5, 'j', 180000],
        ]);

        $this->quote($settings, $aurore, $clients['canopee'], $projects['canopee'], 'Scénographie de l’exposition', 'envoye', now()->subDays(3), now()->addDays(21), null, [
            ['Intention et maquette', 1, 'forfait', 2200000],
            ['Suivi de chantier', 10, 'j', 240000],
        ]);

        $ngounieQuote = $this->quote($settings, $marc, $clients['ngounie'], $projects['ngounie'], 'Vitrine et accueil', 'accepte', now()->startOfMonth()->addDays(4), now()->startOfMonth()->addDays(25), now()->startOfMonth()->addDays(11), [
            ['Esquisse et plans', 1, 'forfait', 1400000],
            ['Suivi de chantier', 12, 'j', 160000],
        ]);
        $ngounieInvoice = $this->invoiceFromQuote($settings, $lea, $ngounieQuote, now()->startOfMonth()->addDays(12), now()->addDays(4));
        $this->pay($ngounieInvoice, $lea, 1500000, now()->subDays(2), 'mobile', 'AIRTEL-1500');

        $this->quote($settings, $marc, $clients['nka'], null, 'Lookbook saison sèche', 'envoye', now()->subDays(40), now()->subDays(6), null, [
            ['Direction artistique', 1, 'forfait', 980000],
            ['Production', 4, 'j', 210000],
        ]);

        ActivityLogger::log($aurore, 'maison.ouverte', null, 'DOUKOU ouvre son portail de pilotage.', now()->subMonths(5));
        ActivityLogger::log($lea, 'facture.reglement', $olambaInvoice, 'Acompte encaissé pour '.$olambaInvoice->number.'.', now()->startOfMonth()->addDays(9));
        ActivityLogger::log($marc, 'devis.relance', null, 'Relance préparée pour la clinique Les Flamboyants.', now()->subDays(1));
        \App\Support\CompanyProvisioner::attachExisting();
    }

    private function user(string $name, string $email, string $role, string $title, ?string $phone, bool $active = true): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'Cedre2026!',
            'role' => $role,
            'job_title' => $title,
            'phone' => $phone,
            'is_active' => $active,
            'email_verified_at' => now(),
        ]);
    }

    private function client(string $code, string $company, string $contact, string $email, string $city, string $status): Client
    {
        return Client::create([
            'code' => $code,
            'company' => $company,
            'contact_name' => $contact,
            'email' => $email,
            'phone' => '+241 01 00 00 00',
            'city' => $city,
            'country' => 'Gabon',
            'status' => $status,
            'address' => 'Centre-ville',
        ]);
    }

    private function project(string $code, Client $client, User $manager, string $name, string $status, int $progress, float $budget, array $steps): Project
    {
        $project = Project::create([
            'code' => $code,
            'client_id' => $client->id,
            'manager_id' => $manager->id,
            'name' => $name,
            'description' => 'Suivi ouvert dans l’atelier.',
            'status' => $status,
            'progress' => $progress,
            'budget' => $budget,
            'start_date' => now()->subMonths(2)->toDateString(),
            'due_date' => now()->addMonths($status === 'livre' ? -1 : 2)->toDateString(),
        ]);

        foreach ($steps as $index => [$title, $done]) {
            $project->milestones()->create([
                'title' => $title,
                'done' => $done,
                'position' => $index,
                'due_date' => now()->addWeeks($index + 1)->toDateString(),
            ]);
        }

        return $project;
    }

    private function quote(Setting $settings, User $author, Client $client, ?Project $project, string $title, string $status, Carbon $issue, ?Carbon $valid, ?Carbon $decided, array $lines): Quote
    {
        $totals = Totals::fromLines(array_map(fn ($line) => [
            'designation' => $line[0],
            'quantity' => $line[1],
            'unit' => $line[2],
            'unit_price' => $line[3],
        ], $lines), 0, (float) $settings->tax_rate);

        $quote = new Quote([
            'number' => DocumentNumbers::next($settings->quote_prefix, Quote::class),
            'client_id' => $client->id,
            'project_id' => $project?->id,
            'created_by' => $author->id,
            'title' => $title,
            'status' => $status,
            'issue_date' => $issue->toDateString(),
            'valid_until' => $valid?->toDateString(),
            'decided_at' => $decided,
            'tax_rate' => $totals['tax_rate'],
            'discount' => $totals['discount'],
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
            'notes' => 'Merci pour votre confiance. Les délais courent à réception de l’acompte.',
        ]);
        $quote->created_at = $issue;
        $quote->updated_at = $decided ?? $issue;
        $quote->save();
        $quote->lines()->createMany($totals['lines']);

        ActivityLogger::log($author, 'devis.cree', $quote, 'Devis '.$quote->number.' — '.$title.'.', $issue);

        return $quote->fresh('lines');
    }

    private function invoiceFromQuote(Setting $settings, User $author, Quote $quote, Carbon $issue, Carbon $due): Invoice
    {
        $invoice = new Invoice([
            'number' => DocumentNumbers::next($settings->invoice_prefix, Invoice::class),
            'client_id' => $quote->client_id,
            'project_id' => $quote->project_id,
            'quote_id' => $quote->id,
            'created_by' => $author->id,
            'title' => $quote->title,
            'status' => 'emise',
            'issue_date' => $issue->toDateString(),
            'due_date' => $due->toDateString(),
            'tax_rate' => $quote->tax_rate,
            'discount' => $quote->discount,
            'subtotal' => $quote->subtotal,
            'tax_amount' => $quote->tax_amount,
            'total' => $quote->total,
            'amount_paid' => 0,
            'notes' => $quote->notes,
        ]);
        $invoice->created_at = $issue;
        $invoice->updated_at = $issue;
        $invoice->save();

        foreach ($quote->lines as $line) {
            $invoice->lines()->create($line->only([
                'position', 'designation', 'detail', 'quantity', 'unit', 'unit_price', 'line_total',
            ]));
        }

        ActivityLogger::log($author, 'facture.creee', $invoice, 'Facture '.$invoice->number.' émise.', $issue);

        return $invoice->fresh();
    }

    private function invoiceMonth(Setting $settings, User $author, Client $client, ?Project $project, string $title, int $monthsAgo, float $amount, bool $paid): Invoice
    {
        $issue = now()->subMonths($monthsAgo)->startOfMonth()->addDays(6);
        $totals = Totals::fromLines([[
            'designation' => $title,
            'quantity' => 1,
            'unit' => 'forfait',
            'unit_price' => $amount,
        ]], 0, (float) $settings->tax_rate);

        $invoice = new Invoice([
            'number' => DocumentNumbers::next($settings->invoice_prefix, Invoice::class),
            'client_id' => $client->id,
            'project_id' => $project?->id,
            'created_by' => $author->id,
            'title' => $title,
            'status' => 'emise',
            'issue_date' => $issue->toDateString(),
            'due_date' => $issue->copy()->addDays(30)->toDateString(),
            'tax_rate' => $totals['tax_rate'],
            'discount' => 0,
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
            'amount_paid' => 0,
        ]);
        $invoice->created_at = $issue;
        $invoice->updated_at = $issue;
        $invoice->save();
        $invoice->lines()->createMany($totals['lines']);

        if ($paid) {
            $this->pay($invoice, $author, (float) $invoice->total, $issue->copy()->addDays(8), 'virement', 'SOLDE-'.$invoice->number);
        }

        return $invoice;
    }

    private function pay(Invoice $invoice, User $author, float $amount, Carbon $date, string $method, string $reference): void
    {
        Payment::create([
            'invoice_id' => $invoice->id,
            'created_by' => $author->id,
            'amount' => $amount,
            'paid_at' => $date->toDateString(),
            'method' => $method,
            'reference' => $reference,
        ]);
        $invoice->refresh();
        $invoice->refreshPaymentStatus();
    }
}
