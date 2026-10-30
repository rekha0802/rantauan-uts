# Kontrak Event StokDisesuaikan

Status: rancangan versi 1, belum diimplementasikan.
Data pengembangan: DUMMY/SIMULASI.

## Jalur pesan

- Nama event: StokDisesuaikan
- Versi payload: 1
- Publisher: Inventory Service
- Consumer: Sales Reporting Service
- Exchange: rantauan.events
- Jenis exchange: topic
- Routing key:
  - stok.penjualan.v1
  - stok.restok.v1
  - stok.koreksi.v1
- Queue Reporting: reporting.penjualan
- Binding Reporting: stok.penjualan.v1
- Dead letter exchange: rantauan.dlx
- Dead letter queue: reporting.penjualan.dlq

## Properti AMQP

- message_id: UUID unik untuk satu event.
- type: StokDisesuaikan.
- content_type: application/json.
- delivery_mode: persistent.
- Exchange dan queue bersifat durable.
- Pengiriman ulang event yang sama memakai message_id yang sama.

## Payload

| Field | Tipe | Makna |
|---|---|---|
| schema_version | integer, nilai 1 | Versi payload |
| operation_id | string UUID | Identitas operasi stok |
| sku_internal | string | SKU produk internal |
| perubahan | integer nonnol | Penambahan atau pengurangan stok |
| alasan | enum: penjualan, restok, koreksi | Alasan perubahan |
| stok_sesudah | integer >= 0 | Stok setelah operasi |
| terjadi_pada | string ISO 8601 dengan offset | Waktu perubahan stok |

## Aturan

1. Operasi stok yang ditolak tidak menghasilkan event.
2. Pengulangan operation_id dengan payload sama tidak membuat event baru.
3. Operation_id yang sama dengan payload berbeda menghasilkan konflik.
4. Reporting memproses hanya event penjualan dengan perubahan negatif.
5. Restok dan koreksi tidak dihitung sebagai penjualan.
6. Payload tidak memuat identitas pembeli, nomor pesanan asli,
   alamat, telepon, password, atau harga owner.
7. Reporting awal merekap kuantitas; laporan uang belum termasuk.

## Consumer dan kegagalan

- Consumer memakai manual ack setelah transaksi jurnal dan inbox berhasil.
- message_id unik pada inbox mencegah pencatatan ganda.
- JSON/payload tidak valid ditolak tanpa requeue menuju DLQ.
- Gangguan sementara dicoba ulang secara terbatas.
- Queue dan binding disiapkan sebelum event diterbitkan.
- Log hanya mencatat metadata yang diperlukan, bukan body pesan.

## Konsistensi

Target rancangan: perubahan stok dan pencatatan event memakai
Transactional Outbox dalam transaksi database yang sama.

Outbox disarankan untuk proyek kelompok pada P06, bukan kewajiban.
Manfaatnya menghindari perubahan stok tanpa catatan event ketika
broker mati. Biayanya tabel tambahan, relay, dan pengujian kegagalan.

Pengiriman dapat berulang (at-least-once), sehingga consumer tetap
harus idempoten. Implementasi dan hasil pengujian belum tersedia.
