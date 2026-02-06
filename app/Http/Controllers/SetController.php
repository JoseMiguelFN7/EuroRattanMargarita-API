<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Set;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SetController extends Controller
{
    //Obtener todos los juegos
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        // 1. CARGA DE RELACIONES
        // Necesitamos 'materials.materialTypes' para distinguir Insumo vs Tapicería
        $sets = Set::with([
            'setType', 
            'product.images',
            'furnitures.materials.materialTypes', 
            'furnitures.labors',
            'furnitures.product.stocks'
        ])->paginate($perPage);

        // 2. TRANSFORMACIÓN
        $sets->through(function ($set) {
            
            // --- A. LLAMADO AL MODELO PARA CÁLCULOS ---
            $precios = $set->calcularPrecios();
            
            $set->pvp_natural = $precios['pvp_natural'];
            $set->pvp_color = $precios['pvp_color'];

            // B. DISPONIBILIDAD DE COLORES
            $set->available_colors = $set->calcularColoresDisponibles();

            // --- C. LIMPIEZA VISUAL ---
            $product = $set->product;
            if ($product) {
                // Imagen para la tabla
                if ($product->images->isNotEmpty()) {
                    $product->image = asset('storage/' . $product->images->first()->url);
                } else {
                    $product->image = null;
                }
                // Ocultamos datos pesados del producto
                $product->makeHidden(['created_at', 'updated_at', 'description', 'sell', 'images', 'stocks']);
            }

            if ($set->setType) $set->setType->makeHidden(['created_at', 'updated_at']);

            // Ocultamos la lógica interna del Set
            $set->makeHidden(['furnitures', 'product_id', 'set_types_id', 'created_at', 'updated_at']);

            return $set;
        });

        return response()->json($sets);
    }

    //Obtener juego por ID
    public function show($id)
    {
        $set = Set::with(['setType', 'product.images', 'product.colors'])->find($id); //Busca el juego por ID

        if(!$set){
            return response()->json(['message'=>'Juego no encontrado'], 404);
        }

        $product = $set->product;

        $product->images = $product->images->map(function ($image) {
            return asset('storage/' . $image->url); // Generar las URLs completas de las imágenes
        });

        return response()->json($set);
    }

    public function showCod($code)
    {
        // 1. CARGA DE RELACIONES
        // Agregamos 'furnitures.product' para saber el nombre de las sillas/mesas que componen el juego
        // Agregamos 'furnitures.furnitureType' por si necesitas mostrar la categoría
        $set = Set::with([
                'setType', 
                'product.images', 
                'furnitures', // Foto del componente
            ])
            ->whereHas('product', function ($query) use ($code) {
                $query->where('code', $code);
            })->first();

        if (!$set) {
            return response()->json(['message' => 'Juego no encontrado'], 404);
        }

        // 2. LIMPIEZA DEL JUEGO (PADRE)
        $set->makeHidden(['created_at', 'updated_at', 'product_id', 'set_types_id']);
        if ($set->setType) $set->setType->makeHidden(['created_at', 'updated_at']);

        // 3. LIMPIEZA DEL PRODUCTO PRINCIPAL
        if ($set->product) {
            $set->product->images->each(function ($image) {
                $image->url = asset('storage/' . $image->url);
                $image->makeHidden(['created_at', 'updated_at', 'product_id']);
            });
            
            $set->product->makeHidden(['created_at', 'updated_at']); 
        }

        // 4. LIMPIEZA DE LOS MUEBLES (HIJOS)
        $set->furnitures->each(function ($furniture) {

            // A. Limpiar la tabla intermedia (Pivot)
            // Solo dejamos 'quantity', ocultamos los IDs redundantes
            if ($furniture->pivot) {
                $furniture->pivot->makeHidden(['set_id', 'furniture_id', 'created_at', 'updated_at']);
            }

            // B. Ocultar datos técnicos del mueble que no son relevantes para el Set
            $furniture->makeHidden([
                'created_at', 
                'updated_at', 
                'product_id', 
                'furniture_type_id',
                'profit_per',
                'paint_per',
                'labor_fab_per'
            ]);
        });

        return response()->json($set);
    }

    //Obtener una cantidad especifica de juegos en orden aleatorio
    public function rand($quantity)
    {
        // Validar que el parámetro es un número entero positivo
        if (!is_numeric($quantity) || $quantity <= 0) {
            return response()->json([
                'error' => 'La cantidad debe ser un número entero positivo.'
            ], 400);
        }

        // Obtener registros aleatorios
        $sets = Product::with(['setType', 'product.images'])
            ->whereHas('product', function ($query) {
                $query->where('sell', true); // Filtrar por 'sell = true'
            })
            ->inRandomOrder() // Seleccionar en orden aleatorio
            ->take($quantity) // Limitar la cantidad
            ->get()
            ->map(function ($set) {
                // Obtener solo la primera imagen del producto, si existe
                $set->product->image = $set->product->images->first() 
                    ? asset('storage/' . $set->product->images->first()->url) 
                    : null;
                return $set;
            });

        return response()->json($sets);
    }

    //Crear juego
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:products',
            'description' => 'required|string|max:500',
            'setType_id' => 'required|integer|exists:set_types,id',
            'furnitures' => 'required|array',
            'furnitures.*.id' => 'integer|exists:furnitures,id',
            'furnitures.*.quantity' => 'required|numeric|min:0',
            'profit_per' => 'required|numeric|min:0',
            'paint_per' => 'required|numeric|min:0',
            'labor_fab_per' => 'required|numeric|min:0',
            'sell' => 'required|boolean',
            'discount' => 'required|numeric|min:0|max:100',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp,gif|max:2048',
        ]);

        //enviar error si es necesario
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Crear producto
            $product = Product::create([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'sell' => $request->sell
            ]);

            // Crear juego
            $set = Set::create([
                'product_id' => $product->id,
                'set_types_id' => $request->setType_id,
                'profit_per' => $request->profit_per,
                'paint_per' => $request->paint_per,
                'labor_fab_per' => $request->labor_fab_per
            ]);

            //procesado de imagen
            if ($request->hasFile('images')) {
                $files = $request->file('images');
                app(ProductImageController::class)->uploadImages($product->id, $files);
            }

            // Procesar muebles
            if ($request->has('furnitures')) {
                $furnituresData = collect($request->furnitures)->mapWithKeys(function ($furniture) {
                    return [$furniture['id'] => ['quantity' => $furniture['quantity']]];
                })->toArray();
                
                $set->furnitures()->sync($furnituresData);
            }

            // Confirmar la transacción
            DB::commit();

            $set->load(['product.images', 'furnitures']);

            return response()->json($set, 201);

        } catch (\Exception $e) {
            // Revertir la transacción en caso de error
            DB::rollback();
            
            // Devolver el error
            return response()->json([
                'message' => 'Error al guardar el juego.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $set = Set::find($id);

        if (!$set) {
            return response()->json(['message' => 'Juego no encontrado'], 404);
        }

        $product = $set->product;

        // 1. VALIDACIÓN
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            // Importante: Ignorar el ID actual para la validación de unique
            'code' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('products', 'code')->ignore($product->id)],
            'description' => 'sometimes|required|string|max:500',
            'setType_id' => 'sometimes|required|integer|exists:set_types,id', // Ojo con el nombre de la tabla
            'furnitures' => 'sometimes|required|array',
            'furnitures.*.id' => 'required|integer|exists:furnitures,id',
            'furnitures.*.quantity' => 'required|numeric|min:0',
            'profit_per' => 'sometimes|required|numeric|min:0',
            'paint_per' => 'sometimes|required|numeric|min:0',
            'labor_fab_per' => 'sometimes|required|numeric|min:0',
            'sell' => 'sometimes|required|boolean',
            'discount' => 'sometimes|required|numeric|min:0|max:100',
            // Imágenes (Lógica Nueva)
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'kept_images' => 'nullable|array',
            'kept_images.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // 2. ACTUALIZACIÓN DE DATOS BÁSICOS
            $product->fill($request->only([
                'name', 'code', 'description', 'sell', 'discount'
            ]));

            // Mapeo manual de setType_id (Request) a set_types_id (DB)
            if ($request->has('setType_id')) {
                $set->set_types_id = $request->setType_id;
            }

            $set->fill($request->only([
                'profit_per', 'paint_per', 'labor_fab_per'
            ]));

            // 3. GESTIÓN DE IMÁGENES (KEPT IMAGES LOGIC)
            
            // A. Borrar imágenes que NO están en kept_images
            if ($request->has('kept_images')) {
                $keptIds = $request->input('kept_images');
                if (!is_array($keptIds)) $keptIds = [];

                // Buscamos las imágenes del producto que NO están en la lista de IDs a mantener
                $imagesToDelete = $product->images()->whereNotIn('id', $keptIds)->get();

                foreach ($imagesToDelete as $img) {
                    // Borrar archivo físico
                    if (Storage::disk('public')->exists($img->url)) {
                        Storage::disk('public')->delete($img->url);
                    }
                    // Borrar registro
                    $img->delete();
                }
            }

            // B. Subir nuevas imágenes
            if ($request->hasFile('images')) {
                $files = $request->file('images');
                app(ProductImageController::class)->uploadImages($product->id, $files);
            }

            // 4. SINCRONIZACIÓN DE MUEBLES (COMPONENTES)
            if ($request->has('furnitures')) {
                $furnituresData = collect($request->furnitures)->mapWithKeys(function ($furniture) {
                    return [$furniture['id'] => ['quantity' => $furniture['quantity']]];
                })->toArray();
                
                $set->furnitures()->sync($furnituresData);
            }

            // Guardamos cambios
            $product->save();
            $set->save();

            DB::commit();

            // 5. RESPUESTA ACTUALIZADA
            // Cargamos relaciones para que el frontend actualice la vista inmediatamente
            $set->load(['product.images', 'furnitures', 'setType']);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'message' => 'Error al actualizar el juego.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }

        return response()->json($set);
    }

    //Eliminar juego
    public function destroy($id){
        DB::beginTransaction();

        try{
            $set = Set::find($id);

            if(!$set){
                return response()->json(['message' => 'Juego no encontrado'], 404);
            }

            $product = $set->product;

            $set->delete();
            if ($product) {
                // Llamar al controlador de colores para eliminar los colores asociadas
                app(ColorController::class)->detachAndDeleteOrphanColors($product->id);

                // Llamar al controlador de imágenes para eliminar las imágenes asociadas
                app(ProductImageController::class)->deleteImages($product->id);
                
                $product->delete();
            }

            // Confirmar la transacción
            DB::commit();

            return response()->json(['message' => 'Juego eliminado correctamente'], 200);

        } catch (\Exception $e){
            // Deshacer la transacción si ocurre un error
            DB::rollBack();

            // Devolver el error
            return response()->json([
                'message' => 'Error al eliminar el juego.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
