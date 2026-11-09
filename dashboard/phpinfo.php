<?php
/**
 * Modern PHP Info Dashboard for XAMPP
 * Enhanced UI/UX with Back Navigation, Live Search, Theme Toggle, and Quick Module Jump
 */

// Capture phpinfo output
ob_start();
phpinfo();
$rawInfo = ob_get_clean();

// Extract body content
if (preg_match('/<body[^>]*>(.*)<\/body>/is', $rawInfo, $matches)) {
    $bodyContent = $matches[1];
} else {
    $bodyContent = $rawInfo;
}

// Clean default outer wrappers and tags
$bodyContent = preg_replace('/^\s*<div class="center">\s*/is', '', $bodyContent);
$bodyContent = preg_replace('/\s*<\/div>\s*$/is', '', $bodyContent);
$bodyContent = preg_replace('/<hr\s*\/?>/i', '', $bodyContent);

// System metrics
$phpVersion = PHP_VERSION;
$phpSapi = php_sapi_name();
$loadedIni = php_ini_loaded_file() ?: 'None';
$memoryLimit = ini_get('memory_limit') ?: 'N/A';
$maxExecution = ini_get('max_execution_time') !== false ? ini_get('max_execution_time') . 's' : 'N/A';
$uploadMax = ini_get('upload_max_filesize') ?: 'N/A';
$postMax = ini_get('post_max_size') ?: 'N/A';
$displayErrors = ini_get('display_errors') ? 'On' : 'Off';
$loadedExts = get_loaded_extensions();
natcasesort($loadedExts);
$extCount = count($loadedExts);
$os = PHP_OS . ' (' . php_uname('m') . ')';

// Value badge formatter
function formatTableValues($html) {
    return preg_replace_callback('/<td class="v">([^<]*)<\/td>/i', function($m) {
        $raw = trim($m[1]);
        $lower = strtolower($raw);
        if ($lower === 'on' || $lower === 'enabled' || $lower === 'active' || $lower === 'yes' || $lower === 'true') {
            return '<td class="v"><span class="val-badge val-on">' . htmlspecialchars($raw) . '</span></td>';
        } elseif ($lower === 'off' || $lower === 'disabled' || $lower === 'inactive' || $lower === 'no' || $lower === 'false') {
            return '<td class="v"><span class="val-badge val-off">' . htmlspecialchars($raw) . '</span></td>';
        } elseif ($lower === 'no value' || $lower === 'none' || $raw === '') {
            return '<td class="v"><span class="val-badge val-none"><em>no value</em></span></td>';
        }
        return '<td class="v">' . $m[1] . '</td>';
    }, $html);
}

// Split into parts by H2
$sections = preg_split('/(<h2[^>]*>.*?<\/h2>)/is', $bodyContent, -1, PREG_SPLIT_DELIM_CAPTURE);

// Parse general section and module sections
$generalHtml = '';
$moduleSections = [];
$modulesList = [
    ['id' => 'sec-general', 'name' => 'General System Info']
];

if (!empty($sections[0])) {
    $generalHtml = formatTableValues($sections[0]);
}

