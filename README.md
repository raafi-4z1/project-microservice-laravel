## Langkah-langkah

Pertama clone repository

```sh
git clone https://github.com/raafi-4z1/project-microservice-laravel.git
```
### API Gateway
Setting Gateway.
1. Buka terminal dan masuk ke folder Gateway
2. Jalankan perintah composer

```sh
composer install
```
3. Copy file .env.example dan simpan sebagai file .env di folder root yang sama
4. Buka dan edit file .env

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=YOURDATABASE
DB_USERNAME=USERNAME
DB_PASSWORD=PASSWORD
```
5. Jalankan perintah untuk generate sebuah unique key untuk aplikasi

```sh
php artisan key:generate
```
6.  Selanjutnya migrasi database. Tunggu sampai prosesnya selesai

```sh
php artisan migrate
```
7. Untuk men-setting passport jalankan perintah untuk membuat encryption keys dan password grant client

```sh
php artisan passport:install
```
8. Buat sebuah virtual host untuk Gateway anda jika dilakukan di local machine atau sebuah subdomain jika dilakukan di live server
9. Buka file .env pada Gateway dan tambahkan baris untuk microservice authentication

```sh
CLASS_SERVICE_BASE_URL=http://classmicroservices.test
CLASS_SERVICE_SECRET=base64:uUTtmBL1ZmUdIOtGSx+2uWQuYg1MdGWnyZb1AC4W/go=

MAPEL_SERVICE_BASE_URL=http://mapelservice.test
MAPEL_SERVICE_SECRET=base64:tV2U1JsoTvOqIgaDJXb1aHrmAhnGW0uvs/tY9h4xuCE=
```

### Class Microservice
Setting Class Microservice.
1. Buka terminal dan masuk ke folder Class Microservice
2. Jalankan perintah composer

```sh
composer install
```
3. Copy file .env.example dan simpan sebagai file .env di folder root yang sama
4. Buka dan edit file .env

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=YOURDATABASE
DB_USERNAME=USERNAME
DB_PASSWORD=PASSWORD
```
5. Jalankan perintah untuk generate sebuah unique key untuk aplikasi

```sh
php artisan key:generate
```
6.  Selanjutnya migrasi database. Tunggu sampai prosesnya selesai

```sh
php artisan migrate
```
7. Buat sebuah virtual host untuk Class Microservice anda jika dilakukan di local machine atau sebuah subdomain jika dilakukan di live server. Pastikan setting url virtual host di file .env pada Gateway.
8. Buka file .env pada Class Microservice dan tambahkan baris untuk microservice authentication.

```sh
ACCEPTED_SECRETS=base64:uUTtmBL1ZmUdIOtGSx+2uWQuYg1MdGWnyZb1AC4W/go=
```

### Mapel Service
Setting Mapel Service.
1. Buka terminal dan masuk ke folder Mapel Service
2. Jalankan perintah composer

```sh
composer install
```
3. Copy file .env.example dan simpan sebagai file .env di folder root yang sama
4. Buka dan edit file .env

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=YOURDATABASE
DB_USERNAME=USERNAME
DB_PASSWORD=PASSWORD
```
5. Jalankan perintah untuk generate sebuah unique key untuk aplikasi

```sh
php artisan key:generate
```
6.  Selanjutnya migrasi database. Tunggu sampai prosesnya selesai

```sh
php artisan migrate
```
7. Buat sebuah virtual host untuk Mapel Service anda jika dilakukan di local machine atau sebuah subdomain jika dilakukan di live server. Pastikan setting url virtual host di file .env pada Gateway.
8. Buka file .env pada Mapel Service dan tambahkan baris untuk microservice authentication.

```sh
ACCEPTED_SECRETS=base64:tV2U1JsoTvOqIgaDJXb1aHrmAhnGW0uvs/tY9h4xuCE=
```

### Catatan
Buatlah value ACCEPTED_SECRETS berbeda-beda pada setiap microservice dan value ..._SERVICE_SECRET merupakan value ACCEPTED_SECRETS dari microservice yang ingin dihubungkan.

## Referensi
Github: https://github.com/ismail17719/apigateway-based-microservices-in-laravel-and-lumen
