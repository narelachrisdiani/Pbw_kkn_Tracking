-- MySQL dump 10.13  Distrib 8.0.19, for Win64 (x86_64)
--
-- Host: localhost    Database: db_kkn_tracking
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `evaluasi`
--

DROP TABLE IF EXISTS `evaluasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `evaluasi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `program_id` int NOT NULL,
  `lokasi_id` int NOT NULL,
  `indikator` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `target` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `realisasi` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `persentase_capaian` decimal(5,2) DEFAULT NULL,
  `dampak_terukur` text COLLATE utf8mb4_general_ci,
  `testimoni_masyarakat` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_program` (`program_id`),
  KEY `idx_lokasi` (`lokasi_id`),
  CONSTRAINT `evaluasi_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `program_kkn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluasi_ibfk_2` FOREIGN KEY (`lokasi_id`) REFERENCES `lokasi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluasi`
--

LOCK TABLES `evaluasi` WRITE;
/*!40000 ALTER TABLE `evaluasi` DISABLE KEYS */;
INSERT INTO `evaluasi` VALUES (1,1,1,'Peningkatan kesadaran kebersihan lingkungan','80% warga memilah sampah','65% warga memilah sampah',81.25,'Berkurangnya volume sampah yang dibuang sembarangan sebesar 40%','Program sangat membantu, desa jadi lebih bersih dan sehat','2026-04-21 12:36:27','2026-04-21 12:36:27'),(2,1,1,'Pemberdayaan masyarakat dalam pengolahan sampah','5 kelompok kompos','5 kelompok kompos terbentuk',100.00,'Terbentuknya 5 kelompok pengomposan yang aktif','Kami sekarang bisa menghasilkan pupuk sendiri untuk kebun','2026-04-21 12:36:27','2026-04-21 12:36:27'),(3,1,2,'Akses layanan kesehatan dasar','100 warga diperiksa','75 warga diperiksa',75.00,'Terdeteksi 15 kasus hipertensi dan 8 kasus diabetes','Warga jadi tahu kondisi kesehatannya dan lebih peduli','2026-04-21 12:36:27','2026-04-21 12:36:27');
/*!40000 ALTER TABLE `evaluasi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kegiatan`
--

DROP TABLE IF EXISTS `kegiatan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kegiatan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `penempatan_id` int NOT NULL,
  `judul_kegiatan` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `jenis_kegiatan` enum('sosialisasi','pelatihan','pembangunan','pendampingan','penyuluhan','lainnya') COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal_kegiatan` date NOT NULL,
  `lokasi_kegiatan` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `peserta_count` int DEFAULT '0',
  `deskripsi` text COLLATE utf8mb4_general_ci NOT NULL,
  `indikator_capaian` text COLLATE utf8mb4_general_ci,
  `kendala` text COLLATE utf8mb4_general_ci,
  `solusi` text COLLATE utf8mb4_general_ci,
  `dokumentasi_foto` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('direncanakan','berjalan','selesai') COLLATE utf8mb4_general_ci DEFAULT 'direncanakan',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_penempatan` (`penempatan_id`),
  KEY `idx_tanggal` (`tanggal_kegiatan`),
  KEY `idx_status` (`status`),
  CONSTRAINT `kegiatan_ibfk_1` FOREIGN KEY (`penempatan_id`) REFERENCES `penempatan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kegiatan`
--

LOCK TABLES `kegiatan` WRITE;
/*!40000 ALTER TABLE `kegiatan` DISABLE KEYS */;
INSERT INTO `kegiatan` VALUES (1,1,'Sosialisasi Pentingnya Kebersihan Lingkungan','sosialisasi','2024-01-20','Balai Desa Sukamaju',50,'Penyuluhan tentang pentingnya menjaga kebersihan lingkungan dan cara pengolahan sampah','Masyarakat memahami cara pemilahan sampah organik dan anorganik','Keterbatasan alat peraga','Menggunakan video presentasi',NULL,'selesai','2026-04-21 12:36:27'),(2,1,'Pelatihan Pembuatan Kompos dari Sampah Organik','pelatihan','2024-01-27','Lapangan Desa Sukamaju',30,'Praktik langsung pembuatan kompos menggunakan bahan-bahan yang tersedia','Peserta mampu membuat kompos sendiri di rumah','Cuaca hujan','Melatih di dalam balai desa',NULL,'selesai','2026-04-21 12:36:27'),(3,2,'Pemeriksaan Kesehatan Gratis','penyuluhan','2024-01-22','Posyandu Mekar Sari',75,'Pemeriksaan tekanan darah, gula darah, dan konsultasi kesehatan','Tersedianya data kesehatan dasar warga','Keterbatasan tenaga medis','Bekerjasama dengan puskesmas',NULL,'selesai','2026-04-21 12:36:27'),(4,3,'Membersihkan lingkungan bersama warga','','2026-04-29','Desa sukamaju',25,'kegiatan ini dilaksanakan untuk membentuk kesadaran masyarakata akan pentingnya menjaga kebersihan longkungan guna membuat lingkungan yang lebih nyaman.','kebersihan','kurangnya kesadaran masyarakat terhadap lingkungan','membuat pengabdian masyarakat tentang kebersihan','69f15b8f1b71b_1777425295.jpeg','selesai','2026-04-29 01:14:55');
/*!40000 ALTER TABLE `kegiatan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `laporan`
--

DROP TABLE IF EXISTS `laporan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `laporan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kegiatan_id` int NOT NULL,
  `jenis_laporan` enum('harian','mingguan','bulanan','akhir') COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal_laporan` date NOT NULL,
  `uraian_kegiatan` text COLLATE utf8mb4_general_ci NOT NULL,
  `capaian` text COLLATE utf8mb4_general_ci,
  `kendala_lapangan` text COLLATE utf8mb4_general_ci,
  `dampak_sosial` text COLLATE utf8mb4_general_ci,
  `rekomendasi` text COLLATE utf8mb4_general_ci,
  `ttd_digital` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_verifikasi` enum('pending','disetujui','revisi') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `catatan_dpl` text COLLATE utf8mb4_general_ci,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `file_pdf` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `verified_by` (`verified_by`),
  KEY `idx_kegiatan` (`kegiatan_id`),
  KEY `idx_status` (`status_verifikasi`),
  KEY `idx_tanggal` (`tanggal_laporan`),
  CONSTRAINT `laporan_ibfk_1` FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `laporan_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laporan`
--

LOCK TABLES `laporan` WRITE;
/*!40000 ALTER TABLE `laporan` DISABLE KEYS */;
INSERT INTO `laporan` VALUES (1,1,'mingguan','2024-01-21','Melaksanakan sosialisasi kebersihan dengan metode ceramah dan tanya jawab','Target 50 peserta tercapai, antusiasme masyarakat tinggi','Sound system kurang memadai','Masyarakat mulai memilah sampah di rumah masing-masing','Perlu tindak lanjut dengan pembuatan bank sampah',NULL,'disetujui',NULL,NULL,NULL,'2026-04-21 12:36:27',NULL),(2,2,'mingguan','2024-01-28','Pelatihan pembuatan kompos dengan praktik langsung','25 peserta berhasil membuat kompos dengan baik','Bahan baku (sampah organik) terbatas','Muncul 5 kelompok pengomposan di RT berbeda','Perlu pendampingan rutin selama 1 bulan',NULL,'disetujui',NULL,NULL,NULL,'2026-04-21 12:36:27',NULL),(3,3,'mingguan','2024-01-23','Pemeriksaan kesehatan gratis bekerjasama dengan puskesmas','75 warga mendapat pemeriksaan, 15 dirujuk ke puskesmas','Antusiasme melebihi kuota','Deteksi dini penyakit hipertensi dan diabetes pada warga','Perlu program rutin bulanan',NULL,'disetujui','','2026-04-25 07:23:44',8,'2026-04-21 12:36:27',NULL),(4,4,'mingguan','2026-04-29','membersihkan lingkungan bersama warga sukamaju','kebersihan','-','mencipatakan kesadaran akan kebersihan','',NULL,'disetujui','','2026-05-19 12:05:08',2,'2026-04-29 01:21:39',NULL),(5,4,'harian','2026-06-02','strategi membersihkan lingkungan','berhasil','banjir','manfaat','lebih rajin',NULL,'pending',NULL,NULL,NULL,'2026-06-02 08:31:13','laporan_6a1e94d1edb25_1780389073.pdf');
/*!40000 ALTER TABLE `laporan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lokasi`
--

DROP TABLE IF EXISTS `lokasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lokasi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_desa` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `kecamatan` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `kabupaten` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `provinsi` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `alamat_detail` text COLLATE utf8mb4_general_ci,
  `koordinat_lat` decimal(10,8) DEFAULT NULL,
  `koordinat_long` decimal(11,8) DEFAULT NULL,
  `nama_pemdes` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kontak_pemdes` varchar(15) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_desa` (`nama_desa`),
  KEY `idx_kecamatan` (`kecamatan`,`kabupaten`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lokasi`
--

LOCK TABLES `lokasi` WRITE;
/*!40000 ALTER TABLE `lokasi` DISABLE KEYS */;
INSERT INTO `lokasi` VALUES (1,'Sukamaju','Cikarang Utara','Bekasi','Jawa Barat','Jl. Raya Sukamaju No. 123, RT 01/02',NULL,NULL,'H. Suryadi','081234567890','2026-04-21 12:36:27'),(2,'Mekar Sari','Tambun Selatan','Bekasi','Jawa Barat','Jl. Mekar Sari KM 5',NULL,NULL,'Drs. Ahmad Fauzi','081234567891','2026-04-21 12:36:27'),(3,'Sumber Jaya','Cikarang Barat','Bekasi','Jawa Barat','Jl. Sumber Jaya Raya',NULL,NULL,'Hj. Siti Maimunah','081234567892','2026-04-21 12:36:27'),(4,'Harapan Baru','Tambun Utara','Bekasi','Jawa Barat','Jl. Harapan Indah Blok A',NULL,NULL,'Drs. Bambang Sutrisno','081234567893','2026-04-21 12:36:27'),(6,'Padakan','Grogol','Sukoharjo','Jawa Tengah','Padakan 01/01 sukoharjo',NULL,NULL,'Joko Susilo','081288976778','2026-04-24 12:56:25');
/*!40000 ALTER TABLE `lokasi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `penempatan`
--

DROP TABLE IF EXISTS `penempatan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `penempatan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mahasiswa_id` int NOT NULL,
  `lokasi_id` int NOT NULL,
  `program_id` int NOT NULL,
  `dpl_id` int NOT NULL,
  `tanggal_penempatan` date NOT NULL,
  `status` enum('aktif','selesai','mengundurkan_dir') COLLATE utf8mb4_general_ci DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `dpl_id` (`dpl_id`),
  KEY `idx_mahasiswa` (`mahasiswa_id`),
  KEY `idx_lokasi` (`lokasi_id`),
  KEY `idx_program` (`program_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `penempatan_ibfk_1` FOREIGN KEY (`mahasiswa_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `penempatan_ibfk_2` FOREIGN KEY (`lokasi_id`) REFERENCES `lokasi` (`id`),
  CONSTRAINT `penempatan_ibfk_3` FOREIGN KEY (`program_id`) REFERENCES `program_kkn` (`id`),
  CONSTRAINT `penempatan_ibfk_4` FOREIGN KEY (`dpl_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `penempatan`
--

LOCK TABLES `penempatan` WRITE;
/*!40000 ALTER TABLE `penempatan` DISABLE KEYS */;
INSERT INTO `penempatan` VALUES (1,3,1,1,1,'2026-04-24','aktif','2026-04-21 12:36:27'),(2,4,2,1,1,'2026-04-24','aktif','2026-04-21 12:36:27'),(3,5,1,1,2,'2024-01-15','aktif','2026-04-21 12:36:27'),(5,10,4,2,2,'2026-04-25','aktif','2026-04-25 07:18:02'),(6,11,3,1,2,'2026-04-25','aktif','2026-04-25 07:20:27');
/*!40000 ALTER TABLE `penempatan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `program_kkn`
--

DROP TABLE IF EXISTS `program_kkn`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `program_kkn` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kode_program` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `nama_program` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `periode` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `tahun` year NOT NULL,
  `tanggal_mulai` date DEFAULT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `status` enum('aktif','selesai','ditunda') COLLATE utf8mb4_general_ci DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_program` (`kode_program`),
  KEY `idx_tahun` (`tahun`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `program_kkn`
--

LOCK TABLES `program_kkn` WRITE;
/*!40000 ALTER TABLE `program_kkn` DISABLE KEYS */;
INSERT INTO `program_kkn` VALUES (1,'KKN2026-01','KKN Kesejahteraan masyarakat 2026','Januari - Maret 2026',2026,'2026-01-25','2026-12-25','aktif','2026-04-21 12:36:27'),(2,'KKN2024-02','KKN Tematik Kesehatan 2024','Juli - September 2024',2024,'2024-07-01','2024-09-30','aktif','2026-04-21 12:36:27'),(3,'KKN2026-03','KKN Ahli gizi dan pangan 2026','Januari - Maret 2026',2026,'2026-01-25','2026-12-25','aktif','2026-04-25 07:48:04');
/*!40000 ALTER TABLE `program_kkn` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `npm_nip` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','dpl','mahasiswa','lembaga') COLLATE utf8mb4_general_ci NOT NULL,
  `lokasi_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `npm_nip` (`npm_nip`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_npm_nip` (`npm_nip`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'DPL001','Dr. Ahmad Santoso, M.Kom','ahmad@univ.ac.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','dpl',NULL,'2026-04-21 12:36:27'),(2,'DPL002','Dewi Lestari, M.Si','dewi@univ.ac.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','dpl',NULL,'2026-04-21 12:36:27'),(3,'M001','Budi Pratama','budi@student.ac.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','mahasiswa',NULL,'2026-04-21 12:36:27'),(4,'M002','Siti Nurhaliza','siti@student.ac.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','mahasiswa',NULL,'2026-04-21 12:36:27'),(5,'M003','Narela Chrisdiani','narela@student.ac.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','mahasiswa',NULL,'2026-04-21 12:36:27'),(6,'LM001','H. Suryadi (Kades Sukamaju)','kades.sukamaju@gmail.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','lembaga',1,'2026-04-21 12:36:27'),(7,'LM002','Drs. Ahmad Fauzi (Kades Mekar Sari)','kades.mekarsari@gmail.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','lembaga',2,'2026-04-21 12:36:27'),(8,'Admin','Rosalia Indah ','Rosalia@gmail.com','admin123','admin',NULL,'2026-04-25 06:02:56'),(10,'M005','Isa Bagus Prakoso','Isa@gmail.com','$2y$10$.Efvc3ADFwoswQkl1juAweJRM1I6KUoo/H7X1nKM2l0G6r/GAquqq','mahasiswa',NULL,'2026-04-25 07:18:02'),(11,'M006','Yashinta Erma Sekarningtyas','yashin@gmail.com','$2y$10$C9e8oGpsmpwbivKcryVocOwtjdXRjRgK46rAlDoDBt4yhm0j5YEH2','mahasiswa',NULL,'2026-04-25 07:20:27');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'db_kkn_tracking'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-06 15:56:17
