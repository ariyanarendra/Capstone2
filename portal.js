import {
  BookOpenCheck,
  ClipboardCheck,
  History,
  LayoutDashboard,
  UserRound,
  UsersRound,
} from 'lucide-react'

export const roleLabels = {
  admin: 'Administrator',
  dosen: 'Dosen Wali',
  mahasiswa: 'Mahasiswa',
}

export const menus = {
  admin: [
    { label: 'Dashboard', icon: LayoutDashboard, path: 'dashboard' },
    { label: 'Data Dosen', icon: UserRound, path: 'data-dosen' },
    { label: 'Data Mahasiswa', icon: UsersRound, path: 'data-mahasiswa' },
    { label: 'Dosen Wali', icon: UserRound, path: 'dosen-wali' },
    { label: 'Data Perwalian', icon: ClipboardCheck, path: 'data-perwalian' },
    { label: 'Rekap & Laporan', icon: BookOpenCheck, path: 'rekap-laporan' },
  ],
  dosen: [
    { label: 'Dashboard', icon: LayoutDashboard, path: 'dashboard' },
    { label: 'Mahasiswa Wali', icon: UsersRound, path: 'mahasiswa-wali' },
    { label: 'Perwalian', icon: ClipboardCheck, path: 'histori-perwalian' },
  ],
  mahasiswa: [
    { label: 'Dashboard', icon: LayoutDashboard, path: 'dashboard' },
    { label: 'Catat Perwalian', icon: ClipboardCheck, path: 'catat-perwalian' },
    { label: 'Histori Perwalian', icon: History, path: 'histori-perwalian' },
  ],
}

export const serviceContent = {
  admin: [
    ['Data Dosen', 'Kelola akun dosen secara terpisah.', UserRound],
    ['Data Mahasiswa', 'Kelola akun mahasiswa secara terpisah.', UsersRound],
    ['Dosen Wali', 'Atur penugasan dosen dan mahasiswa wali.', UserRound],
    ['Data Perwalian', 'Pantau seluruh catatan konsultasi akademik.', ClipboardCheck],
    ['Rekap & Laporan', 'Susun laporan aktivitas perwalian kampus.', BookOpenCheck],
  ],
  dosen: [
    ['Mahasiswa Wali', 'Lihat daftar mahasiswa bimbingan aktif.', UsersRound],
    ['Perwalian', 'Kelola antrean aktif dan arsip konsultasi mahasiswa.', ClipboardCheck],
    ['Arsip Selesai', 'Lihat kembali catatan perwalian yang sudah ditutup.', History],
  ],
  mahasiswa: [
    ['Catat Perwalian', 'Tuliskan hasil konsultasi bersama dosen wali.', ClipboardCheck],
    ['Histori Perwalian', 'Lihat kembali perjalanan konsultasi akademik.', History],
  ],
}
