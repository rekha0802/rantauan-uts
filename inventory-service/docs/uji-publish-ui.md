# Uji Publish melalui RabbitMQ Management UI

Acuan: P05 Bab 7.
Data: pesan sintetis khusus pengujian consumer.

Jalur:
Management UI → rantauan.events → reporting.penjualan
→ consumer Reporting.

Hasil aktual terminal:
- Publish pertama: Dicatat.
- Publish ulang dengan message_id dan payload sama: Dilewati.

Message ID:
bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb

Produk uji:
UJI-001

Publish langsung dari UI tidak melewati Inventory atau Outbox
dan tidak mengubah stok Inventory. Catatan UJI-001 tidak boleh
dianggap transaksi owner atau dipakai untuk rekonsiliasi stok
produk Inventory.

## Verifikasi database

Hasil aktual:
- jurnal_event_ui: 1
- inbox_event_ui: 1

Pengiriman ulang pesan yang sama tidak menambah catatan.
