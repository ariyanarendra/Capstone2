<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Perwalian;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function show(): JsonResponse
    {
        $activeStudents = User::query()
            ->where('role', 'mahasiswa')
            ->where('is_active', true)
            ->count();

        $activeAdvisors = User::query()
            ->where('role', 'dosen')
            ->where('is_active', true)
            ->count();

        $baseQuery = Perwalian::query()->whereNull('cancelled_at');
        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $recentRecords = (clone $baseQuery)
            ->with([
                'mahasiswa:id,name,identifier',
                'dosen:id,name,identifier',
            ])
            ->latest('tanggal')
            ->latest('id')
            ->limit(5)
            ->get();

        return response()->json([
            'summary' => [
                'active_students' => $activeStudents,
                'active_advisors' => $activeAdvisors,
                'total' => $statusCounts->sum(),
                'diajukan' => (int) ($statusCounts['diajukan'] ?? 0),
                'ditinjau' => (int) ($statusCounts['ditinjau'] ?? 0),
                'selesai' => (int) ($statusCounts['selesai'] ?? 0),
            ],
            'recent_perwalians' => $recentRecords,
        ]);
    }
}
