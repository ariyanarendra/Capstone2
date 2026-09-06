<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    private const MANAGED_ROLES = ['dosen', 'mahasiswa'];
    private const PROGRAM_STUDI = ['Informatika', 'Sistem Informasi'];
    private const STUDENT_PREFIXES = [
        'Informatika' => '1224',
        'Sistem Informasi' => '3224',
    ];

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(self::MANAGED_ROLES)],
            'program_studi' => ['nullable', Rule::in(self::PROGRAM_STUDI)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $users = User::query()
            ->select(['id', 'name', 'email', 'identifier', 'role', 'program_studi', 'is_active', 'created_at'])
            ->whereIn('role', self::MANAGED_ROLES)
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $term = '%'.mb_strtolower($search).'%';
                $query->where(function ($query) use ($term) {
                    $query->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(identifier) LIKE ?', [$term]);
                });
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when($filters['program_studi'] ?? null, fn ($query, string $programStudi) => $query->where('program_studi', $programStudi))
            ->when(isset($filters['status']), fn ($query) => $query->where('is_active', $filters['status'] === 'active'))
            ->orderBy('identifier')
            ->orderBy('name')
            ->paginate(10);

        $currentUserId = $request->user()->id;
        $referencedUserIds = $this->referencedUserIds($users->getCollection()->pluck('id')->all());

        $users->getCollection()->transform(function (User $user) use ($currentUserId, $referencedUserIds) {
            $hasAcademicRelations = in_array($user->id, $referencedUserIds, true);
            $user->setAttribute('is_current_user', $user->id === $currentUserId);
            $user->setAttribute('has_academic_relations', $hasAcademicRelations);
            $user->setAttribute('can_change_role', $user->id !== $currentUserId && !$hasAcademicRelations);
            $user->setAttribute('can_delete', $user->id !== $currentUserId && !$hasAcademicRelations);

            return $user;
        });

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalizeIdentityInput($request);
        $data = $request->validate($this->userRules(null, $request->all()), $this->userMessages());
        $data['program_studi'] = $data['role'] === 'admin' ? null : ($data['program_studi'] ?? null);
        $data['is_active'] = true;
        $data['must_change_password'] = true;

        $user = User::create($data);

        return response()->json([
            'message' => 'Akun pengguna berhasil ditambahkan.',
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureManagedUser($user);
        $this->normalizeIdentityInput($request);
        $data = $request->validate($this->userRules($user, $request->all()), $this->userMessages());
        unset($data['password']);

        if ($data['identifier'] !== $user->identifier) {
            return response()->json([
                'message' => $user->role === 'dosen'
                    ? 'Kode dosen tidak dapat diubah setelah akun dibuat.'
                    : 'NIM tidak dapat diubah setelah akun dibuat.',
            ], 422);
        }

        if ($request->user()->is($user) && $data['role'] !== 'admin') {
            return response()->json(['message' => 'Administrator yang sedang digunakan tidak dapat mengubah role dirinya sendiri.'], 422);
        }

        if ($data['role'] !== $user->role && $this->hasAcademicRelations($user)) {
            return response()->json(['message' => 'Role tidak dapat diubah karena akun sudah terhubung dengan data akademik.'], 422);
        }

        $data['program_studi'] = $data['role'] === 'admin' ? null : ($data['program_studi'] ?? null);
        $user->update($data);

        return response()->json([
            'message' => 'Data pengguna berhasil diperbarui.',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function identifierSuggestion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(self::MANAGED_ROLES)],
            'program_studi' => ['nullable', Rule::in(self::PROGRAM_STUDI)],
        ]);

        if ($data['role'] === 'mahasiswa' && empty($data['program_studi'])) {
            return response()->json(['message' => 'Pilih program studi untuk mendapatkan rekomendasi NIM.'], 422);
        }

        $identifier = $this->nextIdentifier($data['role'], $data['program_studi'] ?? null);

        return response()->json([
            'identifier' => $identifier,
            'label' => $data['role'] === 'dosen' ? 'Kode Dosen' : 'NIM',
            'message' => "Rekomendasi identifier berikutnya adalah {$identifier}.",
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->ensureManagedUser($user);
        $data = $request->validate([
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'confirmed'],
        ], [
            'password.letters' => 'Kata sandi sementara harus memiliki setidaknya satu huruf.',
            'password.numbers' => 'Kata sandi sementara harus memiliki setidaknya satu angka.',
        ]);

        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => true,
            'api_token' => null,
        ])->save();

        return response()->json([
            'message' => "Kata sandi sementara untuk {$user->name} berhasil dibuat. Pengguna wajib menggantinya saat login.",
        ]);
    }

    public function updateStatus(Request $request, User $user): JsonResponse
    {
        $this->ensureManagedUser($user);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        if ($request->user()->is($user) && !$data['is_active']) {
            return response()->json(['message' => 'Akun administrator yang sedang digunakan tidak dapat dinonaktifkan.'], 422);
        }

        $user->forceFill([
            'is_active' => $data['is_active'],
            'api_token' => $data['is_active'] ? $user->api_token : null,
        ])->save();

        return response()->json([
            'message' => $data['is_active'] ? 'Akun pengguna berhasil diaktifkan.' : 'Akun pengguna berhasil dinonaktifkan.',
            'user' => $this->userPayload($user),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->ensureManagedUser($user);
        if ($request->user()->is($user)) {
            return response()->json(['message' => 'Akun administrator yang sedang digunakan tidak dapat dihapus.'], 422);
        }

        if ($this->hasAcademicRelations($user)) {
            return response()->json([
                'message' => 'Akun sudah terhubung dengan data akademik. Nonaktifkan akun agar riwayat tetap aman.',
            ], 422);
        }

        DB::transaction(function () use ($user) {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->delete();
        });

        return response()->json(['message' => 'Akun salah input berhasil dihapus permanen.']);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'import_file' => ['required', 'file', 'max:2048', 'mimes:csv,txt'],
            'role_scope' => ['required', Rule::in(self::MANAGED_ROLES)],
        ], [
            'import_file.required' => 'Pilih file CSV yang akan diimpor.',
            'import_file.mimes' => 'File impor harus berformat CSV.',
            'import_file.max' => 'Ukuran file CSV maksimal 2 MB.',
            'role_scope.required' => 'Jenis data pengguna belum dipilih.',
            'role_scope.in' => 'Jenis data pengguna tidak valid.',
        ]);

        $roleScope = (string) $request->input('role_scope');

        $path = $request->file('import_file')->getRealPath();
        $firstLine = file_get_contents($path, false, null, 0, 4096) ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return response()->json(['message' => 'File CSV tidak dapat dibaca.'], 422);
        }

        $expectedHeaders = ['name', 'email', 'identifier', 'role', 'program_studi', 'password'];
        $headers = fgetcsv($handle, 0, $delimiter) ?: [];
        $headers = array_map(fn ($header) => mb_strtolower(trim((string) $header, "\xEF\xBB\xBF \t\n\r\0\x0B")), $headers);

        if ($headers !== $expectedHeaders) {
            fclose($handle);

            return response()->json([
                'message' => 'Susunan kolom CSV tidak sesuai template.',
                'errors' => ['Gunakan: '.implode(',', $expectedHeaders)],
            ], 422);
        }

        $rows = [];
        $errors = [];
        $seenEmails = [];
        $seenIdentifiers = [];
        $lineNumber = 1;

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;

            $hasContent = count(array_filter($values, fn ($value) => trim((string) $value) !== '')) > 0;
            if (!$hasContent) {
                continue;
            }

            if (count($rows) >= 500) {
                $errors[] = 'File maksimal berisi 500 pengguna.';
                break;
            }

            if (count($values) !== count($expectedHeaders)) {
                $errors[] = "Baris {$lineNumber}: jumlah kolom tidak sesuai template.";
                continue;
            }

            $row = array_combine($expectedHeaders, array_map(fn ($value) => trim((string) $value), $values));
            $row['email'] = mb_strtolower($row['email']);
            $row['identifier'] = mb_strtoupper($row['identifier']);
            $row['role'] = mb_strtolower($row['role']);
            $row['program_studi'] = $row['program_studi'] !== '' ? $row['program_studi'] : null;

            if ($row['identifier'] === '' || $row['identifier'] === 'AUTO') {
                try {
                    $row['identifier'] = $this->nextIdentifier($roleScope, $row['program_studi'], array_keys($seenIdentifiers));
                } catch (\InvalidArgumentException) {
                    $errors[] = "Baris {$lineNumber}: program studi tidak valid untuk membuat NIM otomatis.";
                }
            }

            $validator = Validator::make($row, $this->userRules(null, $row), [
                'name.required' => 'nama wajib diisi.',
                'email.required' => 'email wajib diisi.',
                'email.email' => 'format email tidak valid.',
                'email.unique' => 'email sudah digunakan.',
                'email.ends_with' => 'email harus menggunakan @gmail.com.',
                'identifier.required' => 'NIM/NIP wajib diisi.',
                'identifier.unique' => 'NIM/NIP sudah digunakan.',
                'role.required' => 'role wajib diisi.',
                'role.in' => 'role harus dosen atau mahasiswa.',
                'program_studi.required' => 'program studi wajib diisi.',
                'password.required' => 'kata sandi sementara wajib diisi.',
                'password.min' => 'kata sandi minimal 8 karakter.',
                'password.letters' => 'kata sandi harus memiliki setidaknya satu huruf.',
                'password.numbers' => 'kata sandi harus memiliki setidaknya satu angka.',
            ]);

            if ($row['role'] !== $roleScope) {
                $errors[] = "Baris {$lineNumber}: role harus {$roleScope} untuk halaman ini.";
            }

            foreach ($validator->errors()->all() as $message) {
                $errors[] = "Baris {$lineNumber}: {$message}";
            }

            if (isset($seenEmails[$row['email']])) {
                $errors[] = "Baris {$lineNumber}: email sama dengan baris {$seenEmails[$row['email']]}.";
            }

            if (isset($seenIdentifiers[$row['identifier']])) {
                $errors[] = "Baris {$lineNumber}: NIM/NIP sama dengan baris {$seenIdentifiers[$row['identifier']]}.";
            }

            $seenEmails[$row['email']] = $lineNumber;
            $seenIdentifiers[$row['identifier']] = $lineNumber;
            $row['program_studi'] = $row['role'] === 'admin' ? null : $row['program_studi'];
            $row['is_active'] = true;
            $row['must_change_password'] = true;
            $rows[] = $row;
        }

        fclose($handle);

        if ($rows === [] && $errors === []) {
            $errors[] = 'File CSV belum berisi data pengguna.';
        }

        if ($errors !== []) {
            return response()->json([
                'message' => 'Impor dibatalkan. Perbaiki data CSV terlebih dahulu.',
                'errors' => array_values(array_unique($errors)),
            ], 422);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                User::create($row);
            }
        });

        return response()->json([
            'message' => count($rows).' akun pengguna berhasil diimpor.',
            'imported' => count($rows),
        ], 201);
    }

    private function userRules(?User $user = null, array $input = []): array
    {
        $role = $input['role'] ?? $user?->role;
        $programStudi = $input['program_studi'] ?? $user?->program_studi;
        $identifierRules = [
            'required',
            'string',
            'max:50',
            Rule::unique('users', 'identifier')->ignore($user?->id),
        ];

        if ($user === null) {
            $identifierRules[] = function (string $attribute, mixed $value, \Closure $fail) use ($role, $programStudi) {
                $identifier = mb_strtoupper(trim((string) $value));

                if ($role === 'dosen' && !preg_match('/^DSN\d{3,}$/', $identifier)) {
                    $fail('Kode dosen harus menggunakan format DSN diikuti minimal tiga angka, contoh DSN006.');
                }

                if ($role === 'mahasiswa') {
                    $prefix = self::STUDENT_PREFIXES[$programStudi] ?? null;
                    if ($prefix === null || !preg_match('/^'.preg_quote($prefix, '/').'\d{3,}$/', $identifier)) {
                        $example = $programStudi === 'Sistem Informasi' ? '3224010' : '1224010';
                        $fail("NIM harus sesuai program studi, contoh {$example}.");
                    }
                }
            };
        }

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'ends_with:@gmail.com', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'identifier' => $identifierRules,
            'role' => ['required', Rule::in(self::MANAGED_ROLES)],
            'program_studi' => ['required', Rule::in(self::PROGRAM_STUDI)],
            'password' => [$user ? 'nullable' : 'required', 'string', Password::min(8)->letters()->numbers()],
        ];
    }

    private function userMessages(): array
    {
        return [
            'email.ends_with' => 'Email harus menggunakan alamat @gmail.com.',
            'identifier.unique' => 'NIM atau kode dosen sudah digunakan.',
            'program_studi.required' => 'Program studi wajib dipilih.',
            'program_studi.in' => 'Program studi harus Informatika atau Sistem Informasi.',
            'password.letters' => 'Kata sandi harus memiliki setidaknya satu huruf.',
            'password.numbers' => 'Kata sandi harus memiliki setidaknya satu angka.',
        ];
    }

    private function normalizeIdentityInput(Request $request): void
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'identifier' => mb_strtoupper(trim((string) $request->input('identifier'))),
        ]);
    }

    /** @param array<int, string> $reservedIdentifiers */
    private function nextIdentifier(string $role, ?string $programStudi, array $reservedIdentifiers = []): string
    {
        $prefix = $role === 'dosen'
            ? 'DSN'
            : (self::STUDENT_PREFIXES[$programStudi] ?? throw new \InvalidArgumentException('Program studi tidak valid.'));

        $lastNumber = User::query()
            ->where('identifier', 'like', $prefix.'%')
            ->pluck('identifier')
            ->reduce(function (int $highest, string $identifier) use ($prefix) {
                if (!preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', mb_strtoupper($identifier), $matches)) {
                    return $highest;
                }

                return max($highest, (int) $matches[1]);
            }, 0);

        foreach ($reservedIdentifiers as $identifier) {
            if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', mb_strtoupper($identifier), $matches)) {
                $lastNumber = max($lastNumber, (int) $matches[1]);
            }
        }

        return $prefix.str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
    }

    private function hasAcademicRelations(User $user): bool
    {
        return DB::table('dosen_mahasiswa')
            ->where('dosen_id', $user->id)
            ->orWhere('mahasiswa_id', $user->id)
            ->exists()
            || DB::table('perwalians')
                ->where('dosen_id', $user->id)
                ->orWhere('mahasiswa_id', $user->id)
                ->exists();
    }

    private function ensureManagedUser(User $user): void
    {
        abort_unless(in_array($user->role, self::MANAGED_ROLES, true), 404, 'Pengguna tidak ditemukan.');
    }

    /** @param array<int, int> $userIds */
    private function referencedUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $assignmentIds = DB::table('dosen_mahasiswa')
            ->whereIn('dosen_id', $userIds)
            ->orWhereIn('mahasiswa_id', $userIds)
            ->get(['dosen_id', 'mahasiswa_id'])
            ->flatMap(fn ($row) => [$row->dosen_id, $row->mahasiswa_id]);

        $recordIds = DB::table('perwalians')
            ->whereIn('dosen_id', $userIds)
            ->orWhereIn('mahasiswa_id', $userIds)
            ->get(['dosen_id', 'mahasiswa_id'])
            ->flatMap(fn ($row) => [$row->dosen_id, $row->mahasiswa_id]);

        return $assignmentIds->merge($recordIds)->unique()->values()->all();
    }

    private function userPayload(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'identifier', 'role', 'program_studi', 'is_active', 'created_at']);
    }
}
