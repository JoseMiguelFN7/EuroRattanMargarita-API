<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Data\Blob;
use Gemini\Data\Content;
use Gemini\Data\Part;
use Gemini\Enums\Role;
use Gemini\Enums\MimeType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AiConsultantController extends Controller
{
    public function sendMessage(Request $request)
    {
        // 1. Validar la petición
        $request->validate([
            'message' => 'required_without:image|nullable|string',
            'image' => 'required_without:message|nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'session_id' => 'nullable|string',
        ]);

        $sessionId = $request->session_id ?? (string) Str::uuid();
        $cacheKey = "chat_history_{$sessionId}";

        // 2. Extraer el historial crudo de la caché
        $rawHistory = Cache::get($cacheKey, []);
        $historyForGemini = [];

        // 3. RECONSTRUIR EL HISTORIAL (Magia Multimodal)
        // Convertimos las rutas guardadas de vuelta a Base64 solo para esta petición
        foreach ($rawHistory as $turn) {
            $partsObjects = [];
            foreach ($turn['parts'] as $part) {
                if (isset($part['text'])) {
                    // Instanciamos usando el argumento nombrado "text"
                    $partsObjects[] = new Part(text: $part['text']);
                } elseif (isset($part['file_path'])) {
                    $absolutePath = storage_path('app/public/' . $part['file_path']);
                    if (file_exists($absolutePath)) {
                        $mime = mime_content_type($absolutePath);
                        $blob = new Blob(
                            mimeType: MimeType::from($mime),
                            data: base64_encode(file_get_contents($absolutePath))
                        );
                        // Instanciamos usando el argumento nombrado "inlineData"
                        $partsObjects[] = new Part(inlineData: $blob);
                    }
                }
            }
            
            // Convertimos el turno completo
            $historyForGemini[] = new Content(
                parts: $partsObjects,
                role: Role::from($turn['role'])
            );
        }

        $instrucciones = <<<EOT
            Eres el Asesor Virtual Experto de "Euro Rattan Margarita". Tu objetivo es brindar una atención al cliente excepcional, cálida, profesional y persuasiva, ayudando a los usuarios a conceptualizar sus pedidos de muebles personalizados.

            [INFORMACIÓN DE LA EMPRESA]
            - Ubicación: Avenida 4 de Mayo con Avenida Francisco Esteban Gómez. Edif. Bolimar, PB, Local 1. Porlamar 6301, Nueva Esparta, Venezuela.
            - Contacto: +58 414 7894819 (Llamadas y WhatsApp).
            - Especialidad: Venta, fabricación y reparación de muebles de rattan, así como venta de materiales de tapicería.

            [REGLAS ESTRICTAS DE COMPORTAMIENTO]
            1. EXCLUSIVIDAD DE MATERIAL: Fabricamos ÚNICAMENTE muebles de rattan. Si el cliente pide muebles con estructura principal de madera, hierro, plástico, MDF u otros metales, declina amablemente explicando que nuestra especialidad exclusiva es el rattan.
            2. USO EN EXTERIORES: El rattan natural NO es para la intemperie. Si el cliente quiere un mueble para patios descubiertos, adviértele honestamente que el sol directo y la lluvia deterioran el rattan muy rápido. Sugiere siempre su uso en interiores o terrazas techadas.
            3. SOLICITUD DE IMÁGENES: Si el cliente te da descripciones muy vagas (ej: "quiero una silla bonita") o pide un diseño muy específico y complejo, pídele proactivamente que suba una foto o imagen de referencia al chat para entender exactamente lo que desea.
            4. VENTA CRUZADA (TAPICERÍA): Recuerda que también vendemos materiales de tapicería. Siempre que asesores sobre un asiento (sillas, sofás), ofrece la personalización de los cojines y la tela para hacer el mueble más cómodo y a su gusto.
            5. CIERRE DE VENTA (CTA): Tu objetivo final es que el cliente inicie el proceso formal. Cuando notes que el cliente ya tiene clara su idea, resolviste sus dudas y está satisfecho con la asesoría, invítalo directamente a presionar el botón "Crear Pedido Personalizado" que se encuentra debajo del chat.
            6. LÍMITES DEL TEMA (SEGURIDAD): Eres un asesor de muebles, no un asistente general. Si el usuario te hace preguntas sobre programación, política, matemáticas o cualquier tema ajeno a Euro Rattan, responde amablemente que tu función es exclusivamente ayudar con la fabricación de muebles.
            7. FORMATO DE RESPUESTA: Mantén tus respuestas concisas, estructuradas y fáciles de leer. Usa viñetas si debes listar algo. Usa emojis con moderación para mantener un tono cálido y conversacional. Nunca respondas con muros de texto gigantes.
            EOT;

        // Empaquetamos el string crudo en un objeto Content oficial
        $systemContent = Content::parse($instrucciones);

        // Inicializamos el chat inyectando la personalidad primero
        $chat = Gemini::generativeModel('gemini-2.5-flash-lite')
            ->withSystemInstruction($systemContent)
            ->startChat(history: $historyForGemini);

        try {
            // 4. PREPARAR EL NUEVO MENSAJE (Dinámico)
            $messageToSend = [];
            $cacheUserParts = [];

            // a) Si el cliente escribió algo, lo agregamos
            if ($request->filled('message')) {
                $messageToSend[] = $request->message;
                $cacheUserParts[] = ['text' => $request->message];
            }

            // b) Si el cliente subió una foto, la procesamos
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('chat_temp', 'public');
                $absolutePath = storage_path('app/public/' . $path);
                
                $mime = mime_content_type($absolutePath);
                $blob = new Blob(
                    mimeType: MimeType::from($mime),
                    data: base64_encode(file_get_contents($absolutePath))
                );
                
                $messageToSend[] = $blob;
                // A la caché solo va la ruta ligera
                $cacheUserParts[] = ['file_path' => $path];
            }

            // c) EL TRUCO DEL VENDEDOR: Si mandó SOLO la foto sin texto
            if (empty($request->message) && $request->hasFile('image')) {
                // Le damos una instrucción extra a la IA para que tome la iniciativa
                $messageToSend[] = "El cliente acaba de enviarte esta imagen de referencia sin ningún texto. Analízala, dile qué te parece como experto en muebles, y pregúntale amablemente qué le gustaría fabricar basándose en esta foto.";
                // OJO: No guardamos esta instrucción en la caché para no confundir el historial.
            }

            // 5. ENVIAR A GEMINI
            $response = $chat->sendMessage($messageToSend);

            // 6. ACTUALIZAR LA CACHÉ
            $rawHistory[] = [
                'role' => 'user',
                'parts' => $cacheUserParts
            ];
            $rawHistory[] = [
                'role' => 'model',
                'parts' => [['text' => $response->text()]]
            ];

            // Guardamos por 2 horas
            Cache::put($cacheKey, $rawHistory, 7200);

            // 7. RESPONDER AL FRONTEND
            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
                'response' => $response->text()
            ]);

        } catch (\Exception $e) {
            Log::error("Error en Chatbot: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'El asesor virtual está descansando. Intenta de nuevo en unos minutos.'
            ], 500);
        }
    }
}