for ($i = 1; $i < count($sections); $i += 2) {
    $h2 = $sections[$i] ?? '';
    $table = $sections[$i + 1] ?? '';
    
    $cleanTitle = trim(strip_tags($h2));
    if ($cleanTitle === '') {
        $cleanTitle = 'Section ' . (($i + 1) / 2);
    }
    
    $slugId = 'sec-' . strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '-', $cleanTitle));
    $modulesList[] = [
        'id' => $slugId,
        'name' => $cleanTitle
    ];
    
    $moduleSections[] = [
        'id' => $slugId,
        'title' => $cleanTitle,
        'h2' => $h2,
        'table' => formatTableValues($table)
    ];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP <?= htmlspecialchars($phpVersion) ?> Info | XAMPP Dashboard</title>
    <link rel="icon" type="image/png" href="/dashboard/images/favicon.png">
    
    <style>
        :root {
            --bg-base: #f8fafc;
            --bg-surface: #ffffff;
            --bg-subtle: #f1f5f9;
            --bg-hover: #e2e8f0;
            --border-color: #e2e8f0;
            --border-subtle: #cbd5e1;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-subtle: #e0e7ff;
            --primary-text: #3730a3;
            --success-bg: #dcfce7;
            --success-text: #15803d;
            --danger-bg: #fee2e2;
            --danger-text: #b91c1c;
            --neutral-bg: #f3f4f6;
            --neutral-text: #6b7280;
            --header-h: 70px;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        [data-theme="dark"] {
            --bg-base: #0b0f19;
            --bg-surface: #131b2e;
            --bg-subtle: #1e293b;
            --bg-hover: #2d3b55;
            --border-color: #243049;
            --border-subtle: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #64748b;
            --primary: #6366f1;
            --primary-hover: #818cf8;
            --primary-subtle: #232742;
            --primary-text: #c7d2fe;
            --success-bg: #064e3b;
            --success-text: #a7f3d0;
            --danger-bg: #7f1d1d;
            --danger-text: #fecaca;
            --neutral-bg: #1e293b;
            --neutral-text: #94a3b8;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.4);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.5);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.6);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--bg-base);
            color: var(--text-primary);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            padding-top: var(--header-h);
        }

        /* Top Navigation Header */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-h);
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            gap: 1rem;
            box-shadow: var(--shadow-sm);
        }

        [data-theme="dark"] .navbar {
            background: rgba(19, 27, 46, 0.85);
        }

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }

        /* Back Button */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 1rem;
            border-radius: 9999px;
            background: var(--bg-surface);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }

        .btn-back:hover {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            transform: translateX(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-back svg {
            width: 16px;
            height: 16px;
            stroke-width: 2.5;
            transition: transform 0.2s ease;
        }

        .btn-back:hover svg {
            stroke: #ffffff;
            transform: translateX(-2px);
        }

        .brand-crumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .brand-crumb a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
        }

        .brand-crumb a:hover {
            color: var(--primary);
        }

        .brand-crumb .separator {
            color: var(--text-muted);
        }

        .brand-crumb .current {
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .navbar-center {
            flex: 1;
            max-width: 520px;
            position: relative;
        }

        .search-container {
            position: relative;
            width: 100%;
            display: flex;
            align-items: center;
        }

        .search-icon {
            position: absolute;
            left: 0.85rem;
            width: 16px;
            height: 16px;
            color: var(--text-muted);
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            padding: 0.55rem 2.5rem 0.55rem 2.4rem;
            background: var(--bg-subtle);
            border: 1px solid var(--border-color);
            border-radius: 9999px;
            color: var(--text-primary);
            font-size: 0.875rem;
            outline: none;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            background: var(--bg-surface);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-subtle);
        }

        .search-clear {
            position: absolute;
            right: 0.75rem;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0.2rem;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .search-clear:hover {
            color: var(--text-primary);
        }

        .search-shortcut {
            position: absolute;
            right: 0.75rem;
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 0.15rem 0.4rem;
            font-size: 0.7rem;
            color: var(--text-muted);
            font-family: var(--font-mono);
            pointer-events: none;
        }

        .search-badge {
            margin-left: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--primary);
            background: var(--primary-subtle);
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            white-space: nowrap;
            display: none;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .nav-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.8rem;
            border-radius: 8px;
            background: transparent;
            color: var(--text-secondary);
            font-size: 0.825rem;
            font-weight: 500;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.15s ease;
        }

        .nav-link-btn:hover {
            background: var(--bg-subtle);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        .theme-toggle-btn {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            width: 38px;
            height: 38px;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .theme-toggle-btn:hover {
            color: var(--text-primary);
            border-color: var(--primary);
            transform: rotate(15deg);
        }

        .theme-toggle-btn svg {
            width: 18px;
            height: 18px;
        }

        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem 1.5rem;
        }

        /* Hero Banner */
        .hero-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }

        .hero-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4f46e5, #06b6d4, #10b981);
        }

        .hero-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 1.75rem;
        }

        .hero-title-group {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .php-logo-badge {
            width: 60px;
            height: 60px;
            background: #4F5B93;
            color: #ffffff;
            font-weight: 800;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            letter-spacing: -1px;
            box-shadow: 0 4px 10px rgba(79, 91, 147, 0.3);
            flex-shrink: 0;
        }

        .hero-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            letter-spacing: -0.02em;
        }

        .version-pill {
            font-size: 0.85rem;
            font-weight: 700;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            background: var(--primary-subtle);
            color: var(--primary-text);
            border: 1px solid rgba(79, 70, 229, 0.2);
        }

        .hero-desc {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-top: 0.25rem;
        }

        /* Quick Stat Grid */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }

        .stat-box {
            background: var(--bg-subtle);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
            transition: transform 0.15s ease, border-color 0.15s ease;
        }

        .stat-box:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .stat-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--text-muted);
            letter-spacing: 0.05em;
            margin-bottom: 0.35rem;
        }

        .stat-value {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-primary);
            font-family: var(--font-mono);
            word-break: break-all;
        }

        .ini-path-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            padding: 0.75rem 1rem;
            background: var(--bg-subtle);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.85rem;
        }

        .ini-path-label {
            font-weight: 600;
            color: var(--text-secondary);
            flex-shrink: 0;
        }

        .ini-path-code {
            font-family: var(--font-mono);
            color: var(--text-primary);
            word-break: break-all;
            flex: 1;
        }

        .btn-copy {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 0.35rem 0.7rem;
            border-radius: 6px;
            font-size: 0.775rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.15s ease;
            flex-shrink: 0;
        }

        .btn-copy:hover {
            color: var(--primary);
            border-color: var(--primary);
        }

        /* Sticky Navigation Bar for Modules */
        .modules-nav-bar {
            position: sticky;
            top: var(--header-h);
            background: var(--bg-base);
            padding: 0.75rem 0;
            z-index: 900;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }

        .modules-select-wrapper {
            flex-shrink: 0;
            width: 240px;
        }

        .modules-select {
            width: 100%;
            padding: 0.5rem 0.75rem;
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.85rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
        }

        .modules-select:focus {
            border-color: var(--primary);
        }

        .modules-quick-pills {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            overflow-x: auto;
            white-space: nowrap;
            padding-bottom: 2px;
            scrollbar-width: thin;
        }

        .module-pill {
            padding: 0.35rem 0.75rem;
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 9999px;
            color: var(--text-secondary);
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.15s ease;
            flex-shrink: 0;
        }

        .module-pill:hover, .module-pill.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        /* Section Cards */
        .info-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: border-color 0.2s ease;
        }

        .info-card:hover {
            border-color: var(--border-subtle);
        }

        .info-card.flash-highlight {
            animation: pulse-border 1.5s ease-out;
        }

        @keyframes pulse-border {
            0% { box-shadow: 0 0 0 4px var(--primary); border-color: var(--primary); }
            100% { box-shadow: var(--shadow-sm); border-color: var(--border-color); }
        }

        .card-header {
            padding: 1rem 1.5rem;
            background: var(--bg-subtle);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header h2, .card-header h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-header a {
            color: inherit;
            text-decoration: none;
        }

        .card-header a:hover {
            color: var(--primary);
        }

        .card-header-badge {
            font-size: 0.75rem;
            color: var(--text-muted);
            background: var(--bg-surface);
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            font-family: var(--font-mono);
        }

        .card-body {
            padding: 0;
            overflow-x: auto;
        }

        /* Native phpinfo table styles overrides */
        table {
            border-collapse: collapse;
            width: 100% !important;
            margin: 0 !important;
            box-shadow: none !important;
            font-size: 0.875rem !important;
        }

        table + table {
            border-top: 1px solid var(--border-color);
        }

        th {
            background-color: var(--bg-subtle) !important;
            color: var(--text-secondary) !important;
            font-weight: 700 !important;
            text-align: left !important;
            padding: 0.75rem 1.25rem !important;
            border: none !important;
            border-bottom: 2px solid var(--border-color) !important;
            position: sticky;
            top: calc(var(--header-h) + 48px);
            z-index: 10;
        }

        tr.h th, tr.h td {
            background-color: var(--bg-subtle) !important;
            color: var(--text-primary) !important;
            font-weight: 700 !important;
            padding: 0.75rem 1.25rem !important;
            border: none !important;
            border-bottom: 2px solid var(--border-color) !important;
        }

        td {
            padding: 0.65rem 1.25rem !important;
            border: none !important;
            border-bottom: 1px solid var(--border-color) !important;
            vertical-align: middle !important;
            font-size: 0.85rem !important;
            color: var(--text-primary) !important;
        }

        tr:last-child td {
            border-bottom: none !important;
        }

        tr:hover td {
            background-color: var(--bg-subtle) !important;
        }

        /* Directive Name Column */
        td.e {
            background-color: transparent !important;
            font-weight: 600 !important;
            color: var(--text-primary) !important;
            width: 32% !important;
            font-family: var(--font-mono) !important;
            font-size: 0.825rem !important;
            word-break: break-all;
        }

        /* Values Column */
        td.v {
            background-color: transparent !important;
            color: var(--text-secondary) !important;
            font-family: var(--font-mono) !important;
            font-size: 0.825rem !important;
            word-break: break-word !important;
            max-width: none !important;
        }

        /* Value Badges */
        .val-badge {
            display: inline-block;
            padding: 0.15rem 0.55rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            font-family: var(--font-family);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .val-on {
            background-color: var(--success-bg);
            color: var(--success-text);
        }

        .val-off {
            background-color: var(--danger-bg);
            color: var(--danger-text);
        }

        .val-none {
            background-color: var(--neutral-bg);
            color: var(--neutral-text);
            font-weight: normal;
        }

        /* Search highlights */
        mark.search-highlight {
            background-color: #fef08a;
            color: #854d0e;
            padding: 0.1em 0.25em;
            border-radius: 3px;
        }

        [data-theme="dark"] mark.search-highlight {
            background-color: #854d0e;
            color: #fef08a;
        }

        /* Floating Back to Top Button */
        .fab-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--primary);
            color: #ffffff;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-lg);
            cursor: pointer;
            opacity: 0;
            pointer-events: none;
            transition: all 0.25s ease;
            z-index: 999;
        }

        .fab-top.visible {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }

        .fab-top:hover {
            background: var(--primary-hover);
            transform: translateY(-3px);
        }

        /* Toast notifications */
        .toast {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #1e293b;
            color: #ffffff;
            padding: 0.65rem 1.25rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 500;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            z-index: 2000;
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            pointer-events: none;
        }

        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        /* Empty search state */
        .empty-search-state {
            display: none;
            text-align: center;
            padding: 4rem 2rem;
            background: var(--bg-surface);
            border: 1px dashed var(--border-color);
            border-radius: 16px;
            margin: 2rem 0;
        }

        .empty-search-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        /* Responsive Styles */
        @media (max-width: 900px) {
            .navbar-center {
                max-width: 320px;
            }
            .nav-link-text {
                display: none;
            }
        }

        @media (max-width: 768px) {
            :root {
                --header-h: auto;
            }
            body {
                padding-top: 130px;
            }
            .navbar {
                flex-wrap: wrap;
                padding: 0.75rem 1rem;
                height: auto;
            }
            .navbar-left {
                order: 1;
            }
            .navbar-right {
                order: 2;
            }
            .navbar-center {
                order: 3;
                width: 100%;
                max-width: none;
                margin-top: 0.5rem;
            }
            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .modules-select-wrapper {
                width: 160px;
            }
        }
    </style>
