<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Verificación de Pago - Euro Rattan</title>
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
                            
                            @if($payment->status === 'verified')
                                <h2 style="font-size: 22px; margin-top: 0; color: #27ae60;">¡Tu pago ha sido Aprobado!</h2>
                            @else
                                <h2 style="font-size: 22px; margin-top: 0; color: #c0392b;">Aviso de Pago Rechazado</h2>
                            @endif

                            <p style="font-size: 16px; line-height: 1.6; color: #555555; margin-bottom: 25px;">
                                Hola <strong>{{ $payment->order->user->name }}</strong>, hemos procesado la revisión de un pago específico asociado a tu orden <strong>#{{ $payment->order->code }}</strong>.
                            </p>

                            <div style="background-color: #f8f9fa; border-left: 4px solid {{ $payment->status === 'verified' ? '#27ae60' : '#c0392b' }}; padding: 15px 20px; text-align: left; margin-bottom: 30px;">
                                <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #333;">Detalles del Pago Revisado:</h3>
                                <p style="margin: 0 0 8px 0; font-size: 14px;"><strong>Monto:</strong> {{ $payment->currency ? $payment->currency->code : 'USD' }} {{ number_format($payment->amount, 2, ',', '.') }}</p>
                                <p style="margin: 0 0 8px 0; font-size: 14px;"><strong>Método:</strong> {{ $payment->paymentMethod ? $payment->paymentMethod->name : 'N/A' }}</p>
                                <p style="margin: 0 0 8px 0; font-size: 14px;"><strong>Referencia:</strong> {{ $payment->reference_number ?: 'N/A' }}</p>
                                <p style="margin: 0; font-size: 14px;"><strong>Fecha del pago:</strong> {{ $payment->created_at->format('d/m/Y h:i A') }}</p>
                            </div>

                            <div style="background-color: #e9f2fa; border-radius: 6px; padding: 20px; text-align: center;">
                                <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #2c3e50;">¿Qué significa esto para tu orden?</h3>
                                <p style="margin: 0; font-size: 15px; color: #34495e; font-weight: bold;">
                                    {{ $orderMessage }}
                                </p>
                            </div>

                            @if($payment->status === 'rejected')
                                <p style="font-size: 14px; color: #7f8c8d; line-height: 1.5; margin-top: 30px;">
                                    * Te recomendamos verificar los datos de la transferencia de este pago específico y volver a reportarlo desde la plataforma si consideras que hubo un error al ingresarlo.
                                </p>
                            @endif
                            
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