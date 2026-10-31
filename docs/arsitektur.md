# Arsitektur Proyek Rantau.an Hijab

Inventory dan Reporting sudah berjalan.
Import Service masih direncanakan.

~~~mermaid
flowchart TB
    A["Postman / Swagger"] -->|"REST: berjalan"| I["Inventory: berjalan"]
    F["File simulasi"] -.-> M["Import: direncanakan"]
    M -.->|"REST: direncanakan"| I
    I --> D1[("DB Inventory: produk, operasi, outbox")]
    D1 --> R["Relay Outbox"]
    R -->|"AMQP"| E["RabbitMQ: rantauan.events"]
    E -->|"reporting.penjualan"| S["Reporting: berjalan"]
    S --> D2[("DB Reporting: inbox, jurnal")]
    E -->|"monitoring.stok"| N["Consumer Monitoring dalam Reporting"]
    S -.->|"Penolakan tanpa requeue"| X["RabbitMQ: rantauan.dlx"]
    X --> Q["reporting.penjualan.dlq"]
~~~

## Keterangan

- Garis putus-putus Import menunjukkan hubungan yang direncanakan.
- Penolakan tanpa requeue menunjukkan jalur pesan gagal.
- Monitoring adalah command dalam Reporting, bukan layanan ketiga.
- Tiga layanan aplikasi yang dirancang: Import, Inventory, Reporting.
- Transaksi demo menggunakan data sintetis.
- Impor file owner dan integrasi API marketplace belum dibuat.
- Reporting mencatat kuantitas, bukan omzet owner.
