# Rantau.an Hijab — Checkpoint 1

Proyek kelompok Integrasi Sistem dan Komputasi Awan, RENR Project.
Dokumentasi diperbarui 1 November 2026.

## Tujuan dan ruang lingkup

Rancangan sistem membantu menggabungkan data pesanan marketplace dengan
pencatatan persediaan melalui impor file manual. Shopee dan TikTok Shop
direncanakan memiliki adapter masing-masing menuju format internal yang sama.

Saat ini **Inventory dan Reporting sudah berjalan**. Import Service dan dashboard
admin belum dibuat. Tidak ada integrasi langsung API marketplace atau pembaruan
stok Seller Center secara otomatis.

Semua transaksi pengembangan dan demonstrasi menggunakan data sintetis.
Data owner Shopee telah dianalisis secara lokal dan terbatas. Data owner TikTok
belum tersedia. Stok simulasi bukan stok fisik owner dan rekap kuantitas belum
merupakan laporan omzet usaha.

## Struktur repository

~~~text
rantauan-uts/
├── README.md
├── docs/
│   ├── arsitektur.md
│   └── SETUP.md
├── inventory-service/
│   ├── openapi.yaml
│   ├── .spectral.yaml
│   ├── docs/
│   ├── app/
│   ├── database/migrations/
│   └── tests/
└── reporting-service/
    ├── app/
    ├── database/migrations/
    └── tests/
~~~

## Arsitektur dan tanggung jawab

[Diagram arsitektur](docs/arsitektur.md) membedakan komponen berjalan dan rencana.

| Layanan | Tanggung jawab | Status |
|---|---|---|
| Import | Parser file, validasi, mapping produk, deduplikasi dan ringkasan impor | Direncanakan |
| Inventory | Produk, stok, idempotensi operasi REST dan Outbox | Berjalan |
| Sales Reporting | Consumer, inbox dan jurnal kuantitas penjualan | Berjalan |

Inventory dan Reporting mempunyai database masing-masing. Command Monitoring
berada dalam proyek Reporting sebagai subscriber demonstrasi, bukan layanan
aplikasi ketiga yang berdiri sendiri.

REST dari Postman, curl atau Swagger menuju Inventory sudah berjalan. REST Import ke Inventory
masih direncanakan. Jalur asinkron yang berjalan:

~~~text
Inventory: transaksi stok + ledger operasi + Outbox
→ relay → RabbitMQ → Reporting: transaksi inbox + jurnal → ack
                    └→ queue terpisah → Monitoring
~~~

Reporting dapat tertinggal sampai event diproses. Broker mati tidak otomatis
membatalkan perubahan stok yang sah; event menunggu di Outbox untuk dikirim ulang.

## Teknologi

Laravel 13, runtime Sail PHP 8.5, MySQL 8.4, RabbitMQ 4.3 Management,
php-amqplib seri 3.7, OpenAPI 3.1, Spectral dan Swagger UI.
Versi dependensi dikunci melalui composer.lock di masing-masing layanan.
Feature test memakai SQLite di memori; pengujian manual memakai MySQL.

## Instalasi dari nol

Ikuti [panduan SETUP](docs/SETUP.md). Panduan mencakup checkout baru,
instalasi Composer melalui Docker, konfigurasi lokal dan migrasi.

Repository kelompok: https://github.com/rekha0802/rantauan-uts
Uji clone bersih berhasil pada komputer yang sama dengan port dan volume
terpisah. Hasil tersedia di TESTING.md. Laptop anggota lain belum diuji.

## Menjalankan lingkungan yang sudah terpasang

Jalankan dari Ubuntu. Lokasi berikut mengikuti folder pengembangan kelompok.

~~~bash
cd ~/rantauan-uts/inventory-service
./vendor/bin/sail up -d
./vendor/bin/sail exec rabbitmq rabbitmq-diagnostics -q ping
./vendor/bin/sail artisan rabbitmq:siapkan

cd ~/rantauan-uts/reporting-service
./vendor/bin/sail up -d
~~~

Tunggu MySQL healthy dan RabbitMQ berhasil ping. Jika broker masih memulai,
tunggu sebentar lalu ulangi pemeriksaannya sebelum melanjutkan.

Gunakan terminal terpisah untuk proses berikut:

~~~bash
# Terminal A: Reporting
cd ~/rantauan-uts/reporting-service
./vendor/bin/sail artisan reporting:konsumsi
~~~

~~~bash
# Terminal B: Monitoring demonstrasi
cd ~/rantauan-uts/reporting-service
./vendor/bin/sail artisan monitoring:stok
~~~

~~~bash
# Terminal C: relay satu proses
cd ~/rantauan-uts/inventory-service
./vendor/bin/sail artisan outbox:relay --terus
~~~

Ctrl+C menghentikan proses terminal tersebut. Relay awal dirancang untuk satu
proses aktif; pengujian urutan tidak membuktikan urutan global banyak proses.

| Komponen | Alamat/port host |
|---|---|
| Inventory | http://localhost:8000 |
| API daftar produk | http://localhost:8000/api/v1/produk |
| Reporting, halaman Laravel | http://localhost:8001 |
| RabbitMQ Management | http://localhost:15673 |
| AMQP UTS | 5673 |
| MySQL Inventory | 3309 |
| MySQL Reporting | 3310 |
| Swagger UI, bila dijalankan | http://localhost:8082 |

Password dan APP_KEY disimpan dalam .env lokal dan tidak dicantumkan di README.

## API dan kontrak

[OpenAPI Inventory](inventory-service/openapi.yaml), versi rancangan 0.3.0.

