<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Perwalian;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DosenStudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'program_studi' => ['nullable', Rule::in(['Informatika', 'Sistem Informasi'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $dosenId = $request->user()->id;
        $baseQuery = User::query()
            ->from('users as students')
            ->join('dosen_mahasiswa as assignments', 'assignments.mahasiswa_id', '=', 'students.id')
            ->where('assignments.dosen_id', $dosenId)
            ->where('students.role', 'mahasiswa')
            ->where('students.is_active', true);

        $students = (clone $baseQuery)
            ->select([
                'students.id',
                'students.name',
                'students.identifier',
                'students.email',
                'students.program_studi',
            ])
            ->selectSub(
                Perwalian::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('perwalians.mahasiswa_id', 'students.id')
                    ->where('perwalians.dosen_id', $dosenId)
                    ->whereNull('perwalians.cancelled_at'),
                'perwalian_count'
            )
            ->selectSub(
                Perwalian::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('perwalians.mahasiswa_id', 'students.id')
                    ->where('perwalians.dosen_id', $dosenId)
                    ->whereNull('perwalians.cancelled_at')
                    ->whereIn('perwalians.status', ['diajukan', 'ditinjau']),
                'active_perwalian_count'
            )
            ->selectSub(
                Perwalian::query()
                    ->selectRaw('CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END')
                    ->whereColumn('perwalians.mahasiswa_id', 'students.id')
                    ->where('perwalians.dosen_id', $dosenId)
                    ->whereNull('perwalians.cancelled_at')
                    ->whereIn('perwalians.status', ['diajukan', 'ditinjau']),
                'has_active_perwalian'
            )
            ->selectSub(
                Perwalian::query()
                    ->select('tanggal')
                    ->whereColumn('perwalians.mahasiswa_id', 'students.id')
                    ->where('perwalians.dosen_id', $dosenId)
                    ->whereNull('perwalians.cancelled_at')
                    ->latest('tanggal')
                    ->latest('id')
                    ->limit(1),
                'last_perwalian_date'
            )
            ->selectSub(
                Perwalian::query()
                    ->select('status')
                    ->whereColumn('perwalians.mahasiswa_id', 'students.id')
                    ->where('perwalians.dosen_id', $dosenId)
                    ->whereNull('perwalians.cancelled_at')
                    ->latest('tanggal')
                    ->latest('id')
                    ->limit(1),
                'last_perwalian_status'
            )
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $term = '%'.mb_strtolower($search).'%';
                $query->where(function (Builder $query) use ($term) {
                    $query->whereRaw('LOWER(students.name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(students.identifier) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(students.email) LIKE ?', [$term]);
                });
            })
            ->when($filters['program_studi'] ?? null, fn (Builder $query, string $program) => $query->where('students.program_studi', $program))
            ->orderByDesc('has_active_perwalian')
            ->orderBy('students.identifier')
            ->paginate(10);

        $summaryRows = (clone $baseQuery)
            ->selectRaw('students.program_studi, COUNT(students.id) as aggregate')
            ->groupBy('students.program_studi')
            ->pluck('aggregate', 'program_studi');

        $total = (clone $baseQuery)->count();
        $withPerwalian = (clone $baseQuery)
            ->whereExists(function ($query) use ($dosenId) {
                $query->from('perwalians')
                    ->selectRaw('1')
                    ->whereColumn('perwalians.mahasiswa_id', 'students.id')
                    ->where('perwalians.dosen_id', $dosenId)
                    ->whereNull('perwalians.cancelled_at');
            })
            ->count();
        $activeRecords = DB::table('perwalians')
            ->join('users as students', 'students.id', '=', 'perwalians.mahasiswa_id')
            ->join('dosen_mahasiswa as assignments', 'assignments.mahasiswa_id', '=', 'students.id')
            ->where('assignments.dosen_id', $dosenId)
            ->where('students.role', 'mahasiswa')
            ->where('students.is_active', true)
            ->whereNull('perwalians.cancelled_at')
            ->whereIn('perwalians.status', ['diajukan', 'ditinjau'])
            ->count();

        return response()->json([
            ...$students->toArray(),
            'summary' => [
                'total' => $total,
                'informatika' => (int) ($summaryRows['Informatika'] ?? 0),
                'sistem_informasi' => (int) ($summaryRows['Sistem Informasi'] ?? 0),
                'with_perwalian' => $withPerwalian,
                'without_perwalian' => max(0, $total - $withPerwalian),
                'active_records' => $activeRecords,
            ],
        ]);
    }

    public function show(Request $request, User $mahasiswa): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['diajukan', 'ditinjau', 'selesai'])],
            'scope' => ['nullable', Rule::in(['active', 'archive'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $dosenId = $request->user()->id;
        $isAssigned = $mahasiswa->role === 'mahasiswa'
            && DB::table('dosen_mahasiswa')
                ->where('dosen_id', $dosenId)
                ->where('mahasiswa_id', $mahasiswa->id)
                ->exists();

        abort_unless($isAssigned, 404, 'Mahasiswa wali tidak ditemukan.');

        $baseQuery = Perwalian::query()
            ->where('dosen_id', $dosenId)
            ->where('mahasiswa_id', $mahasiswa->id)
            ->whereNull('cancelled_at');

        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $records = $baseQuery
            ->when(($filters['scope'] ?? null) === 'active', fn (Builder $query) => $query->whereIn('status', ['diajukan', 'ditinjau']))
            ->when(($filters['scope'] ?? null) === 'archive', fn (Builder $query) => $query->where('status', 'selesai'))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(($filters['scope'] ?? null) === 'active', function (Builder $query) {
                $query->orderByRaw("CASE status WHEN 'diajukan' THEN 1 WHEN 'ditinjau' THEN 2 ELSE 3 END");
            })
            ->latest('tanggal')
            ->latest('id')
            ->paginate(10);

        return response()->json([
            ...$records->toArray(),
            'student' => $mahasiswa->only(['id', 'name', 'email', 'identifier', 'program_studi']),
            'summary' => [
                'total' => $statusCounts->sum(),
                'diajukan' => (int) ($statusCounts['diajukan'] ?? 0),
                'ditinjau' => (int) ($statusCounts['ditinjau'] ?? 0),
                'selesai' => (int) ($statusCounts['selesai'] ?? 0),
            ],
        ]);
    }

    public function updateStatus(Request $request, Perwalian $perwalian): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['ditinjau', 'selesai'])],
            'catatan_dosen' => ['required', 'string', 'max:2000'],
        ]);

        $updated = DB::transaction(function () use ($request, $perwalian, $data) {
            $record = Perwalian::query()->lockForUpdate()->findOrFail($perwalian->id);
            abort_unless(
                (int) $record->dosen_id === (int) $request->user()->id && !$record->cancelled_at,
                404,
                'Catatan perwalian tidak ditemukan.'
            );

            $currentStatus = $record->getRawOriginal('status');
            $allowedTransitions = [
                'diajukan' => ['ditinjau', 'selesai'],
                'ditinjau' => ['selesai'],
                'selesai' => [],
            ];

            if ($data['status'] !== $currentStatus
                && !in_array($data['status'], $allowedTransitions[$currentStatus] ?? [], true)) {
                abort(422, 'Status yang sudah selesai tidak dapat dikembalikan ke tahap sebelumnya.');
            }

            $record->update([
                'status' => $data['status'],
                'catatan_dosen' => $data['catatan_dosen'],
            ]);

            return $record->fresh();
        });

        return response()->json([
            'message' => 'Tinjauan dan status perwalian berhasil disimpan.',
            'perwalian' => $updated,
        ]);
    }
}
