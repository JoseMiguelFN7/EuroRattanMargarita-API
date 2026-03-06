<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Encargo Cancelado - Euro Rattan</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f4f7f6">
        <tr>
            <td align="center" style="padding: 40px 10px;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="max-width: 600px; border-radius: 8px; overflow: hidden; border: 1px solid #eaeaec;">
                    <tr>
                        <td bgcolor="#a0522d" align="center" style="padding: 30px 20px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: normal; letter-spacing: 2px;">EURO RATTAN MARGARITA</h1>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding: 40px 30px; color: #333333;">
                            <h2 style="font-size: 22px; margin-top: 0; color: #e74c3c;">Actualización de tu Encargo</h2>
                            
                            <p style="font-size: 16px; line-height: 1.6; color: #555555; margin-bottom: 25px;">
                                Hola <strong>{{ $commission->user->name }}</strong>, te escribimos para confirmar que tu solicitud de encargo <strong>#{{ $commission->code }}</strong> ha sido cancelada en nuestro sistema y el proyecto no avanzará a la fase de fabricación.
                            </p>
                            
                            <table cellpadding="0" cellspacing="0" border="0" bgcolor="#fff3f3" style="border: 2px dashed #ffbaba; border-radius: 8px; margin: 0 auto; width: 100%;">
                                <tr>
                                    <td align="center" style="padding: 20px;">
                                        <p style="font-size: 18px; color: #2c3e50; margin: 0;">Estado del Encargo:</p>
                                        <p style="font-size: 24px; font-weight: bold; color: #e74c3c; margin: 10px 0 0 0;">CANCELADO</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size: 14px; color: #7f8c8d; line-height: 1.5; margin-top: 30px;">
                                Si tienes alguna duda sobre esta actualización, crees que se trata de un error, o deseas iniciar un nuevo proyecto con nosotros, nuestro equipo de atención al cliente está a tu entera disposición.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#f9f9f9" align="center" style="padding: 20px 30px; border-top: 1px solid #eeeeee;">
                            <p style="margin: 0; font-size: 12px; color: #999999;">
                                &copy; {{ date('Y') }} Euro Rattan Margarita, C.A.<br>Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>