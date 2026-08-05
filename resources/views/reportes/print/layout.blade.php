<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Reporte de Soportes')</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #1a222c;
            line-height: 1.45;
            margin: 0;
            padding: 24px;
        }
        .header {
            border-bottom: 2px solid #1E4E79;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .header h1 {
            margin: 0 0 4px;
            font-size: 18px;
            color: #0B1F3A;
        }
        .header .meta {
            color: #5B6B7C;
            font-size: 10px;
        }
        h2 {
            font-size: 13px;
            color: #1E4E79;
            margin: 18px 0 8px;
            border-bottom: 1px solid #d8dee6;
            padding-bottom: 4px;
        }
        h3 {
            font-size: 12px;
            margin: 12px 0 6px;
            color: #0B1F3A;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        th, td {
            border: 1px solid #d8dee6;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #e8eef4;
            color: #0B1F3A;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        tr:nth-child(even) td { background: #f7f9fb; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
            color: #fff;
        }
        .kpi-row { width: 100%; margin-bottom: 14px; }
        .kpi {
            display: inline-block;
            width: 23%;
            margin-right: 1%;
            vertical-align: top;
            border: 1px solid #d8dee6;
            border-radius: 6px;
            padding: 10px;
            background: #f3f6f9;
        }
        .kpi .label { font-size: 9px; color: #5B6B7C; text-transform: uppercase; }
        .kpi .value { font-size: 16px; font-weight: bold; color: #0B1F3A; margin-top: 2px; }
        .bar-wrap { margin: 4px 0 8px; }
        .bar-label { font-size: 10px; margin-bottom: 2px; }
        .bar-track {
            background: #e8eef4;
            height: 12px;
            border-radius: 4px;
            overflow: hidden;
        }
        .bar-fill {
            height: 12px;
            background: #2F6FAD;
        }
        .muted { color: #5B6B7C; }
        .footer {
            margin-top: 24px;
            padding-top: 8px;
            border-top: 1px solid #d8dee6;
            font-size: 9px;
            color: #5B6B7C;
        }
        ul { margin: 0 0 10px; padding-left: 16px; }
        li { margin-bottom: 2px; }
        .section { page-break-inside: avoid; }
    </style>
</head>
<body>
    <div class="header">
        <h1>@yield('title', 'Reporte de Soportes')</h1>
        <div class="meta">
            Período: {{ $fechaInicio?->format('d/m/Y H:i') ?? '—' }} — {{ $fechaFin?->format('d/m/Y H:i') ?? '—' }}
            &nbsp;·&nbsp; Generado: {{ now()->format('d/m/Y H:i') }}
            @if(!empty($appName))
                &nbsp;·&nbsp; {{ $appName }}
            @endif
        </div>
    </div>

    @yield('content')

    <div class="footer">
        {{ $reportFooter ?? 'Documento generado automáticamente. Confidencial — uso interno.' }}
    </div>
</body>
</html>
