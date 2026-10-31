# Menjalankan proyek dari nol

Panduan untuk checkout baru pada Ubuntu WSL2. Untuk lingkungan yang sudah
berjalan, gunakan bagian Menjalankan lingkungan yang sudah terpasang di README.
Mengubah template tidak memerlukan mengganti .env aktif yang sudah berfungsi.

## 1. Prasyarat

- Ubuntu WSL2 dengan Git dan Docker Desktop WSL Integration aktif.
- docker version menampilkan Client dan Server.
- Port host 8000, 8001, 3309, 3310, 5175, 5176, 5673, 15673, dan 8082 bila
  memakai Swagger, tersedia atau disesuaikan bersama dokumentasinya.
- Koneksi internet untuk mengunduh image/dependensi.
- Repository kelompok: https://github.com/rekha0802/rantauan-uts

## 2. Dapatkan source

Pada komputer/checkout baru, ganti https://github.com/rekha0802/rantauan-uts.git dengan URL sebenarnya:

~~~bash
git clone https://github.com/rekha0802/rantauan-uts.git ~/rantauan-uts
cd ~/rantauan-uts
~~~

Jangan menimpa folder pengembangan yang sudah ada. Clone tidak menyertakan
.env, vendor, node_modules atau dataset owner. Jika menguji pada komputer
yang sama, gunakan folder, COMPOSE_PROJECT_NAME dan port terpisah.

## 3. Siapkan .env lokal sebelum menyalakan container

~~~bash
cd ~/rantauan-uts
for folder in inventory-service reporting-service; do
    if [ ! -f "$folder/.env" ]; then
        cp "$folder/.env.example" "$folder/.env"
    fi
done
~~~

Edit kedua file .env lokal dengan editor. Isi field kosong berikut:

- DB_PASSWORD Inventory: password baru untuk database lokal Inventory.
- DB_PASSWORD Reporting: password baru untuk database lokal Reporting.
- RABBITMQ_PASSWORD: satu password broker lokal yang sama di kedua layanan.

APP_KEY akan dihasilkan oleh Artisan pada langkah 5. Jangan memasukkan nilai
password atau key ke .env.example, screenshot, log, README atau Git.

Template sudah menentukan DB_CONNECTION=mysql dan host database mysql, dengan
DB_PORT=3306 di dalam container. Forward port 3309/3310 hanya untuk akses dari
host, bukan nilai DB_PORT aplikasi.

Inventory memakai host broker rabbitmq:5672. Reporting mengakses broker Inventory
melalui host.docker.internal:5673 karena kedua proyek mempunyai jaringan berbeda.

Untuk instalasi dengan volume lama, gunakan kredensial yang cocok dengan isi
volume tersebut. Mengedit .env saja tidak mengubah password akun yang sudah
tersimpan di MySQL/RabbitMQ. Perubahan password akun adalah prosedur terpisah.

## 4. Instal dependensi ketika vendor belum tersedia

Jalankan blok berikut hanya pada setup baru yang belum mempunyai dependensi.
Image PHP/Composer dipakai untuk bootstrap; runtime aplikasi tetap Sail PHP 8.5.

~~~bash
cd ~/rantauan-uts/inventory-service
docker run --rm \
  -u "$(id -u):$(id -g)" \
  -v "$(pwd):/var/www/html" \
  -w /var/www/html \
  laravelsail/php84-composer:latest \
  composer install --ignore-platform-reqs
~~~

~~~bash
cd ~/rantauan-uts/reporting-service
docker run --rm \
  -u "$(id -u):$(id -g)" \
  -v "$(pwd):/var/www/html" \
  -w /var/www/html \
  laravelsail/php84-composer:latest \
  composer install --ignore-platform-reqs
~~~

Gunakan composer install, bukan composer update, agar composer.lock dipakai.
Pemeriksaan platform runtime dilakukan di container aplikasi pada langkah 5;
flag bootstrap di atas bukan bukti kecocokan semua dependensi pada runtime.

## 5. Nyalakan Inventory dan siapkan database

~~~bash
cd ~/rantauan-uts/inventory-service
./vendor/bin/sail config --quiet
./vendor/bin/sail up -d
./vendor/bin/sail ps
~~~

Tunggu MySQL healthy. Pada setup baru, buat APP_KEY jika masih kosong:

~~~bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan config:clear
./vendor/bin/sail composer check-platform-reqs
./vendor/bin/sail artisan migrate
./vendor/bin/sail exec rabbitmq rabbitmq-diagnostics -q ping
./vendor/bin/sail artisan rabbitmq:siapkan
~~~

Jika broker belum siap, tunggu lalu ulangi ping sebelum penyiapan topologi.
Tidak perlu membuat ulang key pada setiap startup aplikasi.

## 6. Nyalakan Reporting

~~~bash
cd ~/rantauan-uts/reporting-service
./vendor/bin/sail up -d
./vendor/bin/sail ps
~~~

Tunggu MySQL healthy. Pada setup baru, buat APP_KEY jika masih kosong:

~~~bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan config:clear
./vendor/bin/sail composer check-platform-reqs
./vendor/bin/sail artisan migrate
~~~

Sekarang ikuti perintah consumer dan relay pada README. Penyiapan queue/binding
Inventory harus selesai sebelum consumer Reporting dan publish event dijalankan.

## 7. Periksa API dan jalankan test

~~~bash
curl -i http://localhost:8000/api/v1/produk -H 'Accept: application/json'
~~~

Database baru diharapkan mengembalikan 200 dengan data kosong. Produk uji yang
dipakai pada pengembangan tidak otomatis ada pada instalasi baru; belum ada
seeder produk proyek. Buat produk sintetis melalui POST sebelum demo stok.

~~~bash
cd ~/rantauan-uts/inventory-service
./vendor/bin/sail artisan test
cd ~/rantauan-uts/reporting-service
./vendor/bin/sail artisan test
~~~

Feature test menyiapkan database SQLite di memori dan tidak mengisi database
MySQL aplikasi. Test relay memakai mock publisher, bukan bukti pengiriman nyata.

## 8. Spectral dan Swagger UI

Jalankan lint dari Inventory:

~~~bash
cd ~/rantauan-uts/inventory-service
docker run --rm -v "$(pwd):/work" -w /work \
  stoplight/spectral lint openapi.yaml
~~~

Untuk Swagger, gunakan container yang ada atau buat jika belum tersedia:

~~~bash
if docker container inspect rantauan-swagger >/dev/null 2>&1; then
    docker start rantauan-swagger
else
    docker run --rm -d \
      --name rantauan-swagger \
      -p 8082:8080 \
      -e SWAGGER_JSON=/spec/openapi.yaml \
      -v "$(pwd):/spec:ro" \
      swaggerapi/swagger-ui
fi
~~~

Buka http://localhost:8082. Server API pada kontrak adalah
http://localhost:8000/api. Mintalah anggota selain penulis kontrak melakukan
review dan mencatat hasil nyata; jangan mengisi nama/komentar review fiktif.

## 9. Status verifikasi panduan

Pada 31 Oktober 2026, clone bersih pada komputer Ubuntu WSL2 yang sama
berhasil diuji dengan nama proyek Compose, volume dan port terpisah.
Instalasi dependensi, pemeriksaan platform, migrasi, API, 36 test dan
alur event sampai jurnal berhasil. Lihat TESTING.md untuk rinciannya.
Pengujian pada komputer kedua belum dilakukan.

Referensi umum Sail: https://laravel.com/docs/13.x/sail
