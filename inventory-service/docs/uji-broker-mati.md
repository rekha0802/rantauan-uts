# Uji Manual Broker Mati dan Pemulihan

Tanggal: 27 Oktober 2026.
Acuan: P06 Bab 7.1.
Data: produk dan transaksi sintetis.

| Tahap | Hasil aktual |
|---|---|
| Sebelum pengujian | Stok 16, Outbox tertunda 0 |
| RabbitMQ dihentikan | Broker UTS berhenti |
| Penjualan 1 unit | HTTP 200, stok 15 |
| Relay saat broker mati | AMQPIOException; pesan tetap tertunda |
| Pemeriksaan saat broker mati | Stok 15, Outbox tertunda 1 |
| Broker dinyalakan kembali | Ping succeeded |
| Relay setelah pemulihan | 1 pesan terkirim |
| Consumer Reporting | Dicatat: event bb687b6d-df40-4936-8893-35b9734b4a5d |
| Pemeriksaan akhir | Stok 15, Outbox tertunda 0 |

Kesimpulan: pada skenario ini perubahan stok tetap berhasil
ketika broker mati. Event tersimpan di Outbox dan berhasil
diproses Reporting setelah broker pulih. Pengiriman event
tidak mengurangi stok untuk kedua kalinya.
