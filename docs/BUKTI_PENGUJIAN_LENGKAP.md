# Bukti Pengujian Lengkap Checkpoint 1

RENR Project — diperbarui 2 November 2026.

[Unduh PDF lampiran](BUKTI_PENGUJIAN_LENGKAP.pdf)

## 1. Cara membaca dokumen ini

Dokumen ini menghubungkan fitur yang sudah dibuat, tempat mengujinya di Swagger, implementasi dan test otomatis. Tidak semua fitur adalah endpoint baru. `limit` adalah query pada daftar produk; `sku` adalah path parameter detail/stok; restok, koreksi dan retry memakai endpoint penyesuaian stok yang sama. Relay, consumer, Outbox, Monitoring dan DLQ diperiksa melalui terminal/database/RabbitMQ.

Status bukti:
- **Dijalankan ulang 2 November:** 29 test Inventory (179 assertions), 7 test Reporting (14 assertions), Spectral, enam request GET dan file kontrak yang dilayani Swagger.
- **Bukti manual terdahulu:** alur penjualan sampai jurnal, dua subscriber, replay, pesan invalid ke DLQ, broker mati/pulih dan urutan tiga event. Lihat TESTING.md dan catatan skenario. Tidak diklaim dijalankan ulang seluruhnya pada pembaruan dokumentasi ini.
- **Contoh** pada Swagger bukan respons aktual sampai Execute dijalankan. Respons di `bukti/http-api-20261102.json` adalah hasil HTTP aktual, sedangkan contoh stok/waktu pada OpenAPI hanya ilustrasi.

Semua data demonstrasi sintetis. Import/mapping otomatis, pembatalan/retur otomatis, autentikasi bisnis, dashboard dan pendapatan belum diimplementasikan. POST penyesuaian stok tidak membatalkan jurnal penjualan lama.

## 2. Bukti aktual yang disimpan

| Pemeriksaan | Hasil aktual | Berkas |
|---|---|---|
| Inventory | 29 passed / 179 assertions | [Output test](bukti/inventory-tests-20261102.txt) |
| Reporting | 7 passed / 14 assertions | [Output test](bukti/reporting-tests-20261102.txt) |
| Kontrak 0.4.0 | Tidak ada error Spectral | [Output lint](bukti/spectral-20261102.txt) |
| HTTP daftar limit=1 | 200, paling banyak satu produk | [Respons aktual](bukti/http-api-20261102.json) |
| HTTP limit 0 / 101 / teks | 422, application/problem+json | [Respons aktual](bukti/http-api-20261102.json) |
| HTTP SKU tidak ada | 404, application/problem+json | [Respons aktual](bukti/http-api-20261102.json) |
| HTTP SKU tersedia | 200, SKU sesuai hasil daftar | [Respons aktual](bukti/http-api-20261102.json) |
| Swagger | /openapi.yaml melayani versi 0.4.0 | [Pemeriksaan file](bukti/swagger-spec-20261102.txt) |

Dua ExampleTest bawaan framework (satu per layanan) termasuk total 36 test. Test relay memakai mock publisher; bukti broker nyata ada pada pengujian manual, bukan disimpulkan dari mock.

## 3. Matriks fitur REST dan test

Awalan URL semua operasi: `http://localhost:8000/api`.

