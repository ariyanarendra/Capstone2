<?php

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$suffix = substr(bin2hex(random_bytes(6)), 0, 10);
$numericSuffix = random_int(700, 900);
$createdIdentifier = '1224'.str_pad((string) $numericSuffix, 3, '0', STR_PAD_LEFT);
$token = 'smoke-'.$suffix;
$temporaryCsv = null;

$dispatch = function (string $method, string $uri, array $data = [], array $files = []) use ($kernel, $token) {
    $server = [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    ];

    if ($files === []) {
        $server['CONTENT_TYPE'] = 'application/json';
        $request = Request::create($uri, $method, [], [], [], $server, json_encode($data));
    } else {
        $request = Request::create($uri, $method, $data, [], $files, $server);
    }

    return $kernel->handle($request);
};

$assertStatus = function ($response, int $expected, string $step): array {
    $payload = json_decode($response->getContent(), true) ?: [];

    if ($response->getStatusCode() !== $expected) {
        throw new RuntimeException("{$step} gagal ({$response->getStatusCode()}): ".json_encode($payload));
    }

    return $payload;
};

DB::beginTransaction();

try {
    $admin = User::create([
        'name' => 'Admin Smoke Test',
        'email' => "admin.smoke.{$suffix}@gmail.com",
        'identifier' => 'ADM-SMOKE-'.$suffix,
        'role' => 'admin',
        'is_active' => true,
        'api_token' => hash('sha256', $token),
        'password' => 'SmokePassword123!',
    ]);

    $studentSuggestion = $assertStatus($dispatch('GET', '/api/users/identifier-suggestion?role=mahasiswa&program_studi=Informatika'), 200, 'Rekomendasi NIM mahasiswa');
    if (!preg_match('/^1224\d{3,}$/', $studentSuggestion['identifier'] ?? '')) {
        throw new RuntimeException('Rekomendasi NIM Informatika tidak sesuai format.');
    }

    $lecturerSuggestion = $assertStatus($dispatch('GET', '/api/users/identifier-suggestion?role=dosen&program_studi=Informatika'), 200, 'Rekomendasi kode dosen');
    if (!preg_match('/^DSN\d{3,}$/', $lecturerSuggestion['identifier'] ?? '')) {
        throw new RuntimeException('Rekomendasi kode dosen tidak sesuai format.');
    }

    $orderedLecturers = $assertStatus($dispatch('GET', '/api/users?role=dosen'), 200, 'Urutan kode dosen');
    $lecturerIdentifiers = array_column($orderedLecturers['data'] ?? [], 'identifier');
    $expectedLecturerOrder = $lecturerIdentifiers;
    sort($expectedLecturerOrder, SORT_STRING);
    if ($lecturerIdentifiers !== $expectedLecturerOrder) {
        throw new RuntimeException('Daftar dosen belum diurutkan berdasarkan kode dosen.');
    }

    $created = $assertStatus($dispatch('POST', '/api/users', [
        'name' => 'Pengguna Smoke Test',
        'email' => "pengguna.smoke.{$suffix}@gmail.com",
        'identifier' => $createdIdentifier,
        'role' => 'mahasiswa',
        'program_studi' => 'Informatika',
        'password' => 'SmokePassword123!',
    ]), 201, 'Tambah pengguna');
    $userId = $created['user']['id'];

    $assertStatus($dispatch('POST', '/api/users', [
        'name' => 'Pengguna Duplikat Smoke',
        'email' => "duplikat.smoke.{$suffix}@gmail.com",
        'identifier' => $createdIdentifier,
        'role' => 'mahasiswa',
        'program_studi' => 'Informatika',
        'password' => 'SmokePassword123!',
    ]), 422, 'Tolak NIM duplikat');

    $filteredUsers = $assertStatus($dispatch('GET', '/api/users?status=active&role=mahasiswa&program_studi=Informatika&search=Pengguna%20Smoke'), 200, 'Filter mahasiswa berdasarkan program studi');
    if (($filteredUsers['total'] ?? 0) !== 1 || ($filteredUsers['data'][0]['program_studi'] ?? null) !== 'Informatika') {
        throw new RuntimeException('Filter program studi tidak mengembalikan mahasiswa yang sesuai.');
    }
    $assertStatus($dispatch('PUT', "/api/users/{$userId}", [
        'name' => 'Pengguna Smoke Diperbarui',
        'email' => "pengguna.smoke.{$suffix}@gmail.com",
        'identifier' => $createdIdentifier,
        'role' => 'mahasiswa',
        'program_studi' => 'Sistem Informasi',
    ]), 200, 'Ubah pengguna');
    $assertStatus($dispatch('PATCH', "/api/users/{$userId}/password", [
        'password' => '11111111',
        'password_confirmation' => '11111111',
    ]), 422, 'Tolak password sementara yang hanya angka');
    $assertStatus($dispatch('PATCH', "/api/users/{$userId}/password", [
        'password' => 'PasswordBaru123!',
        'password_confirmation' => 'PasswordBaru123!',
    ]), 200, 'Reset password');
    $assertStatus($dispatch('PATCH', "/api/users/{$userId}/status", ['is_active' => false]), 200, 'Nonaktifkan pengguna');

    $inactiveLogin = Request::create('/api/login', 'POST', [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ], json_encode([
        'login' => "pengguna.smoke.{$suffix}@gmail.com",
        'password' => 'PasswordBaru123!',
    ]));
    $assertStatus($kernel->handle($inactiveLogin), 403, 'Tolak login akun nonaktif');

    $assertStatus($dispatch('PATCH', "/api/users/{$userId}/status", ['is_active' => true]), 200, 'Aktifkan pengguna');
    $assertStatus($dispatch('DELETE', "/api/users/{$userId}"), 200, 'Hapus akun tanpa relasi');

    $temporaryCsv = tempnam(sys_get_temp_dir(), 'gatekampus-users-');
    file_put_contents($temporaryCsv, implode("\r\n", [
        'name,email,identifier,role,program_studi,password',
        "Nadira Smoke,nadira.smoke.{$suffix}@gmail.com,AUTO,mahasiswa,Informatika,PasswordCsv123!",
        ',,,,,',
    ]));
    $upload = new UploadedFile($temporaryCsv, 'users.csv', 'text/csv', null, true);
    $imported = $assertStatus($dispatch('POST', '/api/users/import', ['role_scope' => 'mahasiswa'], ['import_file' => $upload]), 201, 'Impor CSV');

    if (($imported['imported'] ?? 0) !== 1) {
        throw new RuntimeException('Jumlah hasil impor CSV tidak sesuai.');
    }

    $importedUser = User::where('email', "nadira.smoke.{$suffix}@gmail.com")->firstOrFail();

    if (!preg_match('/^1224\d{3,}$/', $importedUser->identifier)) {
        throw new RuntimeException('NIM otomatis hasil impor tidak sesuai program studi.');
    }
    DB::table('dosen_mahasiswa')->insert([
        'dosen_id' => $admin->id,
        'mahasiswa_id' => $importedUser->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $assertStatus($dispatch('DELETE', "/api/users/{$importedUser->id}"), 422, 'Lindungi akun yang memiliki relasi akademik');

    echo "User management smoke test: OK\n";
} finally {
    DB::rollBack();

    if ($temporaryCsv && file_exists($temporaryCsv)) {
        unlink($temporaryCsv);
    }
}
