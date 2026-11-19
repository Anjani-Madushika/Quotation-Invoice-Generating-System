<?php
// Look and feel: button / input classes, icons, logo, document colour theme, page heading.
// Loaded by includes/auth.php, so every page already has it.

// ---- Tailwind class strings used all over the pages ----
const BTN   = 'inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold px-4 py-2.5 text-sm transition';
const BTN2  = 'inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold px-4 py-2.5 text-sm transition';
const INPUT = 'w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500';
// Taller input used on Login / Sign up
const AUTH_INPUT = 'w-full h-11 rounded-xl border border-slate-300 bg-white px-4 text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500';

const ICONS = [
    'home'     => 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10',
    'file'     => 'M7 3h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM14 3v5h5M9 13h6M9 17h4',
    'receipt'  => 'M6 3h12v18l-3-2-3 2-3-2-3 2V3zM9 8h6M9 12h6',
    'box'      => 'M21 8l-9-5-9 5v8l9 5 9-5V8zM3 8l9 5 9-5M12 13v8',
    'building' => 'M4 21V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v16M14 9h5a1 1 0 0 1 1 1v11M2 21h20M8 8h2M8 12h2M8 16h2',
    'users'    => 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM4 21a8 8 0 0 1 16 0',
    'clock'    => 'M12 7v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
    'check'    => 'M5 13l4 4L19 7',
    'plus'     => 'M12 5v14M5 12h14',
    'cash'     => 'M3 7h18v10H3V7zM12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
    'download' => 'M12 3v12m0 0l-4-4m4 4l4-4M4 17v3h16v-3',
    'eye'      => 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
    'eye_off'  => 'M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A9.8 9.8 0 0 1 12 5c6 0 10 7 10 7a17 17 0 0 1-3.2 4M6.1 6.1A17 17 0 0 0 2 12s4 7 10 7a9.7 9.7 0 0 0 4.1-.9',
    'logout'   => 'M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3',
];

function icon(string $key, string $cls = 'w-5 h-5'): string {
    return '<svg class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="' . ICONS[$key] . '"/></svg>';
}

// Logo marks. $tone 'light' = white tile (use on dark blue), 'dark' = navy tile (use on white).
function logo_mark(string $cls = 'w-11 h-11', string $tone = 'light'): string {
    $tile = $tone === 'light' ? '#ffffff' : '#0b1f44';
    $ring = $tone === 'light' ? '#0b1f44' : '#ffffff';
    return '<svg class="' . $cls . ' shrink-0" viewBox="0 0 64 64" aria-hidden="true">'
         . '<rect width="64" height="64" rx="16" fill="' . $tile . '"/>'
         . '<circle cx="30" cy="30" r="13" fill="none" stroke="' . $ring . '" stroke-width="6"/>'
         . '<path d="M36 36 L47 47" stroke="#e0a526" stroke-width="6" stroke-linecap="round"/></svg>';
}

// ---- Page heading (title, small text, buttons on the right) ----
function page_head(string $title, string $sub = '', string $actions = ''): void {
    echo '<div class="no-print flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6"><div class="min-w-0">';
    echo '<h1 class="text-2xl font-extrabold text-brand-900">' . e($title) . '</h1>';
    if ($sub !== '') {
        echo '<p class="text-slate-500 mt-1">' . e($sub) . '</p>';
    }
    echo '</div><div class="flex flex-wrap gap-2 [&>a]:flex-1 sm:[&>a]:flex-none">' . $actions . '</div></div>';
}

// ---- Document colour theme ----
// $hex is the company's chosen colour (the middle of the banner gradient).
const DEFAULT_THEME = '#12306b';
const THEME_PRESETS = [
    'Navy'    => '#12306b',
    'Blue'    => '#1d4ed8',
    'Teal'    => '#0f766e',
    'Green'   => '#15803d',
    'Crimson' => '#be123c',
    'Purple'  => '#6d28d9',
    'Orange'  => '#c2410c',
    'Slate'   => '#334155',
];

function theme_mix(string $hex, string $to, float $ratio): string {
    $a = sscanf($hex, '#%02x%02x%02x');
    $b = sscanf($to, '#%02x%02x%02x');
    $o = '#';
    for ($i = 0; $i < 3; $i++) {
        $o .= sprintf('%02x', (int) round($a[$i] + ($b[$i] - $a[$i]) * $ratio));
    }
    return $o;
}

function theme_colors(?string $hex): array {
    if (!$hex || !preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
        $hex = DEFAULT_THEME;
    }
    $hex = strtolower($hex);
    $isDefault = $hex === DEFAULT_THEME;
    return [
        'dark'   => $isDefault ? '#0b1f44' : theme_mix($hex, '#000000', 0.35),
        'mid'    => $hex,
        'light'  => $isDefault ? '#2c4a8f' : theme_mix($hex, '#ffffff', 0.22),
        'tint'   => theme_mix($hex, '#ffffff', 0.94),
        'border' => theme_mix($hex, '#ffffff', 0.82),
    ];
}

// ---- Document layout ----
// The company picks one on the Company page; it is used on every quotation and invoice.
const DEFAULT_LAYOUT = 'modern';
const DOC_LAYOUTS = [
    'modern'  => ['Modern',  'Colour banner with logo and title across the top'],
    'classic' => ['Classic', 'White header with a coloured line and a filled table header'],
    'minimal' => ['Minimal', 'Light, clean page with thin lines and lots of white space'],
];

function doc_layout_key(?string $key): string {
    return ($key !== null && isset(DOC_LAYOUTS[$key])) ? $key : DEFAULT_LAYOUT;
}