</head>
<body>

    <!-- Top Fixed Navigation -->
    <header class="navbar">
        <div class="navbar-left">
            <!-- Smart Back Button -->
            <button type="button" class="btn-back" id="backBtn" onclick="smartGoBack()" title="Back to Previous Page">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                <span>Back</span>
            </button>

            <!-- Breadcrumbs -->
            <div class="brand-crumb">
                <a href="/dashboard/index.html">Dashboard</a>
                <span class="separator">/</span>
                <span class="current">PHP Info</span>
            </div>
        </div>

        <!-- Center Search Bar -->
        <div class="navbar-center">
            <div class="search-container">
                <svg class="search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" id="searchInput" class="search-input" placeholder="Search directives, modules, values..." autocomplete="off">
                <span class="search-shortcut" id="searchShortcut">/</span>
                <button type="button" id="searchClear" class="search-clear" title="Clear search">✕</button>
            </div>
        </div>

        <!-- Right Action Links -->
        <div class="navbar-right">
            <span class="search-badge" id="searchCountBadge">0 matches</span>

            <a href="/dashboard/index.html" class="nav-link-btn" title="Dashboard">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span class="nav-link-text">Dashboard</span>
            </a>

            <a href="/waterbillingsystem/home.php" class="nav-link-btn" title="Water Billing System">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
                <span class="nav-link-text">Water Billing</span>
            </a>

            <a href="/phpmyadmin/" class="nav-link-btn" title="phpMyAdmin Database Manager">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                </svg>
                <span class="nav-link-text">phpMyAdmin</span>
            </a>

            <!-- Theme Toggle -->
            <button type="button" class="theme-toggle-btn" id="themeToggle" title="Toggle Dark/Light Mode">
                <svg id="themeIconSun" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <svg id="themeIconMoon" style="display:none;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
            </button>
        </div>
    </header>

    <main class="main-container">

        <!-- Hero Overview Card -->
        <section class="hero-card">
            <div class="hero-top">
                <div class="hero-title-group">
                    <div class="php-logo-badge">PHP</div>
                    <div>
                        <div class="hero-title">
                            PHP Environment
                            <span class="version-pill">v<?= htmlspecialchars($phpVersion) ?></span>
                        </div>
                        <div class="hero-desc">
                            Server API: <strong><?= htmlspecialchars($phpSapi) ?></strong> &bull; Operating System: <strong><?= htmlspecialchars($os) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metric Cards -->
            <div class="stat-grid">
                <div class="stat-box">
                    <div class="stat-label">Memory Limit</div>
                    <div class="stat-value"><?= htmlspecialchars($memoryLimit) ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Max Execution</div>
                    <div class="stat-value"><?= htmlspecialchars($maxExecution) ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Upload Max</div>
                    <div class="stat-value"><?= htmlspecialchars($uploadMax) ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Post Max Size</div>
                    <div class="stat-value"><?= htmlspecialchars($postMax) ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Display Errors</div>
                    <div class="stat-value">
                        <span class="val-badge <?= strtolower($displayErrors) === 'on' ? 'val-on' : 'val-off' ?>">
                            <?= htmlspecialchars($displayErrors) ?>
                        </span>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Extensions</div>
                    <div class="stat-value"><?= $extCount ?> Active</div>
                </div>
            </div>

            <!-- php.ini Path with 1-Click Copy -->
            <div class="ini-path-container">
                <span class="ini-path-label">Loaded Configuration File:</span>
                <code class="ini-path-code" id="iniPathText"><?= htmlspecialchars($loadedIni) ?></code>
                <button type="button" class="btn-copy" onclick="copyIniPath()" title="Copy file path">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    <span>Copy Path</span>
                </button>
            </div>
        </section>

        <!-- Module Quick Jump Navigation Bar -->
        <div class="modules-nav-bar">
            <div class="modules-select-wrapper">
                <select id="moduleSelect" class="modules-select" onchange="jumpToModule(this.value)">
                    <option value="">Jump to Module (<?= count($modulesList) ?>)...</option>
                    <?php foreach ($modulesList as $mod): ?>
                        <option value="<?= htmlspecialchars($mod['id']) ?>"><?= htmlspecialchars($mod['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modules-quick-pills">
                <a href="#sec-general" class="module-pill">General</a>
                <a href="#sec-core" class="module-pill">Core</a>
                <a href="#sec-curl" class="module-pill">cURL</a>
                <a href="#sec-date" class="module-pill">date</a>
                <a href="#sec-mbstring" class="module-pill">mbstring</a>
                <a href="#sec-mysqli" class="module-pill">MySQLi</a>
                <a href="#sec-openssl" class="module-pill">OpenSSL</a>
                <a href="#sec-pdo" class="module-pill">PDO</a>
                <a href="#sec-session" class="module-pill">Session</a>
                <a href="#sec-environment" class="module-pill">Environment</a>
                <a href="#sec-php-variables" class="module-pill">Variables</a>
            </div>
        </div>

        <!-- Empty search result card -->
        <div id="emptySearch" class="empty-search-state">
            <div class="empty-search-icon">🔍</div>
            <h3>No matching PHP settings found</h3>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">Try another keyword like "memory", "upload", "mysqli", or "error".</p>
            <button type="button" class="btn-back" onclick="clearSearch()" style="margin-top: 1rem;">Clear Search</button>
        </div>

        <!-- General System Info Card -->
        <section class="info-card" id="sec-general" data-section-name="General System Info">
            <div class="card-header">
                <h2>General System Information</h2>
                <span class="card-header-badge">General</span>
            </div>
            <div class="card-body">
                <?= $generalHtml ?>
            </div>
        </section>

        <!-- Module Sections -->
        <?php foreach ($moduleSections as $sec): ?>
            <section class="info-card" id="<?= htmlspecialchars($sec['id']) ?>" data-section-name="<?= htmlspecialchars($sec['title']) ?>">
                <div class="card-header">
                    <h3>
                        <a href="#<?= htmlspecialchars($sec['id']) ?>"><?= htmlspecialchars($sec['title']) ?></a>
                    </h3>
                    <span class="card-header-badge"><?= htmlspecialchars($sec['title']) ?></span>
                </div>
                <div class="card-body">
                    <?= $sec['table'] ?>
                </div>
            </section>
        <?php endforeach; ?>

    </main>

    <!-- Floating Back to Top Button -->
    <button type="button" id="fabTop" class="fab-top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" title="Back to top">
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/>
        </svg>
    </button>

    <!-- Floating Toast Message -->
    <div id="toast" class="toast">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#4ade80">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
        <span id="toastMsg">Copied!</span>
    </div>

    <script>
        // Smart Back Navigation
        function smartGoBack() {
            // Check if there is actual history we can go back to on the same host
            if (window.history.length > 1 && document.referrer && document.referrer.indexOf(window.location.host) !== -1) {
                window.history.back();
            } else {
                // Default fallback to Dashboard
                window.location.href = '/dashboard/index.html';
            }
        }

        // Theme Toggle (Dark / Light Mode)
        const htmlEl = document.documentElement;
        const themeToggle = document.getElementById('themeToggle');
        const sunIcon = document.getElementById('themeIconSun');
        const moonIcon = document.getElementById('themeIconMoon');

        function setTheme(theme) {
            htmlEl.setAttribute('data-theme', theme);
            localStorage.setItem('phpinfo_theme', theme);
            if (theme === 'dark') {
                sunIcon.style.display = 'none';
                moonIcon.style.display = 'block';
            } else {
                sunIcon.style.display = 'block';
                moonIcon.style.display = 'none';
            }
        }

        const savedTheme = localStorage.getItem('phpinfo_theme') || 
            (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        setTheme(savedTheme);

        themeToggle.addEventListener('click', () => {
            const current = htmlEl.getAttribute('data-theme');
            setTheme(current === 'dark' ? 'light' : 'dark');
        });

        // Copy php.ini path
        function copyIniPath() {
            const text = document.getElementById('iniPathText').innerText;
            navigator.clipboard.writeText(text).then(() => {
                showToast('php.ini path copied to clipboard');
            }).catch(() => {
                showToast('Failed to copy');
            });
        }

        // Toast Helper
        function showToast(msg) {
            const toast = document.getElementById('toast');
            document.getElementById('toastMsg').innerText = msg;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 2500);
        }

        // Jump to Module
        function jumpToModule(id) {
            if (!id) return;
            const target = document.getElementById(id);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
                target.classList.add('flash-highlight');
                setTimeout(() => target.classList.remove('flash-highlight'), 1600);
            }
        }

        // Scroll listener for Fab Top
        const fabTop = document.getElementById('fabTop');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 400) {
                fabTop.classList.add('visible');
            } else {
                fabTop.classList.remove('visible');
            }
        });

        // Live Real-Time Search & Filtering
        const searchInput = document.getElementById('searchInput');
        const searchClear = document.getElementById('searchClear');
        const searchShortcut = document.getElementById('searchShortcut');
        const searchBadge = document.getElementById('searchCountBadge');
        const emptyState = document.getElementById('emptySearch');
        const allCards = document.querySelectorAll('.info-card');

        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim().toLowerCase();
            if (query.length > 0) {
                searchClear.style.display = 'flex';
                searchShortcut.style.display = 'none';
                performSearch(query);
            } else {
                clearSearch();
            }
        });

        function clearSearch() {
            searchInput.value = '';
            searchClear.style.display = 'none';
            searchShortcut.style.display = 'block';
            searchBadge.style.display = 'none';
            emptyState.style.display = 'none';

            allCards.forEach(card => {
                card.style.display = '';
                const rows = card.querySelectorAll('tr');
                rows.forEach(tr => {
                    tr.style.display = '';
                    unmarkRow(tr);
                });
            });
        }

        searchClear.addEventListener('click', clearSearch);

        // Keyboard Shortcuts: '/' or 'Ctrl+K' focuses search; 'Escape' clears it
        window.addEventListener('keydown', (e) => {
            if ((e.key === '/' || (e.ctrlKey && e.key.toLowerCase() === 'k')) && document.activeElement !== searchInput) {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            } else if (e.key === 'Escape' && document.activeElement === searchInput) {
                clearSearch();
                searchInput.blur();
            }
        });

        function unmarkRow(tr) {
            tr.querySelectorAll('mark.search-highlight').forEach(mark => {
                const parent = mark.parentNode;
                parent.replaceChild(document.createTextNode(mark.textContent), mark);
                parent.normalize();
            });
        }

        function highlightMatch(element, query) {
            // Traverse text nodes
            const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, null, false);
            const textNodes = [];
            let node;
            while (node = walker.nextNode()) {
                if (node.parentElement && node.parentElement.tagName !== 'MARK') {
                    textNodes.push(node);
                }
            }

            textNodes.forEach(tNode => {
                const text = tNode.nodeValue;
                const lower = text.toLowerCase();
                const idx = lower.indexOf(query);
                if (idx !== -1) {
                    const before = text.substring(0, idx);
                    const match = text.substring(idx, idx + query.length);
                    const after = text.substring(idx + query.length);

                    const mark = document.createElement('mark');
                    mark.className = 'search-highlight';
                    mark.textContent = match;

                    const frag = document.createDocumentFragment();
                    if (before) frag.appendChild(document.createTextNode(before));
                    frag.appendChild(mark);
                    if (after) frag.appendChild(document.createTextNode(after));

                    tNode.parentNode.replaceChild(frag, tNode);
                }
            });
        }

        function performSearch(query) {
            let totalMatches = 0;
            let visibleCards = 0;

            allCards.forEach(card => {
                const cardTitle = (card.getAttribute('data-section-name') || '').toLowerCase();
                const titleMatches = cardTitle.includes(query);
                const rows = card.querySelectorAll('tbody tr, table tr');
                let cardHasMatch = false;

                rows.forEach(tr => {
                    unmarkRow(tr);
                    // Skip table headers
                    if (tr.querySelector('th')) {
                        tr.style.display = '';
                        return;
                    }

                    const rowText = tr.textContent.toLowerCase();
                    if (titleMatches || rowText.includes(query)) {
                        tr.style.display = '';
                        cardHasMatch = true;
                        totalMatches++;
                        highlightMatch(tr, query);
                    } else {
                        tr.style.display = 'none';
                    }
                });

                if (cardHasMatch || titleMatches) {
                    card.style.display = '';
                    visibleCards++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Update badge and empty state
            searchBadge.style.display = 'inline-block';
            searchBadge.textContent = `${totalMatches} match${totalMatches === 1 ? '' : 'es'}`;

            if (visibleCards === 0) {
                emptyState.style.display = 'block';
            } else {
                emptyState.style.display = 'none';
            }
        }
    </script>
</body>
</html>
