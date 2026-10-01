<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureTrustedProxies();
    }

    /**
     * Di belakang reverse proxy HTTPS (Caddy, Cloudflare, nginx host), Laravel harus percaya
     * header X-Forwarded-* biar URL yang dibuat tetap https. Dibaca dari config (bukan env()
     * langsung) biar tetap jalan waktu config di-cache. Isi "*" atau daftar IP pakai koma.
     */
    private function configureTrustedProxies(): void
    {
        $proxies = trim((string) config('app.trusted_proxies'));

        if ($proxies === '') {
            return;
        }

        TrustProxies::at($proxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $proxies)))));
    }
}
