{{-- Nebula theme: favicon + per-site CSS variables (logo, login background, panel name) --}}
@php
    $nb = class_exists(\Pterodactyl\Http\Controllers\Admin\NebulaThemeController::class)
        ? \Pterodactyl\Http\Controllers\Admin\NebulaThemeController::resolved()
        : ['has_logo' => false, 'has_bg' => false, 'show_name' => true, 'logo_height' => 32, 'logo_aspect' => 1, 'bg_overlay' => 0, 'bg_blur' => 0];
    $nbName = trim(preg_replace('/[^\p{L}\p{N} _.\-]/u', '', (string) config('app.name', 'Pterodactyl')));
@endphp
@if($nb['has_logo'])
    <link rel="icon" href="{{ $nb['logo_url'] }}">
    <link rel="shortcut icon" href="{{ $nb['logo_url'] }}">
    <link rel="apple-touch-icon" href="{{ $nb['logo_url'] }}">
@else
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
    <link rel="icon" type="image/png" href="/favicons/favicon-16x16.png" sizes="16x16">
    <link rel="shortcut icon" href="/favicons/favicon.ico">
@endif
<link rel="manifest" href="/favicons/manifest.json">
<meta name="msapplication-config" content="/favicons/browserconfig.xml">
<meta name="theme-color" content="#0b0716">
<style id="nebula-vars">
:root {
    --nb-panel-name: "{{ $nbName !== '' ? $nbName : 'Panel' }}";
@if($nb['has_logo'])
    --nb-logo: url("{{ $nb['logo_url'] }}");
    --nb-logo-size: {{ (int) $nb['logo_height'] }}px;
    --nb-logo-aspect: {{ $nb['logo_aspect'] }};
    --nb-logo-gap: {{ $nb['show_name'] ? '10px' : '0px' }};
    --nb-login-logo-h: {{ min(88, (int) round($nb['logo_height'] * 1.8)) }}px;
    --nb-logo-gap-y: 20px;
@endif
@if(!$nb['show_name'] && $nb['has_logo'])
    --nb-name-size: 0px;
@endif
@if($nb['has_bg'])
    --nb-login-image: url("{{ $nb['bg_url'] }}");
    --nb-login-overlay: {{ $nb['bg_overlay'] }};
    --nb-login-blur: {{ (int) $nb['bg_blur'] }}px;
@endif
}
</style>
