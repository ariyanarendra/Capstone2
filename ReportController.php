<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function perwalian(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'program_studi' => ['nullable', Rule::in(['Informatika', 'Sistem Informasi'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $baseQuery = $this->perwalianQuery($filters);
        $totals = (clone $baseQuery)
            ->selectRaw('COUNT(p.id) as total')
            ->selectRaw("SUM(CASE WHEN p.status = 'diajukan' THEN 1 ELSE 0 END) as diajukan")
            ->selectRaw("SUM(CASE WHEN p.status = 'ditinjau' THEN 1 ELSE 0 END) as ditinjau")
            ->selectRaw("SUM(CASE WHEN p.status = 'selesai' THEN 1 ELSE 0 END) as selesai")
            ->first();

        $advisorActivity = (clone $baseQuery)
            ->join('users as advisors', 'advisors.id', '=', 'p.dosen_id')
            ->select(['advisors.id', 'advisors.name', 'advisors.identifier'])
            ->selectRaw('COUNT(p.id) as total')
            ->selectRaw("SUM(CASE WHEN p.status = 'diajukan' THEN 1 ELSE 0 END) as diajukan")
            ->selectRaw("SUM(CASE WHEN p.status = 'ditinjau' THEN 1 ELSE 0 END) as ditinjau")
            ->selectRaw("SUM(CASE WHEN p.status = 'selesai' THEN 1 ELSE 0 END) as selesai")
            ->groupBy('advisors.id', 'advisors.name', 'advisors.identifier')
            ->get()
            ->keyBy('id');

        $studentCounts = DB::table('dosen_mahasiswa as assignment')
            ->join('users as students', 'students.id', '=', 'assignment.mahasiswa_id')
            ->when($filters['program_studi'] ?? null, fn (Builder $query, string $program) => $query->where('students.program_studi', $program))
            ->selectRaw('assignment.dosen_id, COUNT(assignment.mahasiswa_id) as total')
            ->groupBy('assignment.dosen_id')
            ->pluck('total', 'dosen_id');

        $advisors = User::query()
            ->where('role', 'dosen')
            ->select(['id', 'name', 'identifier', 'program_studi'])
            ->orderBy('name')
            ->get()
            ->map(function (User $advisor) use ($advisorActivity, $studentCounts) {
                $activity = $advisorActivity->get($advisor->id);
                $total = (int) ($activity->total ?? 0);
                $completed = (int) ($activity->selesai ?? 0);

                return [
                    'id' => $advisor->id,
                    'name' => $advisor->name,
                    'identifier' => $advisor->identifier,
                    'program_studi' => $advisor->program_studi,
                    'students' => (int) ($studentCounts[$advisor->id] ?? 0),
                    'total' => $total,
                    'diajukan' => (int) ($activity->diajukan ?? 0),
                    'ditinjau' => (int) ($activity->ditinjau ?? 0),
                    'selesai' => $completed,
                    'completion_rate' => $total > 0 ? round(($completed / $total) * 100) : 0,
                ];
            });

        $programs = (clone $baseQuery)
            ->select('students.program_studi')
            ->selectRaw('COUNT(p.id) as total')
            ->selectRaw("SUM(CASE WHEN p.status = 'selesai' THEN 1 ELSE 0 END) as selesai")
            ->groupBy('students.program_studi')
            ->orderBy('students.program_studi')
            ->get()
            ->map(fn ($program) => [
                'name' => $program->program_studi ?: 'Belum ditentukan',
                'total' => (int) $program->total,
                'selesai' => (int) $program->selesai,
                'completion_rate' => (int) $program->total > 0
                    ? round(((int) $program->selesai / (int) $program->total) * 100)
                    : 0,
            ]);

        $total = (int) ($totals->total ?? 0);
        $completed = (int) ($totals->selesai ?? 0);

        return response()->json([
            'summary' => [
                'total' => $total,
                'diajukan' => (int) ($totals->diajukan ?? 0),
                'ditinjau' => (int) ($totals->ditinjau ?? 0),
                'selesai' => $completed,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100) : 0,
            ],
            'advisors' => $advisors,
            'programs' => $programs,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    private function perwalianQuery(array $filters): Builder
    {
        return DB::table('perwalians as p')
            ->join('users as students', 'students.id', '=', 'p.mahasiswa_id')
            ->whereNull('p.cancelled_at')
            ->when($filters['program_studi'] ?? null, fn (Builder $query, string $program) => $query->where('students.program_studi', $program))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('p.tanggal', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('p.tanggal', '<=', $to));
    }
}
