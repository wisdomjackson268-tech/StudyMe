<?php

if (!defined('ALOC_BASE_URL')) {
    $baseUrl = env('ALOC_BASE_URL', 'https://questions.aloc.com.ng/api/v2');
    define('ALOC_BASE_URL', rtrim(!empty($baseUrl) ? $baseUrl : 'https://questions.aloc.com.ng/api/v2', '/'));
}

function get_aloc_api_key(): string {
    $key = env('ALOC_API_KEY');
    if ($key !== null && $key !== '') {
        return trim((string)$key);
    }
    return '';
}

function get_aloc_base_url(): string {
    return defined('ALOC_BASE_URL') ? ALOC_BASE_URL : 'https://questions.aloc.com.ng/api/v2';
}

function is_aloc_configured(): bool {
    return get_aloc_api_key() !== '';
}

function get_aloc_supported_exams(): array {
    return [
        'utme'      => [
            'name'        => 'JAMB (UTME)',
            'full_name'   => 'Unified Tertiary Matriculation Examination',
            'badge'       => 'primary',
            'icon'        => 'bi-mortarboard-fill',
            'description' => 'Official entrance examination for Nigerian universities, polytechnics, and colleges.'
        ],
        'waec'      => [
            'name'        => 'WAEC (SSCE)',
            'full_name'   => 'West African Senior School Certificate Examination',
            'badge'       => 'success',
            'icon'        => 'bi-award-fill',
            'description' => 'Regional standardized secondary school leaving examination.'
        ],
        'neco'      => [
            'name'        => 'NECO (SSCE)',
            'full_name'   => 'National Examinations Council',
            'badge'       => 'warning text-dark',
            'icon'        => 'bi-book-half',
            'description' => 'National senior school certificate examination for secondary schools across Nigeria.'
        ],
        'post-utme' => [
            'name'        => 'Post-UTME',
            'full_name'   => 'Post-Unified Tertiary Matriculation Examination',
            'badge'       => 'info text-dark',
            'icon'        => 'bi-building-fill',
            'description' => 'University-specific screening and past examination tests.'
        ]
    ];
}

function get_aloc_supported_subjects(): array {
    return [
        'mathematics'     => ['name' => 'Mathematics', 'category' => 'general', 'icon' => 'bi-calculator'],
        'english'         => ['name' => 'English Language', 'category' => 'general', 'icon' => 'bi-translate'],
        'biology'         => ['name' => 'Biology', 'category' => 'science', 'icon' => 'bi-flower1'],
        'physics'         => ['name' => 'Physics', 'category' => 'science', 'icon' => 'bi-lightning-charge'],
        'chemistry'       => ['name' => 'Chemistry', 'category' => 'science', 'icon' => 'bi-radioactive'],
        'economics'       => ['name' => 'Economics', 'category' => 'commercial', 'icon' => 'bi-graph-up'],
        'government'      => ['name' => 'Government', 'category' => 'arts', 'icon' => 'bi-bank'],
        'literature'      => ['name' => 'Literature in English', 'category' => 'arts', 'icon' => 'bi-journal-text'],
        'crk'             => ['name' => 'Christian Religious Studies (CRK)', 'category' => 'arts', 'icon' => 'bi-book'],
        'irk'             => ['name' => 'Islamic Religious Studies (IRK)', 'category' => 'arts', 'icon' => 'bi-moon-stars'],
        'civiledu'        => ['name' => 'Civic Education', 'category' => 'general', 'icon' => 'bi-people'],
        'commerce'        => ['name' => 'Commerce', 'category' => 'commercial', 'icon' => 'bi-cart-check'],
        'accounting'      => ['name' => 'Financial Accounting', 'category' => 'commercial', 'icon' => 'bi-cash-coin'],
        'geography'       => ['name' => 'Geography', 'category' => 'science', 'icon' => 'bi-globe-americas'],
        'history'         => ['name' => 'History', 'category' => 'arts', 'icon' => 'bi-clock-history'],
        'computer'        => ['name' => 'Computer Studies', 'category' => 'science', 'icon' => 'bi-laptop'],
        'insurance'       => ['name' => 'Insurance', 'category' => 'commercial', 'icon' => 'bi-shield-check'],
        'currentaffairs'  => ['name' => 'Current Affairs', 'category' => 'general', 'icon' => 'bi-newspaper']
    ];
}

function get_aloc_supported_years(): array {
    return [2024, 2023, 2022, 2021, 2020, 2019, 2018, 2017, 2016, 2015, 2014, 2013, 2012, 2011, 2010];
}