| ID | Fitur / input | Hasil / efek | Tempat Swagger | Test terkait di Inventory |
|---|---|---|---|---|
| API-01 | Daftar kosong | 200, data [] | GET /v1/produk | ProdukApiTest |
| API-02 | Tanpa limit | Seluruh produk urut SKU | GET /v1/produk | LimitProdukApiTest |
| API-03 | limit=1 | Maksimal 1 produk, tetap urut SKU | GET /v1/produk > Parameters > limit | LimitProdukApiTest |
| API-04 | limit=0,101,-1,1.5,teks | 422 errors.limit | GET /v1/produk; curl untuk input diblokir UI | LimitProdukApiTest |
| API-05 | Buat produk tanpa stok | 201, stok default 0, Location | POST /v1/produk | TambahProdukApiTest |
| API-06 | Nama wajib hilang | 422 errors.nama | POST /v1/produk | TambahProdukApiTest |
| API-07 | SKU format ABC-123, unik | Format/duplikat salah ditolak 422 | POST /v1/produk | TambahProdukRequest; contoh manual terdahulu SKU duplikat |
| API-08 | Nama 1–120, harga/stok integer >=0 | Input salah ditolak 422 | POST /v1/produk > Schema | TambahProdukRequest; tidak semua batas punya test khusus |
| API-09 | Detail SKU tersedia | 200, satu produk | GET /v1/produk/{sku} > Parameters > sku | DetailProdukApiTest |
| API-10 | Detail SKU tidak ada | 404 Problem Details | GET /v1/produk/{sku} | DetailProdukApiTest |
| API-11 | Penjualan -2, awal 20 | 200, stok 18 | POST penyesuaian-stok > contoh Penjualan | StokApiTest |
| API-12 | Penjualan melebihi stok | 409, tanpa stok/ledger/event baru | POST penyesuaian-stok > contoh StokKurang | StokApiTest, OutboxTest |
| API-13 | Perubahan 0 | 422, stok tetap | POST penyesuaian-stok > uraian skenario 422 | StokApiTest |
| API-14 | operation_id hilang | 422, tanpa efek | POST penyesuaian-stok > uraian skenario 422 | KontrakStokApiTest |
| API-15 | SKU stok tidak ada | 404, tanpa ledger/outbox | POST penyesuaian-stok > Parameters > sku | KontrakStokApiTest |
| API-16 | ID dan isi sama | 200 snapshot hasil pertama, tanpa efek baru | POST penyesuaian-stok, Execute ulang | StokApiTest, OutboxTest |
| API-17 | ID sama, isi berbeda | 409 operation-id-konflik | POST penyesuaian-stok > contoh KonflikId | StokApiTest |
| API-18 | Penjualan positif | 422, tanpa efek | POST penyesuaian-stok > uraian skenario 422 | ArahStokApiTest |
| API-19 | Restok negatif | 422, tanpa efek | POST penyesuaian-stok > uraian skenario 422 | ArahStokApiTest |
| API-20 | Restok +5, awal 20 | 200, stok 25, routing stok.restok.v1 | POST penyesuaian-stok > contoh Restok | ArahStokApiTest |
| API-21 | Koreksi positif/negatif | Stok berubah selama akhir >=0; bukan jurnal penjualan | POST penyesuaian-stok > contoh Koreksi | Implementasi tersedia; belum ada test khusus koreksi |

Nama file pada kolom terakhir berada di `inventory-service/tests/Feature/` kecuali Request di `app/Http/Requests/`. Tidak mengklaim test khusus untuk semua kemungkinan validasi yang belum ditulis.

## 4. Langkah uji Swagger yang dapat diikuti

1. Buka http://localhost:8082 dan tekan Ctrl+F5. Pastikan judul menunjukkan versi **0.4.0**.
2. Buka GET /v1/produk. Klik Try it out, isi `limit=1`, Execute. Lihat Request URL memuat `?limit=1` dan paling banyak satu hasil.
3. Limit hanya tampak saat operasi dibuka. Untuk nilai invalid yang diblokir Swagger, gunakan curl pada bagian 5.
4. Buka POST /v1/produk. Pilih contoh ProdukLengkap, gunakan SKU baru format ABC-123, Execute. Periksa 201 serta Location. Tanpa stok menghasilkan 0; kirim ulang SKU yang sama untuk 422.
5. Buka GET /v1/produk/{sku}. Isi SKU yang dibuat, Execute untuk 200. Isi SKU yang belum ada untuk 404.
6. Buka POST /v1/produk/{sku}/penyesuaian-stok. Isi SKU produk uji. Pilih contoh Penjualan/Restok/Koreksi pada dropdown Examples di request body.
7. Gunakan UUID baru untuk operasi baru. Pertahankan UUID saat retry. Contoh Penjualan -2 mengasumsikan stok awal 20; catat stok aktual sebelum dan sesudah.
8. Pilih respons 200/404/409/422 di Responses untuk membaca schema dan contoh masing-masing. Dropdown request dan response berbeda.
9. Ulangi UUID/isi penjualan sukses: 200 snapshot lama. Ubah delta dengan UUID lama: 409. Baca GET detail untuk stok terkini.
10. Untuk uji 422, salin contoh negatif pada description ke Request body. Skema sengaja tetap ketat, tidak dilonggarkan agar contoh invalid lolos lint.

Contoh invalid tercantum sebagai teks skenario, bukan schema examples yang dinyatakan valid. Contoh respons Problem Details tetap schema-valid. Contoh pada kontrak bukan bukti bahwa semua skenario sudah dieksekusi di mesin dosen.

## 5. Reproduksi test dan validasi backend

Terminal Inventory:

```bash
cd ~/rantauan-uts/inventory-service
./vendor/bin/sail artisan test
./vendor/bin/sail artisan test --filter=LimitProdukApiTest
./vendor/bin/sail artisan test --filter=DetailProdukApiTest
./vendor/bin/sail artisan test --filter=StokApiTest
./vendor/bin/sail artisan test --filter=OutboxTest
```

Terminal Reporting:

```bash
cd ~/rantauan-uts/reporting-service
./vendor/bin/sail artisan test
```

Lint kontrak:

```bash
cd ~/rantauan-uts/inventory-service
docker run --rm -v "$(pwd)":/work -w /work stoplight/spectral lint openapi.yaml
```

Limit invalid mencapai backend tanpa validasi Swagger:

