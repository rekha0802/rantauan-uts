# Uji Urutan Event Setelah Broker Pulih

Tanggal: 27 Oktober 2026.
Acuan: P06 Bab 7.3.
Data: transaksi sintetis.
Konfigurasi uji: satu relay dan satu consumer Reporting.

## Kondisi awal

- Stok: 15.
- Outbox tertunda: 0.
- Jurnal: 4.
- Inbox: 4.

## Pelaksanaan dan hasil aktual

1. RabbitMQ UTS dihentikan.
2. Tiga penjualan dikirim berurutan: 1, 2, dan 3 unit.
3. Respons stok berurutan: 14, 12, dan 9.
4. Saat broker mati, Outbox menyimpan 3 pesan tertunda.
5. Broker dinyalakan dan ping berhasil.
6. Relay menampilkan: 3 pesan terkirim.
7. Reporting menampilkan tiga event Dicatat.
8. Query jurnal untuk tiga operation_id pengujian,
   diurutkan berdasarkan id jurnal, menghasilkan [1, 2, 3].
9. Total jurnal dan inbox masing-masing menjadi 7.

## Kesimpulan

Pada konfigurasi pengujian ini, ketiga event diproses
sesuai urutan. Hasil tidak membuktikan urutan global
untuk beberapa relay atau consumer paralel.
