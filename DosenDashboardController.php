<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Perwalian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DosenDashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $dosenId = $request->user()->id;
        $totalStudents = DB::table('dosen_mahasiswa')
            ->where('dosen_id', $dosenId)
            ->count();

        $baseQuery = Perwalian::query()
            ->where('dosen_id', $dosenId)
            ->whereNull('cancelled_at');
        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $studentsWithRecords = (clone $baseQuery)
            ->distinct()
            ->count('mahasiswa_id');

        $activeRecords = (clone $baseQuery)
            ->whereIn('status', ['diajukan', 'ditinjau'])
            ->with('mahasiswa:id,name,identifier,program_studi')
            ->orderByRaw("CASE status WHEN 'diajukan' THEN 1 WHEN 'ditinjau' THEN 2 ELSE 3 END")
            ->latest('tanggal')
            ->latest('id')
            ->limit(5)
            ->get();

        return response()->json([
            'summary' => [
                'total_students' => $totalStudents,
                'students_with_records' => $studentsWithRecords,
                'students_without_records' => max(0, $totalStudents - $studentsWithRecords),
                'total_records' => $statusCounts->sum(),
                'diajukan' => (int) ($statusCounts['diajukan'] ?? 0),
                'ditinjau' => (int) ($statusCounts['ditinjau'] ?? 0),
                'selesai' => (int) ($statusCounts['selesai'] ?? 0),
            ],
            'recent_perwalians' => $activeRecords,
        ]);
    }
}
