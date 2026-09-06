<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->upsertDemoUser([
            'name' => 'Admin GateKampus',
            'email' => 'admin@gmail.com',
            'identifier' => 'ADM001',
            'role' => 'admin',
            'program_studi' => null,
        ]);

        $dosenData = [
            ['name' => 'Dr. Maya Kusuma, M.Kom.', 'email' => 'dosen@gmail.com', 'identifier' => 'DSN001', 'program_studi' => 'Informatika'],
            ['name' => 'Yudha Pradipta, M.Kom.', 'email' => 'yudha.pradipta@gmail.com', 'identifier' => 'DSN002', 'program_studi' => 'Sistem Informasi'],
            ['name' => 'Raras Maheswari, M.Kom.', 'email' => 'raras.maheswari@gmail.com', 'identifier' => 'DSN003', 'program_studi' => 'Informatika'],
            ['name' => 'Galang Arkana, M.Cs.', 'email' => 'galang.arkana@gmail.com', 'identifier' => 'DSN004', 'program_studi' => 'Sistem Informasi'],
            ['name' => 'Nabila Kinasih, M.Kom.', 'email' => 'nabila.kinasih@gmail.com', 'identifier' => 'DSN005', 'program_studi' => 'Informatika'],
        ];

        $mahasiswaData = [
            ['name' => 'Ariya Pratama', 'email' => 'mahasiswa@gmail.com', 'identifier' => '1224001', 'legacy_identifier' => 'MHS001', 'program_studi' => 'Informatika'],
            ['name' => 'Arunika Zafira Maheswari', 'email' => 'arunika.zafira@gmail.com', 'identifier' => '3224001', 'legacy_identifier' => 'MHS002', 'program_studi' => 'Sistem Informasi'],
            ['name' => 'Keano Ravindra Akbar', 'email' => 'keano.ravindra@gmail.com', 'identifier' => '1224002', 'legacy_identifier' => 'MHS003', 'program_studi' => 'Informatika'],
            ['name' => 'Niskala Bumi Adinata', 'email' => 'niskala.bumi@gmail.com', 'identifier' => '3224002', 'legacy_identifier' => 'MHS004', 'program_studi' => 'Sistem Informasi'],
            ['name' => 'Aleeya Narindri Prameswari', 'email' => 'aleeya.narindri@gmail.com', 'identifier' => '1224003', 'legacy_identifier' => 'MHS005', 'program_studi' => 'Informatika'],
            ['name' => 'Zayyan Elvano Satrio', 'email' => 'zayyan.elvano@gmail.com', 'identifier' => '3224003', 'legacy_identifier' => 'MHS006', 'program_studi' => 'Sistem Informasi'],
            ['name' => 'Senja Kirana Arundhati', 'email' => 'senja.kirana@gmail.com', 'identifier' => '1224004', 'legacy_identifier' => 'MHS007', 'program_studi' => 'Informatika'],
            ['name' => 'Mikael Elang Wiratama', 'email' => 'mikael.elang@gmail.com', 'identifier' => '3224004', 'legacy_identifier' => 'MHS008', 'program_studi' => 'Sistem Informasi'],
            ['name' => 'Shafira Aylin Nasywa', 'email' => 'shafira.aylin@gmail.com', 'identifier' => '1224005', 'legacy_identifier' => 'MHS009', 'program_studi' => 'Informatika'],
            ['name' => 'Rayyan Mahesa Dirgantara', 'email' => 'rayyan.mahesa@gmail.com', 'identifier' => '3224005', 'legacy_identifier' => 'MHS010', 'program_studi' => 'Sistem Informasi'],
            ['name' => 'Calya Kanaya Azzahra', 'email' => 'calya.kanaya@gmail.com', 'identifier' => '1224006', 'legacy_identifier' => 'MHS011', 'program_studi' => 'Informatika'],
            ['name' => 'Rinjani Kayana Putri', 'email' => 'rinjani.kayana@gmail.com', 'identifier' => '3224006', 'legacy_identifier' => 'MHS012', 'program_studi' => 'Sistem Informasi'],
            ['name' => 'Alvaro Jagat Prakasa', 'email' => 'alvaro.jagat@gmail.com', 'identifier' => '1224007', 'legacy_identifier' => 'MHS013', 'program_studi' => 'Informatika'],
            ['name' => 'Nareswari Laras Anindita', 'email' => 'nareswari.laras@gmail.com', 'identifier' => '3224007', 'legacy_identifier' => 'MHS014', 'program_studi' => 'Sistem Informasi'],
            ['name' => 'Arshaka Banyu Pamungkas', 'email' => 'arshaka.banyu@gmail.com', 'identifier' => '1224008', 'legacy_identifier' => 'MHS015', 'program_studi' => 'Informatika'],
            ['name' => 'Elora Saskia Rahmadani', 'email' => 'elora.saskia@gmail.com', 'identifier' => '3224008', 'legacy_identifier' => 'MHS016', 'program_studi' => 'Sistem Informasi'],
            ['name' => 'Kenzie Rakha Adhyaksa', 'email' => 'kenzie.rakha@gmail.com', 'identifier' => '1224009', 'legacy_identifier' => 'MHS017', 'program_studi' => 'Informatika'],
            ['name' => 'Nayara Jelita Andriani', 'email' => 'nayara.jelita@gmail.com', 'identifier' => '3224009', 'legacy_identifier' => 'MHS018', 'program_studi' => 'Sistem Informasi'],
        ];

        $dosen = collect($dosenData)->map(function (array $data) {
            return $this->upsertDemoUser([...$data, 'role' => 'dosen']);
        })->values();

        $mahasiswa = collect($mahasiswaData)->map(function (array $data) {
            return $this->upsertDemoUser([...$data, 'role' => 'mahasiswa']);
        })->values();

        $dosen->firstWhere('identifier', 'DSN001')?->update(['is_active' => true]);
        $mahasiswa->firstWhere('identifier', '1224001')?->update(['is_active' => true]);

        $topics = [
            'Konsultasi rencana studi semester',
            'Evaluasi perkembangan akademik',
            'Diskusi peminatan dan karier',
            'Persiapan program magang',
            'Tinjauan capaian SKS',
            'Rencana topik tugas akhir',
        ];

        $advisorCounters = [];
        $mahasiswa->each(function (User $student, int $index) use ($dosen, $topics, &$advisorCounters) {
            $programAdvisors = $dosen->where('program_studi', $student->program_studi)->values();
            $advisorIndex = $advisorCounters[$student->program_studi] ?? 0;
            $advisor = $programAdvisors[$advisorIndex % $programAdvisors->count()];
            $advisorCounters[$student->program_studi] = $advisorIndex + 1;

            DB::table('dosen_mahasiswa')->updateOrInsert(
                ['mahasiswa_id' => $student->id],
                ['dosen_id' => $advisor->id, 'created_at' => now(), 'updated_at' => now()]
            );

            $topic = $topics[$index % count($topics)];
            $status = $index % 3 === 2 ? 'ditinjau' : 'selesai';

            DB::table('perwalians')->updateOrInsert(
                ['mahasiswa_id' => $student->id, 'topik' => $topic],
                [
                    'dosen_id' => $advisor->id,
                    'tanggal' => now()->subDays($index)->toDateString(),
                    'hasil_konsultasi' => 'Mahasiswa dan dosen wali telah membahas '.$topic.'.',
                    'rencana_tindak_lanjut' => $status === 'selesai' ? 'Hasil konsultasi telah disepakati.' : 'Menunggu tinjauan lanjutan dari dosen wali.',
                    'status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        });

        $this->call(DosenDemoSeeder::class);
    }

    /** @param array<string, mixed> $data */
    private function upsertDemoUser(array $data): User
    {
        $legacyIdentifier = $data['legacy_identifier'] ?? $data['identifier'];
        unset($data['legacy_identifier']);

        $user = User::query()
            ->where('email', $data['email'])
            ->orWhere('identifier', $data['identifier'])
            ->orWhere('identifier', $legacyIdentifier)
            ->first();

        $identifierOwner = User::query()
            ->where('identifier', $data['identifier'])
            ->when($user, fn ($query) => $query->whereKeyNot($user->id))
            ->first();

        if ($identifierOwner) {
            throw new \RuntimeException("NIM/NIP {$data['identifier']} sudah digunakan oleh {$identifierOwner->name}.");
        }

        $user ??= new User();
        $user->fill([
            ...$data,
            'is_active' => true,
            'must_change_password' => false,
            'password' => 'password',
        ])->save();

        return $user;
    }
}
