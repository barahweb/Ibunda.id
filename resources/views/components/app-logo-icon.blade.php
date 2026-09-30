@php $maskId = 'logo-check-mask-'.md5(microtime(true).random_int(0, PHP_INT_MAX)); @endphp
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" {{ $attributes }}>
    <mask id="{{ $maskId }}">
        <rect width="40" height="40" rx="10" fill="#ffffff" />
        <path d="M12 20.5l5.5 5.5L29 14" fill="none" stroke="#000000" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round" />
    </mask>
    <rect width="40" height="40" rx="10" fill="currentColor" mask="url(#{{ $maskId }})" />
</svg>
