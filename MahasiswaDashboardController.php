<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Perwalian;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MahasiswaDashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $studentId = $request->user()->id;
        $baseQuery = Perwalian::query()
            ->where('mahasiswa_id', $studentId)
            ->whereNull('cancelled_at');
        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $advisor = User::query()
            ->from('users as advisors')
            ->join('dosen_mahasiswa as assignments', 'assignments.dosen_id', '=', 'advisors.id')
            ->where('assignments.mahasiswa_id', $studentId)
            ->where('advisors.role', 'dosen')
            ->select([
                'advisors.id',
                'advisors.name',
                'advisors.email',
                'advisors.identifier',
                'advisors.program_studi',
            ])
            ->first();

        $recentRecords = (clone $baseQuery)
            ->with('dosen:id,name,identifier')
            ->latest('tanggal')
            ->latest('id')
            ->limit(5)
            ->get();

        return response()->json([
            'advisor' => $advisor,
            'summary' => [
                'total' => $statusCounts->sum(),
                'diajukan' => (int) ($statusCounts['diajukan'] ?? 0),
                'ditinjau' => (int) ($statusCounts['ditinjau'] ?? 0),
                'selesai' => (int) ($statusCounts['selesai'] ?? 0),
            ],
            'recent_perwalians' => $recentRecords,
        ]);
    }
}
