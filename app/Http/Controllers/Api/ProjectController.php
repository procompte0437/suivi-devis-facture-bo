<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\ActivityLogger;
use App\Support\DocumentNumbers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::query()->with('client:id,company', 'manager:id,name');

        $term = trim((string) $request->query('q', ''));
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('name', 'ilike', $like)
                    ->orWhere('code', 'ilike', $like)
                    ->orWhereHas('client', fn ($client) => $client->where('company', 'ilike', $like));
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->latest()->get());
    }

    public function store(Request $request)
    {
        $data = $this->payload($request);

        $project = DB::transaction(function () use ($data, $request) {
            $milestones = $data['milestones'] ?? $this->defaultMilestones();
            $tasks = $data['tasks'] ?? [];
            $budget = $data['budget_lines'] ?? [];
            unset($data['milestones'], $data['tasks'], $data['budget_lines']);
            $data['code'] = DocumentNumbers::code('PRJ', Project::class);
            $data['manager_id'] = $data['manager_id'] ?? $request->user()->id;

            $project = Project::create($data);
            $this->syncMilestones($project, $milestones);
            $this->syncTasks($project, $tasks);
            $this->syncBudget($project, $budget);

            return $project;
        });

        ActivityLogger::log($request->user(), 'projet.cree', $project, 'Projet '.$project->name.' ouvert.');

        return response()->json($project->load('client', 'manager:id,name', 'milestones', 'tasks.staff', 'budgetLines'), 201);
    }

    public function show(Project $project)
    {
        return response()->json($project->load([
            'client',
            'manager:id,name,job_title',
            'milestones',
            'tasks.staff',
            'budgetLines',
            'quotes' => fn ($query) => $query->latest('issue_date'),
            'invoices' => fn ($query) => $query->latest('issue_date'),
        ]));
    }

    public function update(Request $request, Project $project)
    {
        $data = $this->payload($request);

        DB::transaction(function () use ($data, $project) {
            $milestones = $data['milestones'] ?? null;
            $tasks = $data['tasks'] ?? null;
            $budget = $data['budget_lines'] ?? null;
            unset($data['milestones'], $data['tasks'], $data['budget_lines']);
            $project->update($data);
            if (is_array($milestones)) {
                $this->syncMilestones($project, $milestones);
            }
            if (is_array($tasks)) {
                $this->syncTasks($project, $tasks);
            }
            if (is_array($budget)) {
                $this->syncBudget($project, $budget);
            }
        });

        ActivityLogger::log($request->user(), 'projet.maj', $project, 'Projet '.$project->name.' mis à jour.');

        return response()->json($project->fresh()->load('client', 'manager:id,name', 'milestones', 'tasks.staff', 'budgetLines'));
    }

    public function destroy(Request $request, Project $project)
    {
        if ($project->invoices()->exists()) {
            return response()->json([
                'message' => 'Ce projet porte déjà des factures. Clôturez-le plutôt que de le supprimer.',
            ], 422);
        }

        ActivityLogger::log($request->user(), 'projet.supprime', null, 'Projet '.$project->name.' retiré.');
        $project->delete();

        return response()->json(['message' => 'Projet supprimé.']);
    }

    private function payload(Request $request): array
    {
        return $this->validateData($request, [
            'client_id' => ['required', 'exists:clients,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'status' => ['required', 'in:cadrage,en_cours,pause,livre,clos'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'milestones' => ['nullable', 'array'],
            'milestones.*.title' => ['required', 'string', 'max:160'],
            'milestones.*.due_date' => ['nullable', 'date'],
            'milestones.*.done' => ['nullable', 'boolean'],
            'tasks' => ['nullable', 'array'],
            'tasks.*.title' => ['required', 'string', 'max:180'],
            'tasks.*.staff_id' => ['nullable', 'exists:staff,id'],
            'tasks.*.start_date' => ['nullable', 'date'],
            'tasks.*.end_date' => ['nullable', 'date'],
            'tasks.*.progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'budget_lines' => ['nullable', 'array'],
            'budget_lines.*.label' => ['required', 'string', 'max:160'],
            'budget_lines.*.planned' => ['nullable', 'numeric', 'min:0'],
            'budget_lines.*.spent' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function syncMilestones(Project $project, array $milestones): void
    {
        $project->milestones()->delete();

        foreach (array_values($milestones) as $index => $milestone) {
            $project->milestones()->create([
                'title' => $milestone['title'],
                'due_date' => $milestone['due_date'] ?? null,
                'done' => (bool) ($milestone['done'] ?? false),
                'position' => $index,
        ]);
        }
    }

    private function syncTasks(Project $project, array $tasks): void
    {
        $project->tasks()->delete();
        foreach (array_values($tasks) as $index => $task) {
            if (! trim((string) ($task['title'] ?? ''))) {
                continue;
            }
            $project->tasks()->create([
                'title' => $task['title'],
                'staff_id' => $task['staff_id'] ?? null,
                'start_date' => $task['start_date'] ?? $project->start_date,
                'end_date' => $task['end_date'] ?? $project->due_date,
                'progress' => (int) ($task['progress'] ?? 0),
                'position' => $index,
            ]);
        }
    }

    private function syncBudget(Project $project, array $lines): void
    {
        $project->budgetLines()->delete();
        foreach (array_values($lines) as $index => $line) {
            if (! trim((string) ($line['label'] ?? ''))) {
                continue;
            }
            $project->budgetLines()->create([
                'label' => $line['label'],
                'planned' => $line['planned'] ?? 0,
                'spent' => $line['spent'] ?? 0,
                'position' => $index,
            ]);
        }
    }

    private function defaultMilestones(): array
    {
        return [
            ['title' => 'Cadrage', 'done' => false],
            ['title' => 'Production', 'done' => false],
            ['title' => 'Livraison', 'done' => false],
        ];
    }
}
