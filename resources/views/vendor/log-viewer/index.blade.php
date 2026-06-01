<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ secure_asset('images/favicon.ico') }}">
    <title>Logs — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset(mix('app.css', 'vendor/log-viewer')) }}" rel="stylesheet" onerror="console.error('[LogViewer] app.css failed to load.')">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Montserrat', sans-serif;
            margin: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
            background: #f8fafc;
        }
        #lv-header {
            display: flex;
            align-items: center;
            gap: 12px;
            height: 56px;
            padding: 0 20px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .06);
            flex-shrink: 0;
        }
        #lv-header .back-link {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #6b7280;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 6px;
            transition: background .15s, color .15s;
        }
        #lv-header .back-link:hover {
            background: #f3f4f6;
            color: #374151;
        }
        #lv-header .divider {
            width: 1px;
            height: 20px;
            background: #e5e7eb;
        }
        #lv-header .icon-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: #004D9D;
            border-radius: 6px;
            flex-shrink: 0;
        }
        #lv-header .title {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
        }
        #lv-header .app-name {
            font-size: 12px;
            color: #9ca3af;
            font-weight: 500;
        }
        #lv-container {
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }
        #log-viewer {
            display: flex;
            height: 100%;
            max-height: 100%;
            max-width: 100%;
        }
    </style>
</head>
<body>

<div id="lv-header">
    <a href="{{ config('log-viewer.back_to_system_url', url('/')) }}" class="back-link">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Voltar
    </a>
    <div class="divider"></div>
    <div class="icon-badge">
        <svg width="14" height="14" fill="none" stroke="#ffffff" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
    </div>
    <span class="title">Log Viewer</span>
    <span class="app-name">{{ config('app.name') }}</span>
</div>

<div id="lv-container">
    <div id="log-viewer">
        <router-view></router-view>
    </div>
</div>

<script>
    window.LogViewer = @json($logViewerScriptVariables);
</script>
<script src="{{ asset(mix('app.js', 'vendor/log-viewer')) }}" onerror="console.error('[LogViewer] app.js failed to load.')"></script>
</body>
</html>
