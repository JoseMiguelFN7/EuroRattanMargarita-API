<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;


class PaymentMethodController extends Controller
{
    public function index()
    {
        return PaymentMethod::with('currency')->get()->map(function ($method) {
            // Si existe imagen, concatenamos el dominio completo
            $method->image = $method->image ? asset('storage/' . $method->image) : null;
            return $method;
        });
    }

    public function show($id)
    {
        // 1. Buscar por ID incluyendo la relación con la moneda
        $paymentMethod = PaymentMethod::with('currency')->find($id);

        // 2. Validar si no se encontró (retornar 404)
        if (!$paymentMethod) {
            return response()->json([
                'message' => 'El método de pago solicitado no existe.'
            ], 404);
        }

        $paymentMethod->image = $paymentMethod->image ? asset('storage/' . $paymentMethod->image) : null;

        return response()->json($paymentMethod);
    }

    public function store(Request $request)
    {
        // 1. Decodificar JSON del FormData
        if ($request->has('bank_details') && is_string($request->input('bank_details'))) {
            $request->merge(['bank_details' => json_decode($request->input('bank_details'), true)]);
        }
        
        $request->merge([
            'requires_proof' => filter_var($request->input('requires_proof'), FILTER_VALIDATE_BOOLEAN),
            'is_active' => filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN),
        ]);

        // 2. Validar (Ahora validamos currency_id)
        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            
            // CAMBIO CLAVE: Validamos que la moneda exista
            'currency_id'    => 'required|exists:currencies,id',
            
            'requires_proof' => 'boolean',
            'is_active'      => 'boolean',
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            
            'bank_details'               => 'nullable|array',
            'bank_details.*.label'       => 'required_with:bank_details|string',
            'bank_details.*.value'       => 'required_with:bank_details|string',
            'bank_details.*.is_copyable' => 'boolean',
        ]);

        if($validator->fails()){
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        $data = $validator->validated();

        $data['slug'] = Str::slug($data['name']);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('payment-methods', 'public');
            $data['image'] = $path;
        }

        $method = PaymentMethod::create($data);

        return response()->json($method->load('currency'), 201);
    }

    public function update(Request $request, $id)
    {
        // 1. BUSCAR EL REGISTRO MANUALMENTE
        $paymentMethod = PaymentMethod::find($id);

        if (!$paymentMethod) {
            return response()->json(['message' => 'Método de pago no encontrado'], 404);
        }

        // 2. PRE-PROCESAR DATOS (JSON y Booleanos)
        if ($request->has('bank_details') && is_string($request->input('bank_details'))) {
            $request->merge(['bank_details' => json_decode($request->input('bank_details'), true)]);
        }

        $request->merge([
            'requires_proof' => filter_var($request->input('requires_proof'), FILTER_VALIDATE_BOOLEAN),
            'is_active'      => filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN),
        ]);

        // 3. VALIDAR
        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'currency_id'    => 'required|exists:currencies,id',
            'requires_proof' => 'boolean',
            'is_active'      => 'boolean',
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            
            'bank_details'               => 'nullable|array',
            'bank_details.*.label'       => 'required_with:bank_details|string',
            'bank_details.*.value'       => 'required_with:bank_details|string',
            'bank_details.*.is_copyable' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 4. PREPARAR DATOS PARA GUARDAR
        $data = $validator->validated();
        $data['slug'] = \Illuminate\Support\Str::slug($data['name']);

        // 5. LÓGICA DE IMAGEN (El arreglo del bug)
        if ($request->hasFile('image')) {
            // A. Si existe imagen vieja, la borramos del disco
            if ($paymentMethod->image) {
                Storage::disk('public')->delete($paymentMethod->image);
            }
            
            // B. Guardamos la nueva y asignamos la ruta al array $data
            $path = $request->file('image')->store('payment-methods', 'public');
            $data['image'] = $path;
        } else {
            // C. IMPORTANTE: Si no envían imagen nueva, quitamos el campo del array
            // Esto evita que Laravel intente actualizar la columna 'image' a null.
            unset($data['image']);
        }

        // 6. ACTUALIZAR BASE DE DATOS
        $paymentMethod->update($data);

        // 7. RETORNAR RESPUESTA (Con moneda e imagen actualizada si tienes el accessor)
        return response()->json($paymentMethod->load('currency'));
    }

    public function destroy($id)
    {
        // 1. Buscar el método de pago por ID
        $paymentMethod = PaymentMethod::find($id);

        // 2. Validar que exista
        if (!$paymentMethod) {
            return response()->json(['message' => 'Método de pago no encontrado'], 404);
        }

        // 3. Eliminar la imagen del almacenamiento si existe
        if ($paymentMethod->image) {
            Storage::disk('public')->delete($paymentMethod->image);
        }
        
        // 4. Eliminar el registro de la base de datos
        $paymentMethod->delete();

        return response()->json(['message' => 'Método eliminado correctamente']);
    }
}
