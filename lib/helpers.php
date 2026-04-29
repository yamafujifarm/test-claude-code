<?php
declare(strict_types=1);

const CATEGORY_LABELS = [
    'business' => '業務用',
    'regular'  => '常連',
    'retail'   => '自由米',
];

const CATEGORY_BADGE_CLASS = [
    'business' => 'badge-business',
    'regular'  => 'badge-regular',
    'retail'   => 'badge-retail',
];

const CONFIDENCE_LABELS = [
    'high'   => '高',
    'medium' => '中',
    'low'    => '低',
    'none'   => 'データ不足',
];

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function category_label(string $category): string
{
    return CATEGORY_LABELS[$category] ?? $category;
}

function category_badge_class(string $category): string
{
    return CATEGORY_BADGE_CLASS[$category] ?? '';
}

function confidence_label(string $confidence): string
{
    return CONFIDENCE_LABELS[$confidence] ?? $confidence;
}

function url(string $path = '', array $params = []): string
{
    $qs = $params ? '?' . http_build_query($params) : '';
    return 'index.php' . $qs;
}

function redirect(string $page, array $params = []): void
{
    $params = array_merge(['p' => $page], $params);
    header('Location: ' . url('', $params));
    exit;
}

function format_date(?string $datetime): string
{
    if (!$datetime) return '-';
    $ts = strtotime($datetime);
    return $ts ? date('Y/m/d', $ts) : '-';
}

function format_datetime(?string $datetime): string
{
    if (!$datetime) return '-';
    $ts = strtotime($datetime);
    return $ts ? date('Y/m/d H:i', $ts) : '-';
}

function format_kg(?float $kg): string
{
    if ($kg === null) return '-';
    return rtrim(rtrim(number_format($kg, 2), '0'), '.') . ' kg';
}

/**
 * 玄米本数の計算ベース。白米 1 本（袋）= 27 kg として計算する。
 * 業務上の換算式: 玄米本数 = 白米kg / 27
 */
const GENMAI_KG_PER_UNIT = 27;

function genmai_count(float $kg): float
{
    if (GENMAI_KG_PER_UNIT <= 0) return 0.0;
    return $kg / GENMAI_KG_PER_UNIT;
}

function format_genmai(?float $kg): string
{
    if ($kg === null) return '-';
    return rtrim(rtrim(number_format(genmai_count($kg), 2), '0'), '.') . ' 本';
}

/**
 * 「30 kg / 玄米 1.1 本」のように白米 kg と玄米本数を併記する。
 */
function format_kg_with_genmai(?float $kg): string
{
    if ($kg === null) return '-';
    return format_kg($kg) . ' / 玄米 ' . rtrim(rtrim(number_format(genmai_count($kg), 2), '0'), '.') . ' 本';
}

function days_until(?string $dateStr): ?int
{
    if (!$dateStr) return null;
    $today = strtotime(date('Y-m-d'));
    $target = strtotime($dateStr);
    if ($target === false) return null;
    return (int)round(($target - $today) / 86400);
}

function days_until_label(?int $days): string
{
    if ($days === null) return '-';
    if ($days === 0) return '今日';
    if ($days < 0)   return abs($days) . '日前に予測日経過';
    if ($days === 1) return '明日';
    return 'あと ' . $days . ' 日';
}

function urgency_class(?int $days): string
{
    if ($days === null) return '';
    if ($days <= 0) return 'urgency-overdue';
    if ($days <= 3) return 'urgency-soon';
    if ($days <= 7) return 'urgency-week';
    return 'urgency-far';
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

function get(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}
