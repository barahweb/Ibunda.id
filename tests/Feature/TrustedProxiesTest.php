<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;

afterEach(fn () => TrustProxies::flushState());

function redirectLocationBehindProxy(string $trusted, string $proxyIp): ?string
{
    config(['app.trusted_proxies' => $trusted]);
    (new AppServiceProvider(app()))->boot();

    return test()->withServerVariables([
        'REMOTE_ADDR' => $proxyIp,
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'demo.test',
    ])->get('http://demo.test/dashboard')->headers->get('Location');
}

test('a trusted proxy makes generated urls https', function (string $trusted) {
    expect(redirectLocationBehindProxy($trusted, '10.0.0.2'))->toStartWith('https://demo.test');
})->with(['wildcard' => '*', 'single ip' => '10.0.0.2', 'list with spaces' => '10.0.0.1, 10.0.0.2']);

test('an untrusted or unset proxy list keeps http so forwarded headers cannot be spoofed', function (string $trusted) {
    expect(redirectLocationBehindProxy($trusted, '10.0.0.2'))->toStartWith('http://demo.test');
})->with(['other ip' => '10.9.9.9', 'empty' => '']);
