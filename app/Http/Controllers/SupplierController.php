<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search  = $request->input('search');

        $query = Supplier::query();

        // Filtro de búsqueda extendido para buscar también por nombre de contacto
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('rif', 'LIKE', "%{$search}%")
                  ->orWhere('contact_name', 'LIKE', "%{$search}%");
            });
        }

        $suppliers = $query->orderBy('created_at', 'desc')
                           ->paginate($perPage);

        return response()->json($suppliers);
    }

    public function list()
    {
        $suppliers = Supplier::select('id', 'name', 'rif')
                             ->orderBy('name', 'asc')
                             ->get();
                             
        return response()->json($suppliers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:255',
            'rif'           => 'required|string|max:20|unique:suppliers,rif',
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:20',
            'address'       => 'nullable|string|max:500',
            'contact_name'  => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $supplier = Supplier::create($request->all());

            return response()->json([
                'message' => 'Proveedor creado correctamente',
                'data'    => $supplier
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear proveedor', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        return response()->json($supplier);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:255',
            'rif'           => [
                'required', 
                'string', 
                'max:20', 
                Rule::unique('suppliers')->ignore($supplier->id)
            ],
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:20',
            'address'       => 'nullable|string|max:500',
            'contact_name'  => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $supplier->update($request->all());

            return response()->json([
                'message' => 'Proveedor actualizado correctamente',
                'data'    => $supplier
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        if ($supplier->purchases()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el proveedor porque tiene compras registradas.'
            ], 409);
        }

        try {
            $supplier->delete();
            return response()->json(['message' => 'Proveedor eliminado correctamente']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar', 
                'error' => $e->getMessage()
            ], 500);
        }
    }
}