# Uji Manual Dead Letter Queue

Acuan: P05 Bab 7.
Data: pesan sintetis yang sengaja tidak lengkap.

Payload:
{"schema_version":1}

Hasil aktual:
- Pesan dikirim ke rantauan.events.
- Routing key: stok.penjualan.v1.
- Consumer menolak payload yang tidak memenuhi kontrak.
- Pesan diteruskan ke reporting.penjualan.dlq.
- Header x-death menunjukkan:
  - queue: reporting.penjualan
  - exchange: rantauan.events
  - reason: rejected
  - count: 1

Setelah Get messages, UI menampilkan 0 messages remaining.
Mode pengambilan tidak tercatat, sehingga keberadaan pesan
sesudah pemeriksaan perlu dibedakan dari bukti header di atas.

Kesimpulan: pesan invalid tidak dicatat sebagai penjualan
dan berhasil diarahkan ke DLQ pada pengujian sebelumnya.
