<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Sanctum Manager</title>
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('sanctum-manager-theme');
                if (stored === 'light' || stored === 'dark') {
                    document.documentElement.dataset.theme = stored;
                }
            } catch (e) {}
        })();
    </script>
    <style>
        :root {
            color-scheme: light dark;
            --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;

            --bg: #f8fafc;
            --surface: #ffffff;
            --surface-subtle: #f1f5f9;
            --surface-hover: #f8fafc;
            --border: #e2e8f0;
            --border-strong: #cbd5e1;

            --text: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;

            --accent: #4f46e5;
            --accent-hover: #4338ca;
            --accent-soft: rgba(79, 70, 229, 0.10);
            --accent-text: #4338ca;
            --accent-contrast: #ffffff;

            --success-bg: #ecfdf5;
            --success-border: #a7f3d0;
            --success-text: #047857;
            --success-dot: #10b981;

            --warning-bg: #fffbeb;
            --warning-border: #fde68a;
            --warning-text: #b45309;
            --warning-dot: #f59e0b;

            --danger-bg: #fef2f2;
            --danger-border: #fecaca;
            --danger-text: #b91c1c;
            --danger-dot: #ef4444;
            --danger-solid: #dc2626;
            --danger-solid-hover: #b91c1c;

            --info-bg: #eff6ff;
            --info-border: #bfdbfe;
            --info-text: #1d4ed8;
            --info-dot: #3b82f6;

            --neutral-bg: #f1f5f9;
            --neutral-border: #cbd5e1;
            --neutral-text: #475569;
            --neutral-dot: #94a3b8;

            --code-bg: #0f172a;
            --code-text: #e2e8f0;

            --shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.05);
            --shadow-md: 0 4px 12px rgba(15, 23, 42, 0.08);
            --shadow-lg: 0 20px 40px rgba(15, 23, 42, 0.18);
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --radius-pill: 9999px;
        }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --bg: #0b1120;
                --surface: #111827;
                --surface-subtle: #1e293b;
                --surface-hover: #172033;
                --border: #1f2937;
                --border-strong: #334155;

                --text: #f8fafc;
                --text-secondary: #cbd5e1;
                --text-muted: #94a3b8;

                --accent: #6366f1;
                --accent-hover: #818cf8;
                --accent-soft: rgba(129, 140, 248, 0.16);
                --accent-text: #a5b4fc;
                --accent-contrast: #ffffff;

                --success-bg: rgba(16, 185, 129, 0.14);
                --success-border: rgba(16, 185, 129, 0.35);
                --success-text: #6ee7b7;
                --success-dot: #34d399;

                --warning-bg: rgba(245, 158, 11, 0.14);
                --warning-border: rgba(245, 158, 11, 0.35);
                --warning-text: #fcd34d;
                --warning-dot: #fbbf24;

                --danger-bg: rgba(239, 68, 68, 0.14);
                --danger-border: rgba(239, 68, 68, 0.35);
                --danger-text: #fca5a5;
                --danger-dot: #f87171;
                --danger-solid: #dc2626;
                --danger-solid-hover: #ef4444;

                --info-bg: rgba(59, 130, 246, 0.14);
                --info-border: rgba(59, 130, 246, 0.35);
                --info-text: #93c5fd;
                --info-dot: #60a5fa;

                --neutral-bg: rgba(148, 163, 184, 0.12);
                --neutral-border: rgba(148, 163, 184, 0.28);
                --neutral-text: #cbd5e1;
                --neutral-dot: #94a3b8;

                --code-bg: #020617;
                --code-text: #e2e8f0;

                --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.3);
                --shadow-md: 0 6px 16px rgba(0, 0, 0, 0.35);
                --shadow-lg: 0 24px 48px rgba(0, 0, 0, 0.6);
            }
        }

        :root[data-theme="dark"] {
            --bg: #0b1120;
            --surface: #111827;
            --surface-subtle: #1e293b;
            --surface-hover: #172033;
            --border: #1f2937;
            --border-strong: #334155;

            --text: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;

            --accent: #6366f1;
            --accent-hover: #818cf8;
            --accent-soft: rgba(129, 140, 248, 0.16);
            --accent-text: #a5b4fc;
            --accent-contrast: #ffffff;

            --success-bg: rgba(16, 185, 129, 0.14);
            --success-border: rgba(16, 185, 129, 0.35);
            --success-text: #6ee7b7;
            --success-dot: #34d399;

            --warning-bg: rgba(245, 158, 11, 0.14);
            --warning-border: rgba(245, 158, 11, 0.35);
            --warning-text: #fcd34d;
            --warning-dot: #fbbf24;

            --danger-bg: rgba(239, 68, 68, 0.14);
            --danger-border: rgba(239, 68, 68, 0.35);
            --danger-text: #fca5a5;
            --danger-dot: #f87171;
            --danger-solid: #dc2626;
            --danger-solid-hover: #ef4444;

            --info-bg: rgba(59, 130, 246, 0.14);
            --info-border: rgba(59, 130, 246, 0.35);
            --info-text: #93c5fd;
            --info-dot: #60a5fa;

            --neutral-bg: rgba(148, 163, 184, 0.12);
            --neutral-border: rgba(148, 163, 184, 0.28);
            --neutral-text: #cbd5e1;
            --neutral-dot: #94a3b8;

            --code-bg: #020617;
            --code-text: #e2e8f0;

            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 6px 16px rgba(0, 0, 0, 0.35);
            --shadow-lg: 0 24px 48px rgba(0, 0, 0, 0.6);
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: var(--bg);
            color: var(--text);
            font-family: var(--font-sans);
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: var(--accent-text);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        code, pre, .mono {
            font-family: var(--font-mono);
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* Top Navigation */
        .sm-header {
            position: sticky;
            top: 0;
            z-index: 40;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        .sm-header-inner {
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 20px;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .sm-header-left {
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
            padding: 10px 0;
        }

        .sm-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--text);
            font-weight: 700;
            font-size: 15px;
            letter-spacing: -0.01em;
            text-decoration: none;
        }

        .sm-brand:hover {
            text-decoration: none;
        }

        .sm-brand-mark {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--accent), #7c3aed);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(79, 70, 229, 0.35);
        }

        .sm-nav {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        .sm-nav-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 13.5px;
            text-decoration: none;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .sm-nav-link:hover {
            background: var(--surface-subtle);
            color: var(--text);
            text-decoration: none;
        }

        .sm-nav-link.is-active {
            background: var(--accent-soft);
            color: var(--accent-text);
            font-weight: 600;
        }

        .sm-header-right {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 0;
            flex-wrap: wrap;
        }

        /* Status Pills & Badges */
        .sm-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 9px;
            border-radius: var(--radius-pill);
            font-size: 12px;
            font-weight: 600;
            line-height: 1.3;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .sm-badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            flex-shrink: 0;
        }

        .sm-badge-success {
            background: var(--success-bg);
            border-color: var(--success-border);
            color: var(--success-text);
        }

        .sm-badge-success .sm-badge-dot {
            background: var(--success-dot);
        }

        .sm-badge-warning {
            background: var(--warning-bg);
            border-color: var(--warning-border);
            color: var(--warning-text);
        }

        .sm-badge-warning .sm-badge-dot {
            background: var(--warning-dot);
        }

        .sm-badge-danger {
            background: var(--danger-bg);
            border-color: var(--danger-border);
            color: var(--danger-text);
        }

        .sm-badge-danger .sm-badge-dot {
            background: var(--danger-dot);
        }

        .sm-badge-info {
            background: var(--info-bg);
            border-color: var(--info-border);
            color: var(--info-text);
        }

        .sm-badge-info .sm-badge-dot {
            background: var(--info-dot);
        }

        .sm-badge-neutral {
            background: var(--neutral-bg);
            border-color: var(--neutral-border);
            color: var(--neutral-text);
        }

        .sm-badge-neutral .sm-badge-dot {
            background: var(--neutral-dot);
        }

        .sm-badge-accent {
            background: var(--accent-soft);
            border-color: transparent;
            color: var(--accent-text);
        }

        .sm-chip {
            display: inline-flex;
            align-items: center;
            padding: 2px 7px;
            border-radius: 5px;
            font-family: var(--font-mono);
            font-size: 11.5px;
            font-weight: 500;
            background: var(--surface-subtle);
            color: var(--text-secondary);
            border: 1px solid var(--border);
            white-space: nowrap;
        }

        .sm-chip-accent {
            background: var(--accent-soft);
            color: var(--accent-text);
            border-color: transparent;
        }

        .sm-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        /* Theme Toggle */
        .sm-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-secondary);
            cursor: pointer;
            transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
        }

        .sm-icon-btn:hover {
            background: var(--surface-subtle);
            color: var(--text);
            border-color: var(--border-strong);
        }

        .sm-icon-btn:focus-visible,
        .sm-btn:focus-visible,
        .sm-input:focus-visible,
        .sm-select:focus-visible,
        .sm-nav-link:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }

        /* Main Container */
        .sm-main {
            max-width: 1320px;
            margin: 0 auto;
            padding: 24px 20px 48px;
        }

        .sm-page-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .sm-page-title {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text);
        }

        .sm-page-subtitle {
            margin: 4px 0 0;
            color: var(--text-muted);
            font-size: 13.5px;
        }

        .sm-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Buttons */
        .sm-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-strong);
            background: var(--surface);
            color: var(--text);
            font-family: var(--font-sans);
            font-size: 13px;
            font-weight: 600;
            line-height: 1.25;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, opacity 0.15s ease;
            white-space: nowrap;
        }

        .sm-btn:hover:not(:disabled) {
            background: var(--surface-subtle);
            text-decoration: none;
        }

        .sm-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .sm-btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .sm-btn-primary {
            background: var(--accent);
            border-color: var(--accent);
            color: var(--accent-contrast);
        }

        .sm-btn-primary:hover:not(:disabled) {
            background: var(--accent-hover);
            border-color: var(--accent-hover);
            color: var(--accent-contrast);
        }

        .sm-btn-danger {
            background: var(--danger-solid);
            border-color: var(--danger-solid);
            color: #ffffff;
        }

        .sm-btn-danger:hover:not(:disabled) {
            background: var(--danger-solid-hover);
            border-color: var(--danger-solid-hover);
            color: #ffffff;
        }

        .sm-btn-danger-outline {
            background: var(--surface);
            border-color: var(--danger-border);
            color: var(--danger-text);
        }

        .sm-btn-danger-outline:hover:not(:disabled) {
            background: var(--danger-bg);
        }

        /* Alerts & One-Time Token Box */
        .sm-alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 13px 16px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            margin-bottom: 18px;
            font-size: 13.5px;
        }

        .sm-alert-success {
            background: var(--success-bg);
            border-color: var(--success-border);
            color: var(--success-text);
        }

        .sm-alert-danger {
            background: var(--danger-bg);
            border-color: var(--danger-border);
            color: var(--danger-text);
        }

        .sm-alert-warning {
            background: var(--warning-bg);
            border-color: var(--warning-border);
            color: var(--warning-text);
        }

        .sm-alert ul {
            margin: 6px 0 0;
            padding-left: 18px;
        }

        .sm-secret-box {
            background: var(--surface);
            border: 2px solid var(--warning-dot);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            margin-bottom: 22px;
            box-shadow: var(--shadow-md);
        }

        .sm-secret-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .sm-secret-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
            margin: 0;
        }

        .sm-secret-warning {
            margin: 0 0 12px;
            font-size: 13px;
            color: var(--warning-text);
            font-weight: 500;
        }

        .sm-secret-row {
            display: flex;
            align-items: stretch;
            gap: 10px;
            flex-wrap: wrap;
        }

        .sm-secret-code {
            flex: 1 1 320px;
            background: var(--code-bg);
            color: #4ade80;
            font-family: var(--font-mono);
            font-size: 13px;
            padding: 11px 14px;
            border-radius: var(--radius-sm);
            word-break: break-all;
            user-select: all;
            border: 1px solid var(--border-strong);
        }

        /* Stats Grid */
        .sm-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 22px;
        }

        .sm-stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            box-shadow: var(--shadow-sm);
        }

        .sm-stat-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 6px;
        }

        .sm-stat-value {
            font-size: 24px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: var(--text);
            line-height: 1.15;
        }

        /* Cards */
        .sm-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .sm-card-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .sm-card-title {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }

        .sm-card-subtitle {
            margin: 2px 0 0;
            font-size: 12.5px;
            color: var(--text-muted);
        }

        .sm-card-body {
            padding: 18px 20px;
        }

        /* Toolbar & Filters */
        .sm-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }

        .sm-tabs {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--surface-subtle);
            padding: 3px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            flex-wrap: wrap;
        }

        .sm-tab {
            padding: 5px 11px;
            border-radius: 5px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-secondary);
            text-decoration: none;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .sm-tab:hover {
            color: var(--text);
            text-decoration: none;
        }

        .sm-tab.is-active {
            background: var(--surface);
            color: var(--text);
            box-shadow: var(--shadow-sm);
        }

        .sm-search-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1 1 260px;
            max-width: 380px;
        }

        /* Forms */
        .sm-input, .sm-select {
            width: 100%;
            padding: 8px 11px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-strong);
            background: var(--surface);
            color: var(--text);
            font-family: var(--font-sans);
            font-size: 13.5px;
            line-height: 1.4;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .sm-input:focus, .sm-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
            outline: none;
        }

        .sm-field {
            margin-bottom: 14px;
        }

        .sm-field-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 12px;
        }

        .sm-label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .sm-help {
            margin: 4px 0 0;
            font-size: 12px;
            color: var(--text-muted);
        }

        .sm-check-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 8px;
            max-height: 170px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface-subtle);
        }

        .sm-check-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 12.5px;
            cursor: pointer;
            color: var(--text);
        }

        .sm-check-item input[type="checkbox"] {
            margin-top: 2px;
            accent-color: var(--accent);
        }

        /* Data Tables */
        .sm-table-wrap {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .sm-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }

        .sm-table th {
            padding: 11px 16px;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
            background: var(--surface-subtle);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .sm-table td {
            padding: 13px 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
            color: var(--text);
        }

        .sm-table tbody tr:last-child td {
            border-bottom: none;
        }

        .sm-table tbody tr:hover {
            background: var(--surface-hover);
        }

        .sm-cell-title {
            font-weight: 600;
            color: var(--text);
        }

        .sm-cell-sub {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Pagination */
        .sm-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 13px 18px;
            border-top: 1px solid var(--border);
            background: var(--surface);
            font-size: 12.5px;
            color: var(--text-muted);
        }

        .sm-pagination-links {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        .sm-page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 9px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 12.5px;
            text-decoration: none;
        }

        .sm-page-link:hover {
            background: var(--surface-subtle);
            color: var(--text);
            text-decoration: none;
        }

        .sm-page-link.is-active {
            background: var(--accent);
            border-color: var(--accent);
            color: var(--accent-contrast);
        }

        .sm-page-link.is-disabled {
            opacity: 0.45;
            pointer-events: none;
        }

        /* Grid layouts */
        .sm-grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 18px;
            margin-bottom: 20px;
        }

        .sm-grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .sm-kv-list {
            display: grid;
            gap: 10px;
            margin: 0;
        }

        .sm-kv-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }

        .sm-kv-row:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }

        .sm-kv-key {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .sm-kv-val {
            font-size: 13px;
            color: var(--text);
            text-align: right;
            word-break: break-word;
        }

        /* Code Block */
        .sm-pre {
            margin: 12px 0 0;
            padding: 13px 15px;
            border-radius: var(--radius-sm);
            background: var(--code-bg);
            color: var(--code-text);
            font-family: var(--font-mono);
            font-size: 12px;
            line-height: 1.55;
            overflow-x: auto;
            border: 1px solid var(--border);
        }

        /* Empty State */
        .sm-empty {
            text-align: center;
            padding: 46px 24px;
            color: var(--text-muted);
        }

        .sm-empty-icon {
            width: 44px;
            height: 44px;
            margin: 0 auto 12px;
            border-radius: 12px;
            background: var(--surface-subtle);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sm-empty h3 {
            margin: 0 0 6px;
            font-size: 16px;
            color: var(--text);
        }

        .sm-empty p {
            margin: 0 auto;
            max-width: 520px;
            font-size: 13.5px;
        }

        /* Native <dialog> Modals */
        dialog.sm-dialog {
            padding: 0;
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-lg);
            background: var(--surface);
            color: var(--text);
            width: min(640px, calc(100vw - 28px));
            max-height: calc(100vh - 36px);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        dialog.sm-dialog.sm-dialog-sm {
            width: min(460px, calc(100vw - 28px));
        }

        dialog.sm-dialog::backdrop {
            background: rgba(15, 23, 42, 0.58);
            backdrop-filter: blur(3px);
        }

        dialog.sm-dialog[open] {
            display: flex;
            flex-direction: column;
        }

        .sm-dialog-form {
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 38px);
        }

        .sm-dialog-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
        }

        .sm-dialog-title {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }

        .sm-dialog-body {
            padding: 18px 20px;
            overflow-y: auto;
        }

        .sm-dialog-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            padding: 14px 20px;
            border-top: 1px solid var(--border);
            background: var(--surface-subtle);
        }

        .sm-footer {
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 20px 28px;
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        @media (max-width: 640px) {
            .sm-main {
                padding: 16px 14px 36px;
            }
            .sm-header-inner {
                padding: 0 14px;
            }
            .sm-search-form {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
@php
    $storageModeValue = $storageMode ?? 'zero_migration';
    $isExtendedStorage = $storageModeValue === 'extended_columns' || $storageModeValue === 'Extended Columns';
    $storageLabel = $isExtendedStorage ? 'Extended Columns' : 'Zero-Migration';
    $isMailEnabled = (bool) ($mailEnabled ?? false);
@endphp

<header class="sm-header">
    <div class="sm-header-inner">
        <div class="sm-header-left">
            <a href="{{ route('sanctum-manager.dashboard.index') }}" class="sm-brand">
                <span class="sm-brand-mark" aria-hidden="true">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <circle cx="12" cy="11" r="2"/>
                        <path d="M12 13v3"/>
                    </svg>
                </span>
                <span>Sanctum Manager</span>
            </a>

            <nav class="sm-nav" aria-label="Dashboard navigation">
                <a href="{{ route('sanctum-manager.dashboard.index') }}"
                   class="sm-nav-link {{ request()->routeIs('sanctum-manager.dashboard.index') ? 'is-active' : '' }}"
                   @if(request()->routeIs('sanctum-manager.dashboard.index')) aria-current="page" @endif>
                    Tokens
                </a>
                <a href="{{ route('sanctum-manager.dashboard.scopes') }}"
                   class="sm-nav-link {{ request()->routeIs('sanctum-manager.dashboard.scopes') ? 'is-active' : '' }}"
                   @if(request()->routeIs('sanctum-manager.dashboard.scopes')) aria-current="page" @endif>
                    Scopes &amp; Groups
                </a>
                <a href="{{ route('sanctum-manager.dashboard.logs') }}"
                   class="sm-nav-link {{ request()->routeIs('sanctum-manager.dashboard.logs') ? 'is-active' : '' }}"
                   @if(request()->routeIs('sanctum-manager.dashboard.logs')) aria-current="page" @endif>
                    Audit Logs
                </a>
                <a href="{{ route('sanctum-manager.dashboard.mail') }}"
                   class="sm-nav-link {{ request()->routeIs('sanctum-manager.dashboard.mail') ? 'is-active' : '' }}"
                   @if(request()->routeIs('sanctum-manager.dashboard.mail')) aria-current="page" @endif>
                    Mail &amp; Access
                </a>
            </nav>
        </div>

        <div class="sm-header-right">
            <span class="sm-badge {{ $isExtendedStorage ? 'sm-badge-info' : 'sm-badge-neutral' }}" title="Active storage mode">
                <span class="sm-badge-dot" aria-hidden="true"></span>
                {{ $storageLabel }}
            </span>

            <a href="{{ route('sanctum-manager.dashboard.mail') }}"
               class="sm-badge {{ $isMailEnabled ? 'sm-badge-success' : 'sm-badge-neutral' }}"
               title="Lifecycle mail notifications status"
               style="text-decoration: none;">
                <span class="sm-badge-dot" aria-hidden="true"></span>
                Mail: {{ $isMailEnabled ? 'On' : 'Off' }}
            </a>

            <button type="button"
                    class="sm-icon-btn"
                    id="sm-theme-toggle"
                    aria-label="Toggle color theme"
                    title="Toggle light/dark theme">
                <svg id="sm-theme-icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                </svg>
                <svg id="sm-theme-icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            </button>
        </div>
    </div>
</header>

<main class="sm-main">
    @if (session('sanctum_manager_status'))
        <div class="sm-alert sm-alert-success" role="status">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;" aria-hidden="true">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <div>{{ session('sanctum_manager_status') }}</div>
        </div>
    @endif

    @if (session('sanctum_manager_error'))
        <div class="sm-alert sm-alert-danger" role="alert">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <div>{{ session('sanctum_manager_error') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="sm-alert sm-alert-danger" role="alert">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <div>
                <strong>Please fix the following validation errors:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if (is_array(session('sanctum_manager_plain_token')))
        @php
            $plainToken = session('sanctum_manager_plain_token');
            $tokenAction = ($plainToken['action'] ?? 'issued') === 'rotated' ? 'Token Rotated' : 'Token Issued';
        @endphp
        <section class="sm-secret-box" aria-labelledby="sm-secret-heading" role="region">
            <div class="sm-secret-head">
                <h2 class="sm-secret-title" id="sm-secret-heading">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--warning-dot);" aria-hidden="true">
                        <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                    </svg>
                    <span>{{ $tokenAction }}: <span class="mono">{{ $plainToken['name'] ?? '' }}</span></span>
                </h2>
                @if (! empty($plainToken['grace_expires_at']))
                    <span class="sm-badge sm-badge-warning">
                        <span class="sm-badge-dot" aria-hidden="true"></span>
                        Previous token grace period until {{ $plainToken['grace_expires_at'] }}
                    </span>
                @endif
            </div>
            <p class="sm-secret-warning">
                Copy this plaintext API token now. For security, it is only displayed once and will never be shown again.
            </p>
            <div class="sm-secret-row">
                <div class="sm-secret-code" id="sm-plain-token-value">{{ $plainToken['token'] ?? '' }}</div>
                <button type="button"
                        class="sm-btn sm-btn-primary"
                        data-copy-target="sm-plain-token-value"
                        aria-live="polite">
                    Copy Token
                </button>
            </div>
        </section>
    @endif

    @yield('content')
</main>

<footer class="sm-footer">
    <span>Laravel Sanctum Manager Dashboard</span>
    <span>Storage: <strong class="mono">{{ $storageLabel }}</strong></span>
</footer>

<script>
    (function () {
        // Theme toggle with localStorage persistence + system preference fallback
        var root = document.documentElement;
        var toggleBtn = document.getElementById('sm-theme-toggle');
        var sunIcon = document.getElementById('sm-theme-icon-sun');
        var moonIcon = document.getElementById('sm-theme-icon-moon');

        function getEffectiveTheme() {
            if (root.dataset.theme === 'dark' || root.dataset.theme === 'light') {
                return root.dataset.theme;
            }
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        function syncThemeIcon() {
            var current = getEffectiveTheme();
            if (sunIcon && moonIcon) {
                sunIcon.style.display = current === 'dark' ? 'block' : 'none';
                moonIcon.style.display = current === 'dark' ? 'none' : 'block';
            }
            if (toggleBtn) {
                toggleBtn.setAttribute('aria-pressed', current === 'dark' ? 'true' : 'false');
                toggleBtn.setAttribute('title', current === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
            }
        }

        syncThemeIcon();

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                var next = getEffectiveTheme() === 'dark' ? 'light' : 'dark';
                root.dataset.theme = next;
                try {
                    localStorage.setItem('sanctum-manager-theme', next);
                } catch (e) {}
                syncThemeIcon();
            });
        }

        // Clipboard copy helper with fallback
        function copyText(text) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text);
            }
            return new Promise(function (resolve, reject) {
                var textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.top = '-9999px';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                try {
                    var ok = document.execCommand('copy');
                    document.body.removeChild(textarea);
                    ok ? resolve() : reject(new Error('Copy failed'));
                } catch (err) {
                    document.body.removeChild(textarea);
                    reject(err);
                }
            });
        }

        document.addEventListener('click', function (event) {
            var copyBtn = event.target.closest('[data-copy-target]');
            if (copyBtn) {
                var target = document.getElementById(copyBtn.getAttribute('data-copy-target'));
                if (target) {
                    var originalLabel = copyBtn.textContent;
                    copyText(target.textContent.trim()).then(function () {
                        copyBtn.textContent = 'Copied!';
                        setTimeout(function () {
                            copyBtn.textContent = originalLabel;
                        }, 2000);
                    }).catch(function () {
                        copyBtn.textContent = 'Copy failed';
                        setTimeout(function () {
                            copyBtn.textContent = originalLabel;
                        }, 2000);
                    });
                }
                return;
            }

            // Open native <dialog> modal
            var openTrigger = event.target.closest('[data-dialog-open]');
            if (openTrigger) {
                var dialogId = openTrigger.getAttribute('data-dialog-open');
                var dialog = document.getElementById(dialogId);
                if (dialog && typeof dialog.showModal === 'function') {
                    // Optional dynamic action/label population for Rotate & Revoke dialogs
                    var actionUrl = openTrigger.getAttribute('data-form-action');
                    if (actionUrl) {
                        var form = dialog.querySelector('form');
                        if (form) {
                            form.setAttribute('action', actionUrl);
                        }
                    }
                    var tokenName = openTrigger.getAttribute('data-token-name');
                    if (tokenName !== null) {
                        dialog.querySelectorAll('[data-bind-token-name]').forEach(function (el) {
                            el.textContent = tokenName;
                        });
                    }
                    var tokenId = openTrigger.getAttribute('data-token-id');
                    if (tokenId !== null) {
                        dialog.querySelectorAll('[data-bind-token-id]').forEach(function (el) {
                            el.textContent = '#' + tokenId;
                        });
                    }
                    dialog.showModal();
                }
                return;
            }

            // Close native <dialog> modal
            var closeTrigger = event.target.closest('[data-dialog-close]');
            if (closeTrigger) {
                var parentDialog = closeTrigger.closest('dialog');
                if (parentDialog) {
                    parentDialog.close();
                }
                return;
            }
        });

        // Backdrop click dismiss for native <dialog> elements
        document.querySelectorAll('dialog.sm-dialog').forEach(function (dialog) {
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) {
                    var rect = dialog.getBoundingClientRect();
                    var clickedInside = (
                        event.clientY >= rect.top &&
                        event.clientY <= rect.bottom &&
                        event.clientX >= rect.left &&
                        event.clientX <= rect.right
                    );
                    if (!clickedInside) {
                        dialog.close();
                    }
                }
            });
        });
    })();
</script>
@stack('scripts')
</body>
</html>
