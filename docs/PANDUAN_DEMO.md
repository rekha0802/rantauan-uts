# Panduan Demo Checkpoint 1 — RENR Project

Target sekitar empat menit, mengikuti P07. Semua transaksi menggunakan data sintetis.
Langkah API dan event sudah dicoba secara bertahap; durasi seluruh demo dalam satu sesi belum diukur.

## Persiapan sebelum giliran

1. Jalankan Inventory, Reporting, dan RabbitMQ dengan Sail sesuai docs/SETUP.md.
2. Dari Inventory jalankan `./vendor/bin/sail artisan rabbitmq:siapkan`.
3. Buka Postman dan impor `docs/postman/Rantauan-Checkpoint1.postman_collection.json` jika belum tersedia.
4. Buka diagram `docs/arsitektur.md`. Siapkan RabbitMQ Management di http://localhost:15673 bila diperlukan.
5. Pastikan produk DMO-001 tersedia, stok minimal 1 dan kurang dari 1000000. Periksa melalui GET detail di Postman.
6. Pastikan Outbox tidak memiliki backlog. Proses pesan lama terlebih dahulu, jangan menghapus data untuk demo.
7. Jalankan hanya satu relay dan satu consumer Reporting untuk jalur demo ini.
8. Semua anggota hadir. Sepakati bagian penjelasan berdasarkan pemahaman dan pekerjaan yang benar-benar dilakukan.

Status terakhir latihan: DMO-001 stok 18. Ini catatan historis, bukan angka tetap untuk demo berikutnya.
Jika produk belum ada, buat sekali melalui POST produk. Jika stok habis, lakukan restok positif dengan ID operasi baru sebelum giliran dan proses event persiapannya.

### Terminal A — Inventory dan relay

~~~bash
cd ~/rantauan-uts/inventory-service
./vendor/bin/sail artisan tinker --execute="dump(App\Models\Outbox::whereNull('dikirim_pada')->count());"
~~~

Harapan sebelum demo: 0 pesan tertunda.

### Terminal B — consumer Reporting, biarkan berjalan

~~~bash
cd ~/rantauan-uts/reporting-service
./vendor/bin/sail artisan reporting:konsumsi
~~~

Jika consumer gagal membuka queue, periksa kesiapan broker dan jalankan rabbitmq:siapkan dari Inventory sebelum mencoba kembali. Ctrl+C menghentikan consumer; tidak menghapus jurnal yang sudah dicatat.

### Terminal C — pencatatan awal Reporting

~~~bash
cd ~/rantauan-uts/reporting-service
./vendor/bin/sail artisan tinker --execute="dump(DB::table('jurnal_penjualan')->where('sku_internal', 'DMO-001')->count());"
~~~

Catat jumlah awal. Jumlah setelah transaksi baru berhasil harus bertambah satu.

## Menit 0–1 — arsitektur

Contoh penjelasan:

> RENR Project merancang tiga layanan: Import, Inventory, dan Reporting.
> Inventory dan Reporting sudah berjalan; Import masih direncanakan.
> Postman mengirim REST ke Inventory. Stok dan event disimpan bersama dalam transaksi database.
> Relay mengirim event Outbox ke RabbitMQ; Reporting mencatatnya dengan inbox untuk menangani duplikasi.

Monitoring adalah command dalam Reporting, bukan layanan aplikasi ketiga.
Impor file owner, integrasi langsung API marketplace, dan dashboard belum dibuat.

## Menit 1–3 — Postman, test, dan event

### A. GET berhasil

Di Postman kirim GET http://localhost:8000/api/v1/produk.
Tunjukkan status 200 dan produk sintetis. Catat stok awal DMO-001.

### B. Galat bisnis

Buka request POST Stok Tidak Cukup — 409. Gunakan header Accept: application/json dan Body raw JSON:

~~~json
{
  "operation_id": "{{$guid}}",
  "perubahan": -1000000,
  "alasan": "penjualan"
}
~~~

