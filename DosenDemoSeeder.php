<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DosenDemoSeeder extends Seeder
{
    public function run(): void
    {
        $assignments = DB::table('dosen_mahasiswa')
            ->join('users as students', 'students.id', '=', 'dosen_mahasiswa.mahasiswa_id')
            ->where('students.role', 'mahasiswa')
            ->orderBy('students.identifier')
            ->get([
                'dosen_mahasiswa.dosen_id',
                'dosen_mahasiswa.mahasiswa_id',
                'students.name',
                'students.program_studi',
            ]);

        $assignments->each(function ($assignment, int $index) {
            DB::table('perwalians')->updateOrInsert(
                [
                    'mahasiswa_id' => $assignment->mahasiswa_id,
                    'topik' => 'Verifikasi KRS semester ganjil 2026/2027',
                ],
                [
                    'dosen_id' => $assignment->dosen_id,
                    'tanggal' => '2026-08-05',
                    'hasil_konsultasi' => 'KRS '.$assignment->name.' telah diperiksa bersama dosen wali dan sesuai dengan beban studi yang disarankan.',
                    'rencana_tindak_lanjut' => 'Mahasiswa mengikuti perkuliahan sesuai KRS yang telah disetujui.',
                    'catatan_dosen' => 'KRS telah sesuai dengan prasyarat mata kuliah dan kemampuan studi mahasiswa.',
                    'status' => 'selesai',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $status = ['diajukan', 'ditinjau', 'selesai'][$index % 3];
            $followUp = match ($status) {
                'diajukan' => 'Menunggu pemeriksaan dan arahan dari dosen wali.',
                'ditinjau' => 'Mahasiswa diminta memperbarui target belajar untuk pertemuan berikutnya.',
                default => 'Target akademik telah disepakati bersama dosen wali.',
            };
            $advisorNote = match ($status) {
                'diajukan' => null,
                'ditinjau' => 'Target belajar sudah diperiksa. Mahasiswa perlu membuat jadwal belajar mingguan yang lebih terukur.',
                default => 'Target akademik disetujui dan akan dievaluasi kembali pada pertemuan berikutnya.',
            };

            DB::table('perwalians')->updateOrInsert(
                [
                    'mahasiswa_id' => $assignment->mahasiswa_id,
                    'topik' => 'Pemantauan target akademik semester',
                ],
                [
                    'dosen_id' => $assignment->dosen_id,
                    'tanggal' => '2026-08-29',
                    'hasil_konsultasi' => 'Membahas target akademik, strategi belajar, dan kesiapan mengikuti kegiatan semester pada program studi '.$assignment->program_studi.'.',
                    'rencana_tindak_lanjut' => $followUp,
                    'catatan_dosen' => $advisorNote,
                    'status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        });
    }
}
