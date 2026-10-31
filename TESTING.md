# Hasil Pengujian Checkpoint 1

Diperbarui 27 Oktober 2026. Semua transaksi demonstrasi sintetis.
Hasil di bawah berasal dari output terminal dan RabbitMQ selama pengerjaan.
Ini bukan pengujian menggunakan transaksi atau stok nyata owner.

## Pengujian otomatis terakhir yang tercatat

| Layanan | Test lulus | Assertions |
|---|---:|---:|
| Inventory | 29 | 179 |
| Reporting | 7 | 14 |

Jalankan dari folder layanan masing-masing:

~~~bash
./vendor/bin/sail artisan test
~~~

Feature test menggunakan SQLite di memori. Mock pada test relay tidak
mengirim pesan nyata. Pengujian manual berikut menggunakan MySQL dan RabbitMQ.
Jumlah test berbeda dari jumlah skenario integrasi dan bukan jaminan seluruh
kemungkinan input/gangguan telah diperiksa.

## Kontrak dan API

| Pemeriksaan | Hasil aktual |
|---|---|
| Spectral pada kontrak rancangan 0.3.0 | Tidak ada error |
| Swagger UI | Kontrak dapat dibuka di port 8082 |
| POST produk sintetis | 201, header Location tersedia, stok awal 20 |
| GET detail produk | 200 dengan data produk |
| Penyesuaian stok | Berhasil dan stok berubah sesuai permintaan |
| Stok tidak cukup | Galat 409 Problem Details; stok tetap |
| GET produk dengan limit=1 melalui Swagger | 200 dengan satu produk |
| Input limit=0 di Swagger | Validasi browser menahan request |
| GET produk dengan limit=0 melalui curl | Server 422, application/problem+json, errors.limit |
| Penjualan positif dan restok negatif | Feature test menunjukkan 422 tanpa efek stok, operasi atau Outbox |
| REST operation_id diulang | Feature test lulus; efek tidak digandakan |
| operation_id sama dengan isi berbeda | Feature test menunjukkan konflik 409 |

Review kontrak oleh anggota selain penulis belum tercatat. Lulus Spectral
dan tampil di Swagger tidak menggantikan review manusia.

## Event, consumer dan Outbox

| Pemeriksaan | Hasil aktual | Bukti |
|---|---|---|
| Inventory → Outbox → RabbitMQ → Reporting | Relay mengirim; consumer mencatat event | Output terminal pengerjaan |
| Event menunggu sebelum consumer dijalankan | Pesan terlihat Ready lalu diproses saat Reporting hidup | Output queue dan consumer |
| Replay message_id yang sama | Dilewati; jurnal/inbox tidak bertambah | Output terminal dan query jumlah |
| Publish-subscribe | Reporting dan Monitoring menerima ID event yang sama | Dua output consumer |
| Payload tidak lengkap | Masuk reporting.penjualan.dlq | [Uji DLQ](inventory-service/docs/uji-dlq.md) |
| Header x-death | Queue asal reporting.penjualan, reason rejected, count 1 | Tampilan Management UI |
| Publish UI dengan ID sama dua kali | Dicatat lalu Dilewati; jurnal dan inbox untuk event itu masing-masing 1 | [Uji UI](inventory-service/docs/uji-publish-ui.md) |
| Broker mati lalu pulih | API tetap 200, Outbox menyimpan pesan, relay mengirim setelah pemulihan | [Broker mati](inventory-service/docs/uji-broker-mati.md) |
| Urutan tiga event | Jurnal menyimpan jumlah [1, 2, 3] pada satu relay dan consumer | [Urutan event](inventory-service/docs/uji-urutan-event.md) |

Monitoring tidak menulis jurnal penjualan. Seperti contoh notifikasi P05,
queue monitoring tidak memakai DLQ: salinan rusak ditolak tanpa requeue
dengan peringatan terminal. Queue Reporting menggunakan DLQ.

## Snapshot akhir skenario urutan

- Produk sintetis HJB-001: stok 9.
- Outbox tertunda: 0.
- Jurnal Reporting: 7.
- Inbox Reporting: 7.

Ini snapshot, bukan angka yang harus sama setelah setiap demonstrasi.
Jurnal mencakup fixture UJI-001 yang dipublish langsung dari Management UI;
fixture itu tidak mengubah Inventory. Beberapa perubahan stok awal juga dibuat
sebelum Outbox ditambahkan. Karena itu data latihan ini bukan basis rekonsiliasi
stok awal atau laporan penjualan nyata owner.

## Batas dan pekerjaan verifikasi tersisa

- Seluruh output belum disimpan sebagai log/screenshot terstruktur.
- Mode Get messages saat membaca DLQ tidak tercatat; UI menampilkan
  0 messages remaining setelah pengambilan. Jangan mengklaim pesan masih ada.
- Skenario literal P05 Bab 5 berupa tiga penjualan ketika consumer saja mati
  dan broker tetap hidup belum dicatat terpisah. Buffering satu event sudah
  terlihat; uji tiga event P06 memakai broker mati juga.
- Urutan global banyak relay/consumer dan operasi stok bersamaan belum diuji.
- Clone bersih pada komputer yang sama berhasil diuji; lihat catatan 31 Oktober 2026. Laptop anggota lain belum diuji.
- Review kontrak anggota lain dan kontribusi commit belum diverifikasi.
- Import, parser file owner, status pesanan, retur dan harga owner belum diuji.
- Tidak ada pengujian dashboard, autentikasi atau deployment cluster.

## Aturan pencatatan

Tuliskan hasil aktual setelah perintah dijalankan. Jangan mengisi hasil dengan
ekspektasi, membuat log lama, atau mengubah jumlah agar cocok dengan contoh.
Simpan screenshot hanya dari data sintetis, tanpa password, APP_KEY atau .env.

## Uji clone bersih — 31 Oktober 2026

Pengujian dilakukan dari clone GitHub pada komputer Ubuntu WSL2 yang sama,
dalam folder rantauan-cek-setup, dengan konfigurasi lokal, nama proyek
Compose, volume dan port terpisah dari proyek utama.

- Dependensi kedua layanan berhasil diinstal dari composer.lock.
- Pemeriksaan platform PHP 8.5.11 berhasil pada kedua layanan.
- Migrasi Inventory dan Reporting berhasil.
- Migrasi Reporting sempat gagal karena MySQL belum siap; berhasil setelah healthy.
- GET produk pada port 8010: 200 dengan data kosong sebelum produk uji dibuat.
- Inventory: 29 test lulus, 179 assertions.
- Reporting: 7 test lulus, 14 assertions.
- Produk sintetis CEK-001 dibuat: 201 dan Location.
- Penjualan satu unit: 200, stok berubah dari 10 menjadi 9.
- Relay mengirim satu event; jurnal Reporting untuk operasi tersebut berjumlah 1.
- Container pengujian dihentikan setelah pemeriksaan; data tidak dihapus.

Hasil ini membuktikan setup clone pada komputer yang sama, bukan pengujian
pada komputer kedua. Port pengujian berbeda dari port default README.
