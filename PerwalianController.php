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
use Illuminate\Validation\ValidationException;

class PerwalianController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['diajukan', 'ditinjau', 'selesai', 'dibatalkan'])],
            'scope' => ['nullable', Rule::in(['active', 'archive'])],
            'dosen_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'dosen')),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $baseQuery = $this->scopedQuery($request);
        $summaryQuery = clone $baseQuery;
        $statusCounts = $summaryQuery
            ->whereNull('cancelled_at')
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $cancelledCount = (clone $baseQuery)->whereNotNull('cancelled_at')->count();

        $perwalians = $baseQuery
            ->with([
                'mahasiswa:id,name,identifier,program_studi',
                'dosen:id,name,identifier',
            ])
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $term = '%'.mb_strtolower($search).'%';
                $query->where(function (Builder $query) use ($term) {
                    $query->whereRaw('LOWER(topik) LIKE ?', [$term])
                        ->orWhereHas('mahasiswa', function (Builder $query) use ($term) {
                            $query->whereRaw('LOWER(name) LIKE ?', [$term])
                                ->orWhereRaw('LOWER(identifier) LIKE ?', [$term]);
                        });
                });
            })
            ->when(($filters['status'] ?? null) === 'dibatalkan', fn (Builder $query) => $query->whereNotNull('cancelled_at'))
            ->when(
                ($filters['status'] ?? null) && ($filters['status'] ?? null) !== 'dibatalkan',
                fn (Builder $query) => $query->whereNull('cancelled_at')->where('status', $filters['status'])
            )
            ->when(
                ($filters['scope'] ?? null) === 'active',
                fn (Builder $query) => $query->whereNull('cancelled_at')->whereIn('status', ['diajukan', 'ditinjau'])
            )
            ->when(
                ($filters['scope'] ?? null) === 'archive',
                fn (Builder $query) => $query->whereNull('cancelled_at')->where('status', 'selesai')
            )
            ->when($filters['dosen_id'] ?? null, fn (Builder $query, int $dosenId) => $query->where('dosen_id', $dosenId))
            ->when(
                ($filters['scope'] ?? null) === 'active',
                fn (Builder $query) => $query->orderByRaw("CASE status WHEN 'diajukan' THEN 1 WHEN 'ditinjau' THEN 2 ELSE 3 END")
            )
            ->latest('tanggal')
            ->latest('id');

        $perwalians = $perwalians->paginate(10);

        $advisors = User::query()
            ->where('role', 'dosen')
            ->select(['id', 'name', 'identifier'])
            ->orderBy('name')
            ->get();

        return response()->json([
            ...$perwalians->toArray(),
            'advisors' => $advisors,
            'summary' => [
                'total' => $statusCounts->sum() + $cancelledCount,
                'diajukan' => (int) ($statusCounts['diajukan'] ?? 0),
                'ditinjau' => (int) ($statusCounts['ditinjau'] ?? 0),
                'selesai' => (int) ($statusCounts['selesai'] ?? 0),
                'dibatalkan' => $cancelledCount,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->editableRules());

        $dosenId = DB::table('dosen_mahasiswa')
            ->where('mahasiswa_id', $request->user()->id)
            ->value('dosen_id');

        abort_unless($dosenId, 422, 'Dosen wali belum ditetapkan oleh admin.');

        $perwalian = Perwalian::create([
            ...$data,
            'mahasiswa_id' => $request->user()->id,
            'dosen_id' => $dosenId,
            'status' => 'diajukan',
        ]);

        return response()->json([
            'message' => 'Catatan perwalian berhasil dikirim kepada Dosen Wali.',
            'perwalian' => $perwalian,
        ], 201);
    }

    public function update(Request $request, Perwalian $perwalian): JsonResponse
    {
        $data = $request->validate($this->editableRules());

        $updated = DB::transaction(function () use ($request, $perwalian, $data) {
            $record = Perwalian::query()->lockForUpdate()->findOrFail($perwalian->id);

            abort_unless((int) $record->mahasiswa_id === (int) $request->user()->id, 404, 'Catatan perwalian tidak ditemukan.');
            $this->ensureEditable($record);

            $record->update($data);

            return $record->fresh();
        });

        return response()->json([
            'message' => 'Catatan perwalian berhasil diperbarui.',
            'perwalian' => $updated,
        ]);
    }

    public function cancel(Request $request, Perwalian $perwalian): JsonResponse
    {
        $data = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $cancelled = DB::transaction(function () use ($request, $perwalian, $data) {
            $record = Perwalian::query()->lockForUpdate()->findOrFail($perwalian->id);

            abort_unless((int) $record->mahasiswa_id === (int) $request->user()->id, 404, 'Catatan perwalian tidak ditemukan.');
            $this->ensureEditable($record);

            $reason = trim((string) ($data['cancellation_reason'] ?? ''));
            $record->update([
                'cancelled_at' => now(),
                'cancellation_reason' => $reason !== '' ? $reason : null,
            ]);

            return $record->fresh();
        });

        return response()->json([
            'message' => 'Pengajuan berhasil dibatalkan dan tetap disimpan sebagai riwayat.',
            'perwalian' => $cancelled,
        ]);
    }

    private function scopedQuery(Request $request): Builder
    {
        return Perwalian::query()
            ->when($request->user()->role === 'mahasiswa', fn (Builder $query) => $query->where('mahasiswa_id', $request->user()->id))
            ->when($request->user()->role === 'dosen', fn (Builder $query) => $query->where('dosen_id', $request->user()->id)->whereNull('cancelled_at'));
    }

    private function editableRules(): array
    {
        return [
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'topik' => ['required', 'string', 'min:3', 'max:255'],
            'hasil_konsultasi' => ['required', 'string', 'min:10', 'max:5000'],
            'rencana_tindak_lanjut' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function ensureEditable(Perwalian $perwalian): void
    {
        if ($perwalian->cancelled_at || $perwalian->getRawOriginal('status') !== 'diajukan') {
            throw ValidationException::withMessages([
                'status' => 'Catatan hanya dapat diubah atau dibatalkan selama masih berstatus Diajukan.',
            ]);
        }
    }
}
