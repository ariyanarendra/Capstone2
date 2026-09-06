<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdvisorAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'dosen_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'dosen')),
            ],
            'status' => ['nullable', Rule::in(['assigned', 'unassigned'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $students = User::query()
            ->from('users as students')
            ->leftJoin('dosen_mahasiswa as assignments', 'assignments.mahasiswa_id', '=', 'students.id')
            ->leftJoin('users as advisors', 'advisors.id', '=', 'assignments.dosen_id')
            ->where('students.role', 'mahasiswa')
            ->select([
                'students.id as student_id',
                'students.name as mahasiswa_name',
                'students.identifier',
                'students.email',
                'students.program_studi',
                'advisors.id as dosen_id',
                'advisors.name as dosen_name',
                'advisors.identifier as dosen_identifier',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $term = '%'.mb_strtolower($search).'%';
                $query->where(function ($query) use ($term) {
                    $query->whereRaw('LOWER(students.name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(students.identifier) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(students.email) LIKE ?', [$term]);
                });
            })
            ->when($filters['dosen_id'] ?? null, fn ($query, int $dosenId) => $query->where('assignments.dosen_id', $dosenId))
            ->when(($filters['status'] ?? null) === 'assigned', fn ($query) => $query->whereNotNull('assignments.dosen_id'))
            ->when(($filters['status'] ?? null) === 'unassigned', fn ($query) => $query->whereNull('assignments.dosen_id'))
            ->orderBy('students.identifier')
            ->orderBy('students.name')
            ->paginate(10);

        $advisors = User::query()
            ->where('role', 'dosen')
            ->where('is_active', true)
            ->select(['id', 'name', 'identifier', 'program_studi'])
            ->selectSub(function ($query) {
                $query->from('dosen_mahasiswa')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('dosen_mahasiswa.dosen_id', 'users.id');
            }, 'student_count')
            ->orderBy('identifier')
            ->orderBy('name')
            ->get();

        $studentTotal = User::where('role', 'mahasiswa')->count();
        $assignedTotal = DB::table('dosen_mahasiswa')
            ->join('users', 'users.id', '=', 'dosen_mahasiswa.mahasiswa_id')
            ->where('users.role', 'mahasiswa')
            ->count();

        return response()->json([
            ...$students->toArray(),
            'advisors' => $advisors,
            'summary' => [
                'students' => $studentTotal,
                'assigned' => $assignedTotal,
                'unassigned' => max(0, $studentTotal - $assignedTotal),
            ],
        ]);
    }

    public function update(Request $request, User $mahasiswa): JsonResponse
    {
        abort_unless($mahasiswa->role === 'mahasiswa', 404);

        $data = $request->validate([
            'dosen_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'dosen')
                    ->where('is_active', true)),
            ],
        ], [
            'dosen_id.exists' => 'Dosen wali harus merupakan dosen aktif yang tersedia.',
        ]);

        if (empty($data['dosen_id'])) {
            DB::table('dosen_mahasiswa')->where('mahasiswa_id', $mahasiswa->id)->delete();

            return response()->json([
                'message' => 'Penugasan dosen wali berhasil dihapus.',
                'assignment' => null,
            ]);
        }

        if (!$mahasiswa->is_active) {
            return response()->json(['message' => 'Mahasiswa nonaktif tidak dapat diberi dosen wali.'], 422);
        }

        $advisor = User::query()
            ->whereKey($data['dosen_id'])
            ->where('role', 'dosen')
            ->where('is_active', true)
            ->firstOrFail();

        if ($advisor->program_studi !== $mahasiswa->program_studi) {
            return response()->json([
                'message' => 'Program studi dosen wali harus sama dengan program studi mahasiswa.',
            ], 422);
        }

        DB::transaction(function () use ($mahasiswa, $advisor) {
            $assignment = DB::table('dosen_mahasiswa')->where('mahasiswa_id', $mahasiswa->id);

            if ($assignment->exists()) {
                $assignment->update(['dosen_id' => $advisor->id, 'updated_at' => now()]);
            } else {
                DB::table('dosen_mahasiswa')->insert([
                    'mahasiswa_id' => $mahasiswa->id,
                    'dosen_id' => $advisor->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json([
            'message' => 'Dosen wali untuk '.$mahasiswa->name.' berhasil diperbarui.',
            'assignment' => [
                'mahasiswa_id' => $mahasiswa->id,
                'dosen_id' => $advisor->id,
                'dosen_name' => $advisor->name,
                'dosen_identifier' => $advisor->identifier,
            ],
        ]);
    }
}