```bash
curl -i 'http://localhost:8000/api/v1/produk?limit=0' -H 'Accept: application/json'
curl -i 'http://localhost:8000/api/v1/produk?limit=101' -H 'Accept: application/json'
curl -i 'http://localhost:8000/api/v1/produk?limit=teks' -H 'Accept: application/json'
```

Hasil yang diharapkan: 422, Content-Type application/problem+json, status 422 dan errors.limit. Masukan limit=-1 dan 1.5 juga dicakup test otomatis. Limit bukan pagination; tidak ada parameter page, offset, pencarian nama atau filter SKU pada daftar saat ini.

## 6. Matriks transaksi dan integrasi event

| ID | Perilaku | Bukti |
|---|---|---|
| EVT-01 | Payload event 7 field, UUID dan waktu tetap pada pembacaan ulang | Unit/StokDisesuaikanTest |
| EVT-02 | Penjualan menyimpan satu event tertunda | Feature/OutboxTest |
| EVT-03 | Stok tidak cukup tidak membuat event/ledger | Feature/OutboxTest |
| EVT-04 | Retry tidak membuat event baru | Feature/OutboxTest |
| EVT-05 | Simulasi gagal simpan Outbox: transaksi rollback, stok tetap | Feature/OutboxTest, respons 500 internal; bukan contoh Problem Details |
| EVT-06 | Relay publish lalu menandai dikirim | Feature/RelayOutboxTest, mock publisher |
| EVT-07 | Event telah dikirim tidak dipublish lagi oleh relay | Feature/RelayOutboxTest |
| EVT-08 | Simulasi broker gagal: event tetap tertunda | Feature/RelayOutboxTest |
| EVT-09 | Event penjualan dicatat di jurnal dan inbox | Reporting Feature/CatatPenjualanTest |
| EVT-10 | Event duplikat tidak menambah jurnal | Reporting Feature/CatatPenjualanTest |
| EVT-11 | Payload wajib hilang / penjualan positif ditolak | Reporting Feature/CatatPenjualanTest |
| EVT-12 | Restok tidak dicatat sebagai penjualan | Reporting Feature/CatatPenjualanTest |
| EVT-13 | Publisher/consumer nyata sampai jurnal | Bukti manual historis TESTING.md, demo terbaru pengguna |
| EVT-14 | Dua subscriber memakai queue berbeda | TESTING.md, kontrak-event.md; Monitoring command dalam Reporting |
| EVT-15 | Broker nyata mati/pulih | [uji-broker-mati.md](../inventory-service/docs/uji-broker-mati.md) |
| EVT-16 | Pesan invalid Reporting ke DLQ, x-death rejected | [uji-dlq.md](../inventory-service/docs/uji-dlq.md) |
| EVT-17 | Publish UI dan replay ID sama | [uji-publish-ui.md](../inventory-service/docs/uji-publish-ui.md) |
| EVT-18 | Tiga event satu relay/consumer, jumlah 1,2,3 | [uji-urutan-event.md](../inventory-service/docs/uji-urutan-event.md) |
| ENV-01 | Setup clone baru sampai jurnal pada komputer sama | TESTING.md bagian clone bersih |

Monitoring tidak menyimpan jurnal dan tidak mempunyai DLQ. Publish dari Management UI melewati Inventory/Outbox dan tidak mengubah stok. Jangan merekonsiliasi semua jurnal historis demo dengan stok karena ada fixture UI dan perubahan sebelum Outbox dibuat.

## 7. Bukti manual integrasi yang perlu ditunjukkan saat demo

- Consumer Reporting pada terminal tersendiri: `./vendor/bin/sail artisan reporting:konsumsi` dari folder Reporting.
- Satu penjualan valid di Swagger, UUID baru: stok berkurang sesuai delta.
- Relay pada folder Inventory: `./vendor/bin/sail artisan outbox:relay`.
- Terminal consumer menampilkan Dicatat: event.
- Periksa jurnal berdasarkan operation_id yang tepat: satu catatan dan jumlah sesuai transaksi.
- Ulangi request dengan ID/isi sama: stok dan jumlah jurnal operasi tidak bertambah lagi.
- Catat UUID, message_id, SKU, stok awal/akhir dan hasil aktual bila menambah bukti baru. Jangan menggunakan placeholder sebagai UUID atau mengisi hasil sebelum test dilakukan.

Broker mati/pulih dan DLQ menjadi cadangan jika diminta. Jangan mematikan broker saat demo orang lain berlangsung. Jangan menghapus queue/data untuk mengulang pengujian.

## 8. Yang belum termasuk bukti

Impor data owner, mapping otomatis, integrasi API resmi marketplace, status batal/retur otomatis, pendapatan, UI bisnis, autentikasi bisnis, cluster, konkurensi banyak relay/consumer, urutan global pesan dan instalasi komputer kedua belum dibuktikan oleh pengujian ini. Semua fitur tersebut tidak ditambahkan sebagai endpoint fiktif di Swagger.