| Operasi | Hasil utama |
|---|---|
| GET /api/v1/produk | 200; limit opsional 1–100, invalid menghasilkan 422 |
| POST /api/v1/produk | 201 + Location; validasi gagal 422 |
| GET /api/v1/produk/{sku} | 200 atau 404 |
| POST /api/v1/produk/{sku}/penyesuaian-stok | 200; 404, 409 atau 422 |

Galat yang dikontrakkan memakai application/problem+json. Penjualan harus
mengurangi stok, restok harus menambah stok, koreksi boleh kedua arah tetapi
bukan nol. Ini aturan operasi stok simulasi, bukan aturan status pesanan owner
yang telah dikonfirmasi.

operation_id yang sama dengan isi sama mengembalikan respons pertama tanpa efek
stok/event baru; isi berbeda menghasilkan 409. Pengiriman ulang event memakai
message_id yang sama. Reporting memproses message_id paling banyak sekali dalam
inbox; transport tetap at-least-once, bukan exactly-once.

[Kontrak event](inventory-service/docs/kontrak-event.md) memuat payload versi 1.
Subscriber demonstrasi tambahan menggunakan queue monitoring.stok dengan binding
stok.*.v1. Reporting memakai reporting.penjualan dengan binding stok.penjualan.v1
dan DLQ reporting.penjualan.dlq melalui rantauan.dlx.

## Pengujian

Lihat [catatan pengujian terpusat](TESTING.md) dan
[panduan demo empat menit](docs/PANDUAN_DEMO.md).

~~~bash
cd ~/rantauan-uts/inventory-service
./vendor/bin/sail artisan test

cd ~/rantauan-uts/reporting-service
./vendor/bin/sail artisan test
~~~

Hasil terakhir yang tercatat dalam pengerjaan, bukan janji hasil mesin baru:

- Inventory: 29 passed, 179 assertions.
- Reporting: 7 passed, 14 assertions.
- Spectral: tidak ada error pada kontrak versi rancangan 0.3.0.
- Uji manual: dua subscriber, replay, DLQ dan x-death, UI publish,
  broker mati/pulih, dan urutan tiga event pada satu relay/consumer.

Bukti skenario:

- [Broker mati dan pulih](inventory-service/docs/uji-broker-mati.md)
- [Dead letter queue](inventory-service/docs/uji-dlq.md)
- [Publish langsung dari UI dan duplikat](inventory-service/docs/uji-publish-ui.md)
- [Urutan event](inventory-service/docs/uji-urutan-event.md)

Publish langsung dari UI adalah fixture consumer: tidak mengubah Inventory.
Catatan UJI-001 harus dipisahkan dari rekonsiliasi stok produk Inventory.

Untuk lint dan Swagger, lihat SETUP. Review kontrak oleh anggota lain belum
tercatat; tampilnya Swagger dan lulus lint tidak menggantikan review manusia.

## Kelompok dan pembagian tugas

Kelompok telah menetapkan pembagian tanggung jawab berikut.
Penugasan mencakup pekerjaan Checkpoint 1 dan rancangan pengembangan selanjutnya.

| Anggota | Tanggung jawab |
|---|---|
| Rekhanur Faidah | Kontrak REST, Inventory, stok dan Outbox |
| Nurul Zahra Fatimah | Analisis data, rancangan Import dan mapping produk |
| Reisya Sahriyatul Fitrri | RabbitMQ, Reporting dan pengujian consumer |
| Eka Putri | Dokumentasi, pengujian API melalui Postman dan persiapan demo |

Anggota kelompok telah memperoleh akses collaborator pada repository.
Setiap anggota menggunakan akun sendiri untuk mencatat perubahan yang dikerjakan.
Akses collaborator berbeda dari bukti instalasi layanan pada komputer anggota.

## Kelengkapan pengumpulan Checkpoint 1

- Dokumen analisis final 7 halaman, Times New Roman dan spasi 1,15.
- Slides presentasi final 21 slide, termasuk identitas anggota dan pembagian tugas.
- Repository kode dengan OpenAPI, kontrak event, feature test dan panduan setup.
- [Collection Postman](docs/postman/Rantauan-Checkpoint1.postman_collection.json) untuk pengujian API.
- Kontrak event telah diselaraskan dengan subscriber Monitoring.
- Uji clone bersih pada komputer yang sama telah berhasil.

PDF dan PPT disiapkan sebagai berkas pengumpulan terpisah. Bagian ini tidak
menyatakan bahwa kedua berkas tersebut sudah diunggah ke repository.

## Persiapan demonstrasi

Gunakan [panduan demo](docs/PANDUAN_DEMO.md), collection Postman dan hasil test.
Siapkan Docker Desktop, terminal Ubuntu, Postman serta RabbitMQ Management.
Gunakan data sintetis dan identitas operasi baru untuk transaksi baru.
Hasil review kontrak anggota lain dan durasi latihan utuh belum dicatat di sini.
Uji instalasi pada komputer anggota lain belum termasuk bukti yang tersedia.

Import, dashboard, autentikasi dan deployment cluster belum diimplementasikan.
Lingkup berikutnya harus mengikuti instruksi dosen yang tersedia; dokumen ini
tidak mengklaim semua ketentuan UAS sudah diketahui atau selesai.

## Privasi

Dataset owner, salinan kerja pseudonim, password, APP_KEY, raw uploads dan log
sensitif tidak disertakan dalam repository atau paket tugas. .gitignore membantu
mencegah penambahan file, tetapi isi file dan riwayat Git tetap perlu diperiksa.
Gunakan transaksi sintetis untuk screenshot, contoh API dan demonstrasi.
