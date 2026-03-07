<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Pago Reportado - Euro Rattan</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f7f6;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f4f7f6" style="padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="max-width: 600px; border-radius: 8px; overflow: hidden; border: 1px solid #eaeaec;">
                    <tr>
                        <td bgcolor="#a0522d" align="center" style="padding: 30px 20px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: normal; letter-spacing: 2px;">EURO RATTAN MARGARITA</h1>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding: 40px 30px; color: #333333;">
                            
                            <h2 style="font-size: 22px; margin-top: 0; color: #f39c12;">Pago Pendiente de Verificación</h2>
                            
                            <p style="font-size: 16px; line-height: 1.6; color: #555555; margin-bottom: 25px;">
                                Hola equipo, el cliente <strong>{{ $payment->order->user->name ?? 'Desconocido' }}</strong> ha reportado un nuevo pago asociado a la orden <strong>#{{ $payment->order->code ?? 'N/A' }}</strong>.
                            </p>

                            <table cellpadding="0" cellspacing="0" border="0" bgcolor="#f8f9fa" style="border: 1px solid #bdc3c7; border-radius: 8px; margin: 0 auto; width: 100%; text-align: left;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="margin: 0 0 10px 0; font-size: 14px;"><strong>Monto:</strong> {{ $payment->currency ? $payment->currency->code : 'USD' }} {{ number_format($payment->amount, 2, ',', '.') }}</p>
                                        <p style="margin: 0 0 10px 0; font-size: 14px;"><strong>Método:</strong> {{ $payment->paymentMethod ? $payment->paymentMethod->name : 'N/A' }}</p>
                                        <p style="margin: 0; font-size: 14px;"><strong>Referencia:</strong> {{ $payment->reference_number ?: 'N/A' }}</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size: 14px; color: #7f8c8d; line-height: 1.5; margin-top: 30px;">
                                Por favor, ingresa al panel administrativo para conciliar los datos con el banco y proceder a aprobar o rechazar este reporte.
                            </p>
                            
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#f9f9f9" align="center" style="padding: 20px 30px; border-top: 1px solid #eeeeee;">
                            <p style="margin: 0; font-size: 12px; color: #999999;">
                                &copy; {{ date('Y') }} Euro Rattan Margarita, C.A. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>