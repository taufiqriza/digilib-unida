# AI SYSTEM TASK – Modul staff_manage (Manajemen SDM) untuk SLiMS 9.5.2

Kamu adalah seorang SOFTWARE ENGINEER EXPERT yang memahami sepenuhnya struktur internal SLiMS (Senayan Library Management System) versi 9.5.2, termasuk mekanisme modul admin, sidebar, sistem login, serta koneksi database melalui sysconfig.inc.php. Tugasmu adalah membuat satu modul baru bernama `staff_manage` (Manajemen SDM) untuk sistem SLiMS, yang memiliki antarmuka modern, profesional, dan responsif, serta menyediakan fitur kehadiran publik berbasis QR + PIN + GPS.

Buat modul ini sepenuhnya kompatibel dengan SLiMS 9.5.2 tanpa mengubah core system. Pastikan seluruh fungsi dan file dapat langsung berjalan setelah folder ditempatkan di `/admin/modules/`.

====================================================================
🎯 TUJUAN
====================================================================
Membangun sistem Manajemen SDM (staf dan tendik) yang memungkinkan admin perpustakaan memantau aktivitas, jadwal, kehadiran, dan kinerja staf secara efisien. Modul ini juga mencakup versi publik untuk absensi mandiri menggunakan PIN dan QR Scanner, seperti contoh tampilan referensi yang telah diberikan.

====================================================================
📦 OUTPUT FINAL
====================================================================
1. Folder `/admin/modules/staff_manage` lengkap dan siap jalan di SLiMS
2. Folder `/public/staff_attendance` untuk akses publik (QR + PIN)
3. Semua file `.php`, `.css`, `.js` dan aset pendukung sudah lengkap
4. Seluruh file menggunakan style coding native SLiMS + PHP modern (OOP & fungsi modular)
5. Kompatibel dengan versi PHP 7.4+ dan Bootstrap/Tailwind modern

====================================================================
🧱 STRUKTUR DIREKTORI YANG HARUS DIHASILKAN
====================================================================
staff_manage/
 ├── index.php
 ├── attendance.php
 ├── schedule.php
 ├── todo.php
 ├── location.php
 ├── stats.php
 ├── activity.php
 ├── inc/
 │    ├── config.inc.php
 │    ├── function.inc.php
 │    └── ui.inc.php
 └── assets/
      ├── css/
      ├── js/
      └── img/

public/staff_attendance/
 ├── index.php
 ├── scan.php
 ├── location_select.php
 └── assets/
      ├── css/
      ├── js/
      └── img/

====================================================================
🧭 STRUKTUR MENU SIDEBAR ADMIN
====================================================================
📁 Manajemen SDM
 ├── Overview
 ├── Kehadiran Staf
 ├── Jadwal Piket
 ├── TodoList
 ├── Seting Lokasi
 ├── Statistik Kinerja
 └── Aktivitas Staf

====================================================================
🧩 FUNGSI DAN FITUR
====================================================================
• OVERVIEW (index.php)
  - Dashboard ringkasan: total staf, hadir hari ini, tugas selesai, lokasi aktif.
  - Chart.js grafik kehadiran bulanan & performa.
  - Tabel ringkas jadwal piket hari ini.
  - Daftar aktivitas terakhir staf.

• KEHADIRAN STAF
  - CRUD kehadiran (check-in, check-out) berdasarkan GPS atau input manual.
  - Filter tanggal & lokasi.
  - Mode validasi otomatis berdasarkan radius lokasi aktif.

• JADWAL PIKET
  - Pengaturan shift & lokasi kerja staf (Resepsionis, Processing, Referensi).
  - Jadwal mingguan/hari tertentu dengan status aktif/nonaktif.

• TODOLIST
  - Sistem tugas individu & tim.
  - Status: Belum, Proses, Selesai.
  - Kolom progress bar, due date, filter prioritas.

• SETING LOKASI
  - Input nama lokasi, lat, long, radius, status aktif.
  - Lihat lokasi di peta (gunakan Leaflet.js / Google Maps).
  - Integrasi dengan kehadiran.

• STATISTIK KINERJA
  - Tampilkan grafik & tabel rekap (Chart.js).
  - Laporan bulanan kehadiran, keterlambatan, produktivitas.

• RIWAYAT AKTIVITAS
  - Menampilkan semua log staf (waktu, lokasi, tindakan).

====================================================================
🌐 SISTEM PUBLIK SCANNER (staff_attendance)
====================================================================
• Halaman login PIN (index.php)
  - Input PIN staf untuk autentikasi.
  - Jika valid → redirect ke halaman Scan.

• Halaman scan (scan.php)
  - Kamera aktif, menampilkan area scan QR.
  - Tombol “Scan QR” dan “Pilih Lokasi”.
  - Menampilkan status login, tanggal, dan lokasi aktif.
  - UI seperti referensi gambar (tema biru modern, icon & card gradient).

• Halaman pilih lokasi (location_select.php)
  - Menampilkan daftar lokasi aktif (nama, deskripsi, status GPS aktif).
  - Jika user berada di radius lokasi → aktifkan tombol “Check-in”.
  - Jika di luar radius → tampilkan peringatan.

====================================================================
🎨 DESAIN & UI REQUIREMENTS
====================================================================
• Gunakan gaya modern elegan:
  - Warna dominan biru (#2563eb – #38bdf8)
  - Font: Inter / Poppins / Ubuntu
  - Rounded corner, shadow lembut, grid responsif.
  - Tabel modern + card + chart dinamis.
• Gunakan ikon Feather atau FontAwesome.
• Mobile-first design (harus tampil bagus di HP & PC).
• Tombol besar dan mudah digunakan untuk publik (seperti referensi screenshot).
• Gunakan Chart.js, Leaflet.js, dan Bootstrap 5/Tailwind (bebas pilih).

====================================================================
🧠 PERINTAH UNTUK AI
====================================================================
1. Pelajari struktur modul di `/admin/modules/` SLiMS 9.5.2 untuk memahami pola include, sidebar, dan routing modul.
2. Bangun modul baru `staff_manage` dengan semua fitur di atas.
3. Hasilkan seluruh file PHP, HTML, CSS, dan JS dengan struktur folder yang lengkap.
4. Gunakan metode pengambilan data sesuai konvensi SLiMS (`sysconfig.inc.php`).
5. Tambahkan komentar di setiap file untuk dokumentasi.
6. Buat interface admin dan publik yang terhubung dengan lancar.
7. Untuk database, AI diminta membuat struktur tabel dan relasi yang sesuai berdasarkan konteks fitur (tidak perlu dijelaskan di sini, buat langsung di dalam kode).
8. Semua hasil output harus bisa langsung dijalankan di SLiMS tanpa error setelah copy folder.

====================================================================
⚙️ OUTPUT YANG HARUS DIHASILKAN
====================================================================
• Folder `staff_manage` dan `staff_attendance` lengkap.
• File PHP berisi struktur layout siap pakai (HTML + PHP native SLiMS).
• File CSS dan JS modern.
• Dummy data/seed untuk simulasi tampilan awal.
• Semua interface siap ditautkan di menu admin dan URL publik.

====================================================================
🚀 INSTRUKSI EKSEKUSI
====================================================================
Jalankan seluruh perintah pembuatan modul ini secara otomatis. Hasilkan file, struktur direktori, tampilan, dan logika yang lengkap berdasarkan spesifikasi di atas. Gunakan gaya penulisan bersih, terstruktur, dan kompatibel dengan PHP 7.4+. Fokuskan pada kerapian UI dan kelancaran fungsi.

Mulai eksekusi sekarang.
