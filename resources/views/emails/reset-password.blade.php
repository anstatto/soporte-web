<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecimiento de contraseña</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #0000ff;
            padding: 30px 0;
            text-align: center;
        }
        .logo {
            width: 100px;
            height: auto;
        }
        .content {
            padding: 40px;
            text-align: center;
        }
        h1 {
            color: #0000ff;
            font-size: 28px;
            margin-bottom: 20px;
        }
        p {
            color: #555555;
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 20px;
        }
        .button {
            display: inline-block;
            background-color: #0000ff;
            color: #ffffff;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            font-size: 18px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
            letter-spacing: 1px;
        }
        .button:hover {
            background-color: #3333ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            color: #ffffff;
        }
        .footer {
            background-color: #f8f8f8;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #888888;
        }
        .highlight {
            color: #0000ff;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ $message->embed(public_path('images/LogoMono.png')) }}" alt="RM Consuegra SRL Logo" class="logo">
        </div>
        <div class="content">
            <h1>¡Hola {{ $notifiable->name }}!</h1>
            <p>Hemos recibido una solicitud de restablecimiento de contraseña para tu cuenta.</p>
            <a href="{{ $url }}" class="button">Restablecer Contraseña</a>
            <p>Este enlace mágico expirará en <span class="highlight">60 minutos</span>. ¡Úsalo sabiamente!</p>
            <p>Si no solicitaste este cambio, puedes ignorar este mensaje. Tu cuenta está segura.</p>
            <p>¡Que tengas un día increíble!<br><strong>El equipo de RM Consuegra SRL Soporte</strong></p>
        </div>
        <div class="footer">
            ¿Problemas con el botón? Copia y pega este enlace en tu navegador:<br>
            <a href="{{ $url }}" style="color: #0000ff;">{{ $url }}</a>
        </div>
    </div>
</body>
</html>
