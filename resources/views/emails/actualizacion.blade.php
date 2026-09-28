<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualización de Datos - I.E.P Miguel Ángel Buonarroti</title>
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
            padding: 30px 25px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
        }
        .header p {
            margin: 5px 0 0;
            font-size: 13px;
            color: #d1e2ff;
        }
        .content {
            padding: 30px 35px;
            line-height: 1.6;
        }
        .greeting {
            font-size: 17px;
            font-weight: 600;
            color: #1e3c72;
            margin-bottom: 12px;
        }
        .info-box {
            background-color: #f0f7ff;
            border-left: 4px solid #0066cc;
            padding: 14px 18px;
            margin: 18px 0;
            border-radius: 0 6px 6px 0;
            font-size: 14px;
            color: #0c4a6e;
        }
        .changes-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }
        .changes-table th {
            background-color: #f1f5f9;
            color: #334155;
            text-align: left;
            padding: 10px 12px;
            border-bottom: 2px solid #cbd5e1;
            font-weight: 600;
        }
        .changes-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .old-value {
            color: #e53e3e;
            text-decoration: line-through;
        }
        .new-value {
            color: #2f855a;
            font-weight: 600;
        }
        .alert-box {
            background-color: #fff9db;
            border-left: 4px solid #f59f00;
            padding: 12px 18px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
            font-size: 13px;
            color: #744210;
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
            <p>Notificación de Modificación de Datos</p>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                Estimado(a) {{ $empleado->EmpleadoNombres }} {{ $empleado->EmpleadoApellidos }},
            </div>

            <div class="info-box">
                Te informamos que se han actualizado tus datos personales en el sistema institucional. A continuación, te mostramos el detalle de los cambios efectuados:
            </div>

            <table class="changes-table">
                <thead>
                    <tr>
                        <th>Campo Modificado</th>
                        <th>Valor Anterior</th>
                        <th>Nuevo Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cambios as $cambio)
                        <tr>
                            <td><strong>{{ $cambio['campo'] }}</strong></td>
                            <td class="old-value">{{ $cambio['anterior'] ?? 'N/A' }}</td>
                            <td class="new-value">{{ $cambio['nuevo'] ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="alert-box">
                <strong>¿No solicitaste este cambio?</strong> Si no reconoces estas modificaciones o consideras que se trata de un error, por favor contacta de inmediato con el área de Administración o Recursos Humanos.
            </div>

            <p style="font-size: 13px; color: #64748b;">
                Fecha y hora de actualización: <strong>{{ now()->format('d/m/Y H:i:s') }}</strong>
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>I.E.P Miguel Ángel Buonarroti - Comercial Valencia</strong></p>
            <p>Este es un correo de notificación automática. Por favor no responder a esta dirección.</p>
            <p>&copy; {{ date('Y') }} Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
