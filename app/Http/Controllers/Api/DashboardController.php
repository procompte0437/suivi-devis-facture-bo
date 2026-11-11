<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quote;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();
        $prevStart = now()->subMonth()->startOfMonth()->toDateString();
        $prevEnd = now()->subMonth()->endOfMonth()->toDateString();

        $billed = $this->billedBetween($start, $end);
        $previous = $this->billedBetween($prevStart, $prevEnd);
        $collected = (float) Payment::query()->whereBetween('paid_at', [$start, $end])->sum('amount');
        $openQuotes = Quote::query()->whereIn('status', ['brouillon', 'envoye'])->get();
        $openAmount = $openQuotes->sum(fn (Quote $quote) => $quote->display_status === 'expire' ? 0 : (float) $quote->total);
        $openCount = $openQuotes->filter(fn (Quote $quote) => $quote->display_status !== 'expire')->count();

        $activeProjects = Project::query()->with('client:id,company')->whereIn('status', ['cadrage', 'en_cours', 'pause'])->get();
        $averageProgress = $activeProjects->count() ? (int) round($activeProjects->avg('progress')) : 0;

        $created = Quote::query()->whereBetween('issue_date', [$start, $end])->count();
        $accepted = Quote::query()->where('status', 'accepte')->whereBetween('decided_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
        $overdue = Invoice::query()
            ->whereIn('status', ['emise', 'partielle'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereColumn('amount_paid', '<', 'total')
            ->count();

        $delta = $previous > 0 ? (int) round((($billed - $previous) / $previous) * 100) : null;
        $collectionRate = $billed > 0 ? (int) min(100, round(($collected / $billed) * 100)) : 0;

        $pipeline = collect(['brouillon', 'envoye', 'accepte', 'refuse'])->map(function (string $status) {
            $rows = Quote::query()->where('status', $status)->get();

            return [
                'status' => $status,
                'count' => $rows->count(),
                'amount' => round((float) $rows->sum('total'), 2),
            ];
        })->values();

        $spark = collect(range(5, 0))->map(function (int $offset) {
            $month = now()->subMonths($offset);

            return [
                'label' => $month->translatedFormat('M'),
                'total' => $this->billedBetween(
                    $month->copy()->startOfMonth()->toDateString(),
                    $month->copy()->endOfMonth()->toDateString()
                ),
            ];
        })->values();

        return response()->json([
            'month_label' => now()->translatedFormat('F Y'),
            'kpis' => [
                [
                    'key' => 'billed',
                    'label' => 'Facturé du mois',
                    'value' => $billed,
                    'hint' => 'Hors factures annulées',
                    'percent' => $collectionRate,
                    'delta' => $delta,
                    'tone' => 'forest',
                ],
                [
                    'key' => 'collected',
                    'label' => 'Encaissé',
                    'value' => $collected,
                    'hint' => 'Règlements enregistrés',
                    'percent' => $collectionRate,
                    'delta' => null,
                    'tone' => 'brass',
                ],
                [
                    'key' => 'quotes',
                    'label' => 'Devis ouverts',
                    'value' => $openAmount,
                    'hint' => $openCount.' en cours de décision',
                    'percent' => $created > 0 ? (int) min(100, round(($accepted / max($created, 1)) * 100)) : 0,
                    'delta' => null,
                    'tone' => 'forest',
                    'count' => $openCount,
                ],
                [
                    'key' => 'projects',
                    'label' => 'Projets en cours',
                    'value' => $activeProjects->count(),
                    'hint' => 'Avancement moyen '.$averageProgress.' %',
                    'percent' => $averageProgress,
                    'delta' => null,
                    'tone' => 'brass',
                    'money' => false,
                ],
            ],
            'flow' => [
                ['key' => 'creations', 'label' => 'Créations', 'value' => $created],
                ['key' => 'accepted', 'label' => 'Acceptés', 'value' => $accepted],
            ],
            'flow_rows' => [
                ['label' => 'Devis créés', 'value' => $created],
                ['label' => 'Devis acceptés', 'value' => $accepted],
                ['label' => 'Factures en retard', 'value' => $overdue],
            ],
            'pipeline' => $pipeline,
            'spark' => $spark,
            'follow_ups' => Quote::query()
                ->with('client:id,company')
                ->where('status', 'envoye')
                ->whereDate('issue_date', '<=', now()->subDays(7)->toDateString())
                ->where(function ($query) {
                    $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()->toDateString());
                })
                ->orderBy('issue_date')
                ->limit(5)
                ->get(),
            'due' => Invoice::query()
                ->with('client:id,company')
                ->whereIn('status', ['emise', 'partielle'])
                ->whereColumn('amount_paid', '<', 'total')
                ->orderBy('due_date')
                ->limit(6)
                ->get(),
            'projects' => $activeProjects->sortByDesc('progress')->take(5)->values(),
            'activity' => Activity::query()->with('user:id,name')->latest()->limit(7)->get(),
        ]);
    }

    private function billedBetween(string $start, string $end): float
    {
        return round((float) Invoice::query()
            ->where('status', '!=', 'annulee')
            ->whereBetween('issue_date', [$start, $end])
            ->sum('total'), 2);
    }
}
