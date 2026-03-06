<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Mensaje - Euro Rattan</title>
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
                            <h2 style="font-size: 22px; margin-top: 0; color: #2c3e50;">Tienes un nuevo mensaje</h2>
                            
                            <p style="font-size: 16px; line-height: 1.6; color: #555555; margin-bottom: 25px;">
                                Hola <strong>{{ $commission->user->name }}</strong>, nuestro equipo ha dejado un comentario o sugerencia respecto a tu encargo <strong>#{{ $commission->code }}</strong>.
                            </p>
                            
                            <table cellpadding="0" cellspacing="0" border="0" bgcolor="#fdfbf7" style="border-left: 4px solid #a0522d; margin: 0 auto; width: 100%;">
                                <tr>
                                    <td align="left" style="padding: 15px 20px;">
                                        <p style="font-size: 15px; color: #444444; margin: 0; font-style: italic;">
                                            "{!! nl2br(e($suggestion->message)) !!}"
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size: 14px; color: #7f8c8d; line-height: 1.5; margin-top: 30px;">
                                Inicia sesión en tu cuenta para responder a este mensaje y continuar con el proceso de diseño de tu mueble ideal.
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