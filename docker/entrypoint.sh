#!/bin/sh
set -e

# Tunggu database siap. Retry terus tiap 3 detik, gak nebak lama waktu startup MySQL.
until php artisan migrate --force --isolated; do
    echo "Database belum siap, coba lagi 3 detik lagi..."
    sleep 3
done

# --isolated di atas pakai lock, jadi aman kalau container app/scheduler/queue
# kebetulan start bareng dan sama-sama coba migrate.
php artisan config:cache
php artisan view:cache
# route:cache sengaja dilewatin: routes/web.php ada route pakai closure ("/"),
# dan Laravel gak bisa cache route yang isinya closure.

# Perintah artisan di atas jalan sebagai root. Kembalikan kepemilikan folder yang
# ditulis aplikasi ke www-data (user php-fpm), kalau nggak log/cache bisa gagal ditulis.
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