URL: http://localhost:8000/api/v1/produk/DMO-001/penyesuaian-stok.
Tunjukkan status 409, header respons Content-Type: application/problem+json, dan field Problem Details.
Stok harus lebih kecil dari 1000000 agar skenario benar-benar ditolak.

### C. Feature test

Di Terminal A jalankan:

~~~bash
./vendor/bin/sail artisan test
~~~

Hasil terakhir terverifikasi: Inventory 29 passed / 179 assertions; Reporting 7 passed / 14 assertions.
Gunakan hasil aktual hari demo. Bila diminta, jalankan test Reporting di Terminal C.

### D. Penjualan baru dari Postman

Request POST Penjualan Berhasil — 200 memakai URL penyesuaian stok yang sama.
Sebelum tiap sesi demo, buat UUID baru di Terminal A:

~~~bash
cat /proc/sys/kernel/random/uuid
~~~

Salin hasilnya ke operation_id pada Body berikut, menggantikan placeholder:

~~~json
{
  "operation_id": "GANTI_DENGAN_UUID_BARU",
  "perubahan": -1,
  "alasan": "penjualan"
}
~~~

Jangan gunakan placeholder secara literal. Untuk pengujian ulang operasi yang sama, simpan UUID yang sudah dipilih dan jangan mengubah Body.
ID 31000000-0000-4000-8000-000000000001 dalam collection sudah dipakai pada database latihan; menggunakannya lagi mengembalikan hasil lama, bukan penjualan baru.

Klik Send sekali. Harapan: 200 dan stok berkurang satu dari stok awal.
Jika hasil tidak jelas, periksa dulu; jangan langsung mengirim operasi baru.

Di Terminal A jalankan:

~~~bash
./vendor/bin/sail artisan outbox:relay
~~~

Harapan: 1 pesan terkirim jika tidak ada backlog. Terminal B menampilkan Dicatat: event beserta UUID.
Di Terminal C jalankan kembali query jumlah jurnal DMO-001. Harapan: bertambah satu dari jumlah awal.

## Menit 3–4 — tanya jawab anggota

Pertanyaan latihan, bukan bocoran pertanyaan dosen:

- Apa tugas route, controller, model, dan Resource?
- Mengapa stok tidak cukup menghasilkan 409 dan input invalid 422?
- Mengapa stok dan Outbox harus satu transaksi?
- Apa perbedaan operation_id dan message_id?
- Mengapa ack dilakukan setelah penyimpanan berhasil? Kapan pesan menuju DLQ?
- Mengapa subscriber memakai queue berbeda?
- Mengapa data owner tidak dipakai sebagai transaksi demo?
- Layanan mana yang berjalan dan mana yang masih direncanakan?

## Bukti tambahan jika diminta

- POST produk baru: 201 dan Location. PST-001 dan PST-002 sudah dipakai; pilih SKU baru yang belum tersedia, sesuai pola tiga huruf, tanda hubung, tiga angka. POST ulang SKU yang sama menghasilkan 422.
- GET produk tidak ada: 404 Problem Details.
- Tanpa operation_id: 422 Problem Details.
- Retry operasi: kirim Body penjualan yang sama dengan UUID tetap. Hasil operasi lama dikembalikan; periksa stok aktual melalui GET detail, jurnal operasi tetap satu, dan tidak ada Outbox baru. Respons retry merupakan snapshot operasi, sehingga stok aktual dapat berbeda bila transaksi lain terjadi setelahnya.
- Broker mati lalu pulih, replay event, publish dari Management UI, DLQ, dan dua subscriber sudah dicoba sebelumnya. Tunjukkan bukti terkait bila diminta; jangan memaksakan semuanya ke empat menit.

## Setelah latihan

Ukur durasi satu sesi dan catat hasil aktual. Jangan menyebut latihan empat menit selesai sebelum benar-benar diukur.
Simpan bukti data sintetis saja. Jangan membuka .env, password, atau token saat berbagi layar.
Pembagian tugas README masih perlu disepakati kelompok. Riwayat commit dan review harus mencerminkan kontribusi sebenarnya.
