<?php

use App\Models\Perwalian;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$suffix = substr(bin2hex(random_bytes(6)), 0, 10);
$identifierBase = random_int(900000, 999990);

$dispatch = function (string $method, string $uri, array $data = [], ?string $token = null) use ($kernel) {
    $server = [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ];

    if ($token) {
        $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
    }

    $request = Request::create($uri, $method, [], [], [], $server, json_encode($data));

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
    $adminToken = 'admin-flow-'.$suffix;
    $dosenToken = 'dosen-flow-'.$suffix;
    $otherDosenToken = 'other-dosen-flow-'.$suffix;
    $studentToken = 'student-flow-'.$suffix;

    $admin = User::create([
        'name' => 'Admin Flow Test',
        'email' => "admin.flow.{$suffix}@gmail.com",
        'identifier' => 'ADM-FLOW-'.$suffix,
        'role' => 'admin',
        'is_active' => true,
        'api_token' => hash('sha256', $adminToken),
        'password' => 'PasswordAwal123!',
    ]);
    $dosen = User::create([
        'name' => 'Dosen Flow Test',
        'email' => "dosen.flow.{$suffix}@gmail.com",
        'identifier' => 'DSN'.($identifierBase + 1),
        'role' => 'dosen',
        'program_studi' => 'Informatika',
        'is_active' => true,
        'api_token' => hash('sha256', $dosenToken),
        'password' => 'PasswordAwal123!',
    ]);
    $otherDosen = User::create([
        'name' => 'Dosen Lain Flow Test',
        'email' => "dosen.lain.flow.{$suffix}@gmail.com",
        'identifier' => 'DSN'.($identifierBase + 2),
        'role' => 'dosen',
        'program_studi' => 'Informatika',
        'is_active' => true,
        'api_token' => hash('sha256', $otherDosenToken),
        'password' => 'PasswordAwal123!',
    ]);
    $crossProgramDosen = User::create([
        'name' => 'Dosen Lintas Prodi Flow Test',
        'email' => "dosen.lintas.flow.{$suffix}@gmail.com",
        'identifier' => 'DSN'.($identifierBase + 3),
        'role' => 'dosen',
        'program_studi' => 'Sistem Informasi',
        'is_active' => true,
        'password' => 'PasswordAwal123!',
    ]);
    $inactiveDosen = User::create([
        'name' => 'Dosen Nonaktif Flow Test',
        'email' => "dosen.nonaktif.flow.{$suffix}@gmail.com",
        'identifier' => 'DSN'.($identifierBase + 4),
        'role' => 'dosen',
        'program_studi' => 'Informatika',
        'is_active' => false,
        'password' => 'PasswordAwal123!',
    ]);
    $student = User::create([
        'name' => 'Mahasiswa Flow Test',
        'email' => "mahasiswa.flow.{$suffix}@gmail.com",
        'identifier' => '1224'.$identifierBase,
        'role' => 'mahasiswa',
        'program_studi' => 'Informatika',
        'is_active' => true,
        'api_token' => hash('sha256', $studentToken),
        'password' => 'PasswordAwal123!',
    ]);

    DB::table('dosen_mahasiswa')->insert([
        'dosen_id' => $dosen->id,
        'mahasiswa_id' => $student->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $perwalian = Perwalian::create([
        'dosen_id' => $dosen->id,
        'mahasiswa_id' => $student->id,
        'tanggal' => '2026-08-29',
        'topik' => 'Flow test',
        'hasil_konsultasi' => 'Catatan pengujian alur Dosen.',
        'status' => 'diajukan',
    ]);

    $users = $assertStatus($dispatch('GET', '/api/users?search=Flow%20Test', [], $adminToken), 200, 'Daftar pengguna tanpa admin');
    foreach ($users['data'] as $listedUser) {
        if ($listedUser['role'] === 'admin') {
            throw new RuntimeException('Akun admin masih muncul pada Data Pengguna.');
        }
    }

    $assignmentList = $assertStatus($dispatch('GET', '/api/advisor-assignments', [], $adminToken), 200, 'Daftar penugasan terurut');
    $studentIdentifiers = array_column($assignmentList['data'] ?? [], 'identifier');
    $expectedStudentOrder = $studentIdentifiers;
    sort($expectedStudentOrder, SORT_STRING);
    if ($studentIdentifiers !== $expectedStudentOrder) {
        throw new RuntimeException('Daftar penugasan belum diurutkan berdasarkan NIM.');
    }
    $advisorIdentifiers = array_column($assignmentList['advisors'] ?? [], 'identifier');
    $expectedAdvisorOrder = $advisorIdentifiers;
    sort($expectedAdvisorOrder, SORT_STRING);
    if ($advisorIdentifiers !== $expectedAdvisorOrder) {
        throw new RuntimeException('Pilihan dosen wali belum diurutkan berdasarkan kode dosen.');
    }
    if (in_array($inactiveDosen->id, array_column($assignmentList['advisors'] ?? [], 'id'), true)) {
        throw new RuntimeException('Dosen nonaktif masih muncul pada pilihan dosen wali.');
    }

    $assertStatus($dispatch('PUT', "/api/advisor-assignments/{$student->id}", [
        'dosen_id' => $crossProgramDosen->id,
    ], $adminToken), 422, 'Tolak dosen wali dari program studi berbeda');
    $assertStatus($dispatch('PUT', "/api/advisor-assignments/{$student->id}", [
        'dosen_id' => $inactiveDosen->id,
    ], $adminToken), 422, 'Tolak dosen wali nonaktif');
    $assertStatus($dispatch('PUT', "/api/advisor-assignments/{$student->id}", [
        'dosen_id' => $otherDosen->id,
    ], $adminToken), 200, 'Ganti dosen wali satu program studi');
    $assertStatus($dispatch('PUT', "/api/advisor-assignments/{$student->id}", [
        'dosen_id' => $dosen->id,
    ], $adminToken), 200, 'Kembalikan dosen wali pengujian');

    $assertStatus($dispatch('GET', '/api/profile', [], $adminToken), 200, 'Buka profil admin');
    $updatedProfile = $assertStatus($dispatch('PUT', '/api/profile', [
        'name' => 'Admin Flow Diperbarui',
        'email' => $admin->email,
    ], $adminToken), 200, 'Ubah profil admin');
    if (($updatedProfile['user']['name'] ?? '') !== 'Admin Flow Diperbarui') {
        throw new RuntimeException('Nama profil tidak diperbarui.');
    }

    $assertStatus($dispatch('PATCH', '/api/profile/password', [
        'current_password' => 'PasswordAwal123!',
        'password' => 'PasswordProfil123!',
        'password_confirmation' => 'PasswordProfil123!',
    ], $adminToken), 200, 'Ubah password profil');

    $assertStatus($dispatch('PATCH', "/api/users/{$dosen->id}/password", [
        'password' => 'PasswordSementara123!',
        'password_confirmation' => 'PasswordSementara123!',
    ], $adminToken), 200, 'Admin membuat password sementara');
    if (!$dosen->fresh()->must_change_password || !Hash::check('PasswordSementara123!', $dosen->fresh()->password)) {
        throw new RuntimeException('Password sementara atau status wajib ganti password tidak tersimpan.');
    }

    $temporaryLogin = $assertStatus($dispatch('POST', '/api/login', [
        'login' => $dosen->identifier,
        'password' => 'PasswordSementara123!',
    ]), 200, 'Login dengan password sementara');
    if (empty($temporaryLogin['user']['must_change_password'])) {
        throw new RuntimeException('Login tidak mengarahkan pengguna untuk mengganti password sementara.');
    }

    $temporaryToken = $temporaryLogin['token'];
    $assertStatus($dispatch('GET', '/api/dosen/dashboard', [], $temporaryToken), 423, 'Blokir layanan sebelum password diganti');
    $assertStatus($dispatch('PATCH', '/api/profile/password', [
        'current_password' => 'PasswordSementara123!',
        'password' => 'PasswordSementara123!',
        'password_confirmation' => 'PasswordSementara123!',
    ], $temporaryToken), 422, 'Tolak penggunaan ulang password sementara');
    $assertStatus($dispatch('PATCH', '/api/profile/password', [
        'current_password' => 'PasswordSementara123!',
        'password' => '11111111',
        'password_confirmation' => '11111111',
    ], $temporaryToken), 422, 'Tolak password baru yang hanya angka');
    $changedPassword = $assertStatus($dispatch('PATCH', '/api/profile/password', [
        'current_password' => 'PasswordSementara123!',
        'password' => 'PasswordReset123!',
        'password_confirmation' => 'PasswordReset123!',
    ], $temporaryToken), 200, 'Pengguna mengganti password sementara');
    if (($changedPassword['user']['must_change_password'] ?? true) || !Hash::check('PasswordReset123!', $dosen->fresh()->password)) {
        throw new RuntimeException('Status wajib ganti password tidak dibersihkan setelah password diperbarui.');
    }

    $dosen = $dosen->fresh();
    $dosen->forceFill(['api_token' => hash('sha256', $dosenToken)])->save();
    $assertStatus($dispatch('PUT', '/api/profile', [
        'name' => 'Dosen Tidak Boleh Mengubah Profil',
        'email' => $dosen->email,
    ], $dosenToken), 403, 'Kunci perubahan identitas Dosen');
    $reviewQueueItem = Perwalian::create([
        'dosen_id' => $dosen->id,
        'mahasiswa_id' => $student->id,
        'tanggal' => '2026-08-30',
        'topik' => 'Flow test sedang ditinjau',
        'hasil_konsultasi' => 'Catatan pembanding untuk menguji urutan antrean Dosen.',
        'status' => 'ditinjau',
    ]);
    $dashboard = $assertStatus($dispatch('GET', '/api/dosen/dashboard', [], $dosenToken), 200, 'Dashboard Dosen');
    if (($dashboard['summary']['total_students'] ?? 0) !== 1
        || ($dashboard['summary']['diajukan'] ?? 0) !== 1
        || ($dashboard['summary']['ditinjau'] ?? 0) !== 1) {
        throw new RuntimeException('Ringkasan dashboard Dosen tidak sesuai data pengujian.');
    }
    if (($dashboard['recent_perwalians'][0]['id'] ?? null) !== $perwalian->id) {
        throw new RuntimeException('Antrean dashboard Dosen belum memprioritaskan status Diajukan sebelum Ditinjau.');
    }
    $reviewQueueItem->delete();
    $studentWithoutQueue = User::create([
        'name' => 'Mahasiswa Tanpa Antrean Flow Test',
        'email' => "mahasiswa.tanpa.antrean.{$suffix}@gmail.com",
        'identifier' => '1223'.$identifierBase,
        'role' => 'mahasiswa',
        'program_studi' => 'Informatika',
        'is_active' => true,
        'api_token' => hash('sha256', 'other-student-flow-'.$suffix),
        'password' => 'PasswordAwal123!',
    ]);
    DB::table('dosen_mahasiswa')->insert([
        'dosen_id' => $dosen->id,
        'mahasiswa_id' => $studentWithoutQueue->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $advisorStudents = $assertStatus($dispatch('GET', '/api/dosen/mahasiswa-wali', [], $dosenToken), 200, 'Daftar mahasiswa wali Dosen');
    if (($advisorStudents['data'][0]['id'] ?? null) !== $student->id
        || (int) ($advisorStudents['data'][0]['active_perwalian_count'] ?? 0) !== 1
        || (int) ($advisorStudents['summary']['active_records'] ?? 0) !== 1) {
        throw new RuntimeException('Daftar mahasiswa wali belum memprioritaskan antrean aktif atau hitungannya keliru.');
    }
    $assertStatus($dispatch('GET', "/api/dosen/mahasiswa-wali/{$student->id}", [], $dosenToken), 200, 'Riwayat mahasiswa wali');
    $assertStatus($dispatch('GET', "/api/dosen/mahasiswa-wali/{$student->id}", [], $otherDosenToken), 404, 'Tolak mahasiswa milik Dosen lain');
    $reviewed = $assertStatus($dispatch('PATCH', "/api/dosen/perwalians/{$perwalian->id}/status", [
        'status' => 'ditinjau',
        'catatan_dosen' => 'Mahasiswa perlu menyusun target belajar mingguan.',
    ], $dosenToken), 200, 'Review perwalian');
    if (($reviewed['perwalian']['catatan_dosen'] ?? '') !== 'Mahasiswa perlu menyusun target belajar mingguan.') {
        throw new RuntimeException('Catatan tinjauan Dosen tidak tersimpan.');
    }
    $assertStatus($dispatch('PATCH', "/api/dosen/perwalians/{$perwalian->id}/status", [
        'status' => 'selesai',
        'catatan_dosen' => 'Target belajar telah disepakati dan siap dijalankan.',
    ], $dosenToken), 200, 'Selesaikan perwalian');
    $assertStatus($dispatch('PATCH', "/api/dosen/perwalians/{$perwalian->id}/status", [
        'status' => 'ditinjau',
        'catatan_dosen' => 'Percobaan akses oleh Dosen lain.',
    ], $otherDosenToken), 404, 'Tolak review Dosen lain');

    $dashboardAfterCompletion = $assertStatus($dispatch('GET', '/api/dosen/dashboard', [], $dosenToken), 200, 'Dashboard Dosen setelah perwalian selesai');
    if (count($dashboardAfterCompletion['recent_perwalians'] ?? []) !== 0) {
        throw new RuntimeException('Perwalian selesai masih muncul pada antrean aktif dashboard Dosen.');
    }
    $activeQueue = $assertStatus($dispatch('GET', '/api/perwalians?scope=active', [], $dosenToken), 200, 'Antrean aktif Dosen');
    if (($activeQueue['total'] ?? -1) !== 0) {
        throw new RuntimeException('Perwalian selesai masih muncul pada antrean aktif Dosen.');
    }
    $archive = $assertStatus($dispatch('GET', '/api/perwalians?scope=archive', [], $dosenToken), 200, 'Arsip selesai Dosen');
    if (($archive['total'] ?? 0) !== 1 || ($archive['data'][0]['id'] ?? null) !== $perwalian->id) {
        throw new RuntimeException('Perwalian selesai belum dipindahkan ke arsip Dosen yang tepat.');
    }
    $studentActiveQueue = $assertStatus($dispatch('GET', "/api/dosen/mahasiswa-wali/{$student->id}?scope=active", [], $dosenToken), 200, 'Antrean aktif per mahasiswa');
    if (($studentActiveQueue['total'] ?? -1) !== 0) {
        throw new RuntimeException('Catatan selesai masih muncul pada antrean aktif per mahasiswa.');
    }
    $studentArchive = $assertStatus($dispatch('GET', "/api/dosen/mahasiswa-wali/{$student->id}?scope=archive", [], $dosenToken), 200, 'Arsip per mahasiswa');
    if (($studentArchive['total'] ?? 0) !== 1 || ($studentArchive['data'][0]['id'] ?? null) !== $perwalian->id) {
        throw new RuntimeException('Arsip per mahasiswa tidak berisi catatan selesai yang tepat.');
    }

    $studentDashboard = $assertStatus($dispatch('GET', '/api/mahasiswa/dashboard', [], $studentToken), 200, 'Dashboard Mahasiswa');
    if (($studentDashboard['advisor']['id'] ?? null) !== $dosen->id || ($studentDashboard['summary']['total'] ?? 0) !== 1) {
        throw new RuntimeException('Dashboard Mahasiswa tidak menampilkan Dosen Wali atau ringkasan yang benar.');
    }

    $createdRecord = $assertStatus($dispatch('POST', '/api/perwalians', [
        'tanggal' => now()->toDateString(),
        'topik' => 'Evaluasi target belajar',
        'hasil_konsultasi' => 'Mahasiswa dan Dosen membahas target belajar untuk satu semester.',
        'rencana_tindak_lanjut' => 'Mahasiswa menyusun jadwal belajar mingguan.',
    ], $studentToken), 201, 'Mahasiswa mencatat perwalian');
    if (($createdRecord['perwalian']['status'] ?? '') !== 'diajukan'
        || ($createdRecord['perwalian']['dosen_id'] ?? null) !== $dosen->id) {
        throw new RuntimeException('Catatan Mahasiswa tidak diarahkan kepada Dosen Wali atau status awal salah.');
    }

    $createdRecordId = $createdRecord['perwalian']['id'];
    $updatedRecord = $assertStatus($dispatch('PUT', "/api/perwalians/{$createdRecordId}", [
        'tanggal' => now()->toDateString(),
        'topik' => 'Evaluasi target belajar diperbarui',
        'hasil_konsultasi' => 'Mahasiswa memperbaiki isi konsultasi sebelum ditinjau oleh Dosen Wali.',
        'rencana_tindak_lanjut' => 'Mahasiswa menyusun jadwal belajar yang telah diperbarui.',
    ], $studentToken), 200, 'Mahasiswa mengubah catatan berstatus Diajukan');
    if (($updatedRecord['perwalian']['topik'] ?? '') !== 'Evaluasi target belajar diperbarui') {
        throw new RuntimeException('Perubahan catatan Mahasiswa tidak tersimpan.');
    }
    $assertStatus($dispatch('PUT', "/api/perwalians/{$createdRecordId}", [
        'tanggal' => now()->toDateString(),
        'topik' => 'Percobaan akses mahasiswa lain',
        'hasil_konsultasi' => 'Mahasiswa lain tidak boleh mengubah catatan milik pengguna lain.',
        'rencana_tindak_lanjut' => null,
    ], 'other-student-flow-'.$suffix), 404, 'Tolak perubahan oleh Mahasiswa lain');

    $cancelCandidate = $assertStatus($dispatch('POST', '/api/perwalians', [
        'tanggal' => now()->toDateString(),
        'topik' => 'Catatan yang salah kirim',
        'hasil_konsultasi' => 'Catatan ini dibuat untuk menguji pembatalan pengajuan mahasiswa.',
        'rencana_tindak_lanjut' => null,
    ], $studentToken), 201, 'Buat catatan untuk dibatalkan');
    $cancelCandidateId = $cancelCandidate['perwalian']['id'];
    $cancelledRecord = $assertStatus($dispatch('PATCH', "/api/perwalians/{$cancelCandidateId}/cancel", [
        'cancellation_reason' => 'Salah menulis isi konsultasi.',
    ], $studentToken), 200, 'Mahasiswa membatalkan catatan Diajukan');
    if (($cancelledRecord['perwalian']['status'] ?? '') !== 'dibatalkan'
        || empty($cancelledRecord['perwalian']['cancelled_at'])) {
        throw new RuntimeException('Pembatalan tidak tersimpan sebagai riwayat yang dapat diaudit.');
    }
    $assertStatus($dispatch('PATCH', "/api/perwalians/{$cancelCandidateId}/cancel", [], $studentToken), 422, 'Tolak pembatalan berulang');

    $cancelledHistory = $assertStatus($dispatch('GET', '/api/perwalians?status=dibatalkan', [], $studentToken), 200, 'Filter riwayat Dibatalkan');
    if (($cancelledHistory['total'] ?? 0) !== 1 || ($cancelledHistory['data'][0]['id'] ?? null) !== $cancelCandidateId) {
        throw new RuntimeException('Catatan Dibatalkan tidak tersedia pada histori Mahasiswa.');
    }
    $dosenQueueAfterCancellation = $assertStatus($dispatch('GET', '/api/perwalians?scope=active', [], $dosenToken), 200, 'Antrean Dosen setelah pembatalan');
    if (in_array($cancelCandidateId, array_column($dosenQueueAfterCancellation['data'] ?? [], 'id'), true)) {
        throw new RuntimeException('Catatan Dibatalkan masih muncul pada antrean aktif Dosen.');
    }

    $assertStatus($dispatch('PATCH', "/api/dosen/perwalians/{$createdRecordId}/status", [
        'status' => 'ditinjau',
        'catatan_dosen' => 'Catatan koreksi mahasiswa mulai ditinjau.',
    ], $dosenToken), 200, 'Dosen mulai meninjau catatan yang telah dikoreksi');
    $lockedPayload = [
        'tanggal' => now()->toDateString(),
        'topik' => 'Perubahan setelah ditinjau',
        'hasil_konsultasi' => 'Perubahan ini harus ditolak karena Dosen sudah mulai meninjau catatan.',
        'rencana_tindak_lanjut' => null,
    ];
    $assertStatus($dispatch('PUT', "/api/perwalians/{$createdRecordId}", $lockedPayload, $studentToken), 422, 'Kunci perubahan setelah Ditinjau');
    $assertStatus($dispatch('PATCH', "/api/perwalians/{$createdRecordId}/cancel", [], $studentToken), 422, 'Kunci pembatalan setelah Ditinjau');

    $studentHistory = $assertStatus($dispatch('GET', '/api/perwalians', [], $studentToken), 200, 'Histori perwalian Mahasiswa');
    if (($studentHistory['summary']['total'] ?? 0) !== 3
        || ($studentHistory['summary']['dibatalkan'] ?? 0) !== 1
        || ($studentHistory['summary']['ditinjau'] ?? 0) !== 1) {
        throw new RuntimeException('Histori Mahasiswa tidak dibatasi atau tidak terhitung dengan benar.');
    }

    $adminCancelledHistory = $assertStatus($dispatch('GET', '/api/perwalians?status=dibatalkan', [], $adminToken), 200, 'Audit pembatalan oleh Admin');
    if (($adminCancelledHistory['total'] ?? 0) !== 1) {
        throw new RuntimeException('Admin tidak dapat melihat jejak catatan yang dibatalkan.');
    }
    $report = $assertStatus($dispatch('GET', '/api/reports/perwalian', [], $adminToken), 200, 'Rekap tidak menghitung pembatalan');
    $expectedReportTotal = DB::table('perwalians')->whereNull('cancelled_at')->count();
    if (($report['summary']['total'] ?? -1) !== $expectedReportTotal) {
        throw new RuntimeException('Catatan Dibatalkan masih masuk ke metrik rekap akademik.');
    }

    echo "Portal flow smoke test: OK\n";
} finally {
    DB::rollBack();
}
