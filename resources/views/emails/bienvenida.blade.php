<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenida - Comercial Valencia</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333333;
        }
        .email-container {
            max-width: 600px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e1e8ed;
        }
        .header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            padding: 35px 25px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .header p {
            margin: 6px 0 0;
            font-size: 14px;
            color: #d1e2ff;
        }
        .content {
            padding: 30px 35px;
            line-height: 1.6;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1e3c72;
            margin-bottom: 15px;
        }
        .welcome-box {
            background-color: #eef4ff;
            border-left: 4px solid #2a5298;
            padding: 16px 20px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
            font-size: 15px;
            color: #1b3560;
        }
        .credentials-card {
            background: #fafbfc;
            border: 1px solid #e5e9f0;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        .credentials-card h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 16px;
            color: #1e3c72;
            border-bottom: 1px solid #eceff4;
            padding-bottom: 8px;
        }
        .cred-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .cred-label {
            font-weight: 600;
            color: #555555;
        }
        .cred-value {
            color: #111111;
            font-family: 'Courier New', Courier, monospace;
            background: #ffffff;
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }
        .notice-box {
            background-color: #fff9db;
            border-left: 4px solid #f59f00;
            padding: 12px 18px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
            font-size: 13px;
            color: #664d03;
        }
        .footer {
            background-color: #f8fafc;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #718096;
            border-top: 1px solid #edf2f7;
        }
        .footer p {
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <h1>I.E.P Miguel Ángel Buonarroti</h1>
            <p>Comercial Valencia - Sistema de Gestión de Personal</p>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                ¡Hola, {{ $empleado->EmpleadoNombres }} {{ $empleado->EmpleadoApellidos }}!
            </div>

            <div class="welcome-box">
                <strong>Bienvenido a Comercial Valencia.</strong><br>
                Tu fecha de ingreso es el <strong>{{ $fechaIngresoFormatted }}</strong>. Te esperamos.<br>
                A partir de ese día, tus credenciales estarán habilitadas para el aplicativo web.
            </div>

            <p>
                Nos complace darte la más cordial bienvenida a nuestra institución. A continuación, te proporcionamos tus datos de acceso preliminares:
            </p>

            <div class="credentials-card">
                <h3>Datos de Acceso Institucional</h3>
                <div class="cred-item">
                    <span class="cred-label">Cargo Asignado:</span>
                    <span><strong>{{ $empleado->cargo->cargoDescripcion ?? 'Personal' }}</strong></span>
                </div>
                <div class="cred-item">
                    <span class="cred-label">Usuario / Correo:</span>
                    <span class="cred-value">{{ $empleado->EmpleadoCorreo }}</span>
                </div>
                <div class="cred-item">
                    <span class="cred-label">Contraseña Inicial:</span>
                    <span class="cred-value">{{ $passwordInicial }}</span>
                </div>
                <div class="cred-item">
                    <span class="cred-label">Habilitado desde:</span>
                    <span><strong>{{ $fechaIngresoFormatted }}</strong></span>
                </div>
            </div>

            <div class="notice-box">
                <strong>Aviso de Seguridad:</strong> Tu contraseña inicial corresponde a tu número de DNI. Por motivos de seguridad y confidencialidad, te recomendamos cambiarla al iniciar sesión por primera vez.
            </div>

            <p style="font-size: 14px; color: #4a5568;">
                Si tienes alguna consulta sobre tu incorporación, por favor comunícate con el área de Recursos Humanos o Administración.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>I.E.P Miguel Ángel Buonarroti - Comercial Valencia</strong></p>
            <p>Este es un correo automático generado por el sistema. Por favor, no responder directamente a este mensaje.</p>
            <p>&copy; {{ date('Y') }} Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
