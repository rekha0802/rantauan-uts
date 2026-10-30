# Panduan Demo Checkpoint 1

Target sekitar empat menit, mengikuti P07. Gunakan data sintetis.
Ini panduan yang disiapkan; seluruh urutan demo ini belum dilatih dalam satu sesi.

## Sebelum giliran: persiapan di luar empat menit

1. Jalankan Inventory, Reporting dan RabbitMQ melalui Sail; pastikan MySQL
   healthy dan broker ping berhasil. Ikuti README untuk startup.
2. Jalankan rabbitmq:siapkan dari Inventory.
3. Buka diagram arsitektur dan Swagger UI. Tidak mengunduh image saat giliran.
4. Hentikan relay kontinu lain agar hanya satu relay aktif saat demo.
5. Pastikan tidak ada event lama tertunda yang akan ikut terkirim saat demo.
   Jika ada, proses dan jelaskan terlebih dahulu; jangan menghapusnya sembarangan.
6. Siapkan produk DMO-001 berikut. Produk ini sengaja terpisah dari latihan HJB-001.
7. Pastikan stok DMO-001 minimal 1 dan di bawah 1000000 sebelum latihan ini.
8. Semua anggota hadir dan membagi bagian penjelasan yang benar-benar dipahami.

Periksa produk demo dari terminal Inventory:

~~~bash
cd ~/rantauan-uts/inventory-service
curl -i http://localhost:8000/api/v1/produk/DMO-001 \
  -H 'Accept: application/json'
~~~

Hanya jika produk belum ada (404), buat sekali pada persiapan:

~~~bash
curl -i -X POST http://localhost:8000/api/v1/produk \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{"sku":"DMO-001","nama":"Produk Simulasi Demo","harga":45000,"stok":20}'
~~~

Jika SKU sudah ada, gunakan hasil pemeriksaan stoknya. Jangan mengharapkan
POST berulang menghasilkan 201; SKU duplikat ditolak 422. Bila stok habis,
lakukan restok positif dengan operation_id baru lewat API sebelum giliran,
kirim event persiapannya dan pastikan tidak ada Outbox tertunda.

Periksa pesan tertunda:

~~~bash
./vendor/bin/sail artisan tinker --execute="dump(App\Models\Outbox::whereNull('dikirim_pada')->count());"
~~~

### Terminal A: Inventory

~~~bash
cd ~/rantauan-uts/inventory-service
~~~

### Terminal B: consumer Reporting, biarkan berjalan

~~~bash
cd ~/rantauan-uts/reporting-service
./vendor/bin/sail artisan reporting:konsumsi
~~~

### Terminal C: pemeriksaan Reporting

~~~bash
cd ~/rantauan-uts/reporting-service
./vendor/bin/sail artisan tinker --execute="dump(DB::table('jurnal_penjualan')->where('sku_internal', 'DMO-001')->count());"
~~~

Catat jumlah sebelum demo. Consumer Monitoring boleh dibuka pada terminal
tambahan jika ingin bukti dua subscriber, tetapi itu bukan pengganti tiga
layanan aplikasi pada diagram. Jalur utama demo cukup Reporting.

## Menit 0–1: diagram dan batas sistem

Contoh penjelasan:

> Kami merancang Import, Inventory dan Reporting. Inventory serta Reporting
> sudah berjalan. Import masih direncanakan. REST Inventory diuji melalui curl
> atau Swagger. Perubahan stok dan event disimpan bersama di Outbox, lalu relay
> menerbitkan event ke RabbitMQ. Reporting memakai inbox untuk mencegah duplikasi.

Jelaskan garis rencana dan jalur yang sudah berjalan. Jangan mengklaim impor
owner, API marketplace, atau dashboard telah diimplementasikan.

## Menit 1–3: request, test, event

### A. Satu request berhasil, Terminal A

~~~bash
curl -i http://localhost:8000/api/v1/produk/DMO-001 \
  -H 'Accept: application/json'
~~~

Harapan: 200 dan produk sintetis. Cocokkan dengan OpenAPI.

### B. Satu galat bisnis, Terminal A

Perintah pembuat UUID berikut memuat autoloader, bukan password.
Gunakan ID baru agar tidak berbenturan dengan riwayat latihan.

~~~bash
gagal_id=$(./vendor/bin/sail php -r 'require "vendor/autoload.php"; echo Illuminate\Support\Str::uuid();')
curl -i -X POST \
  http://localhost:8000/api/v1/produk/DMO-001/penyesuaian-stok \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d "{\"operation_id\":\"$gagal_id\",\"perubahan\":-1000000,\"alasan\":\"penjualan\"}"
~~~

Harapan: 409 dengan application/problem+json. Request ini tidak menghasilkan
perubahan stok atau event. Besaran pengurangan bergantung kondisi persiapan:
stok harus lebih kecil dari 1000000 agar benar-benar tidak cukup.

### C. Jalankan feature test, Terminal A

~~~bash
./vendor/bin/sail artisan test
~~~

Hasil terakhir Inventory: 29 passed, 179 assertions. Gunakan hasil aktual
yang muncul pada hari demo. Bila diminta, jalankan juga seluruh test Reporting
di Terminal C; hasil terakhir 7 passed, 14 assertions.

### D. Kirim satu perubahan stok dan event, Terminal A

~~~bash
demo_id=$(./vendor/bin/sail php -r 'require "vendor/autoload.php"; echo Illuminate\Support\Str::uuid();')
curl -i -X POST \
  http://localhost:8000/api/v1/produk/DMO-001/penyesuaian-stok \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d "{\"operation_id\":\"$demo_id\",\"perubahan\":-1,\"alasan\":\"penjualan\"}"
~~~

Jika respons 200, jalankan relay:

~~~bash
./vendor/bin/sail artisan outbox:relay
~~~

Harapan: 1 pesan terkirim jika persiapan memastikan tidak ada backlog.
Terminal B menampilkan Dicatat: event diikuti UUID.

Periksa Terminal C:

~~~bash
./vendor/bin/sail artisan tinker --execute="dump(DB::table('jurnal_penjualan')->where('sku_internal', 'DMO-001')->count());"
~~~

Jumlah jurnal demo seharusnya bertambah satu dari catatan sebelum demo.
Request dan relay tidak dijalankan ulang dengan UUID baru untuk mengatasi
ketidakpastian respons; periksa hasil sebelum mengulang efek bisnis.

## Menit 3–4: tanya jawab setiap anggota

Pertanyaan latihan, bukan bocoran pertanyaan dosen:

- Apa fungsi route, controller, model dan Resource pada request tadi?
- Mengapa stok tidak cukup menghasilkan 409 dan field invalid 422?
- Mengapa update stok dan pencatatan Outbox harus satu transaksi?
- Apa perbedaan operation_id dan message_id?
- Kapan consumer mengirim ack dan kapan pesan menuju DLQ?
- Mengapa dua subscriber memakai queue berbeda?
- Mengapa dataset owner belum dipakai sebagai transaksi demo?
- Bagian mana yang sudah berjalan dan mana yang belum dibuat?

Jawaban setiap anggota harus sesuai pekerjaan dan pemahaman sebenarnya.
Gunakan riwayat commit dan catatan review yang nyata, bukan pembagian fiktif.

## Setelah latihan

Catat durasi aktual dan hasil. Jika melebihi empat menit, ringkas penjelasan
dan siapkan terminal; jangan menghapus bukti atau mengaku test telah dijalankan.
Simpan bukti hanya dari data sintetis. Password dan .env tidak dibuka saat
berbagi layar. Skenario broker mati, replay, UI publish dan DLQ dapat ditunjukkan
sebagai bukti tambahan jika diminta, bukan dipaksakan semua ke empat menit.
