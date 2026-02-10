<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Furniture;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class FurnitureController extends Controller
{
    // Obtener todos los muebles
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 8);

        $furnitures = Furniture::with([
            'furnitureType', 
            'product.images', 
            'product.stocks',
            'materials.materialTypes',
            'labors'
        ])
        ->paginate($perPage);

        // 2. TRANSFORMACIÓN
        $furnitures = $furnitures->through(function ($furniture) {
            
            $product = $furniture->product;

            $product->images->each(function ($image) {
                // 1. Generamos la URL completa
                $image->url = asset('storage/' . $image->url);
                // 2. Limpiamos la basura de cada objeto imagen
                $image->makeHidden(['created_at', 'updated_at', 'product_id']);
            });

            // --- Precios ---
            $precios = $furniture->calcularPrecios();
            $furniture->pvp_natural = $precios['pvp_natural'];
            $furniture->pvp_color = $precios['pvp_color'];

            // --- Stock ---
            $product->stocks->makeHidden(['productID', 'productCode']);


            $product->makeHidden(['created_at', 'updated_at']);

            $furniture->furnitureType->makeHidden(['created_at', 'updated_at']);
            $furniture->makeHidden(['materials', 'labors', 'product_id', 'furniture_type_id', 'created_at', 'updated_at']);

            return $furniture;
        });

        return response()->json($furnitures);
    }

    public function indexSell(Request $request)
    {
        $perPage = $request->input('per_page', 8);

        $furnitures = Furniture::with([
            'furnitureType', 
            'product.images', 
            'product.stocks',
            'materials.materialTypes',
            'labors'
        ])->whereHas('product', function ($query) {
            $query->where('sell', true);
        })
        ->paginate($perPage);

        // 2. TRANSFORMACIÓN
        $furnitures = $furnitures->through(function ($furniture) {
            
            $product = $furniture->product;

            $product->images->each(function ($image) {
                // 1. Generamos la URL completa
                $image->url = asset('storage/' . $image->url);
                // 2. Limpiamos la basura de cada objeto imagen
                $image->makeHidden(['created_at', 'updated_at', 'product_id']);
            });

            // --- Precios ---
            $precios = $furniture->calcularPrecios();
            $furniture->pvp_natural = $precios['pvp_natural'];
            $furniture->pvp_color = $precios['pvp_color'];

            // --- Stock ---
            $product->stocks->makeHidden(['productID', 'productCode']);


            $product->makeHidden(['created_at', 'updated_at']);

            $furniture->furnitureType->makeHidden(['created_at', 'updated_at']);
            $furniture->makeHidden(['materials', 'labors', 'product_id', 'furniture_type_id', 'created_at', 'updated_at']);

            return $furniture;
        });

        return response()->json($furnitures);
    }

    // Obtener todos los muebles a la venta sin paginación
    public function listAll()
    {
        // 1. CONSULTA OPTIMIZADA
        $furnitures = Furniture::whereHas('product', function ($query) {
                // FILTRO: Solo productos marcados para la venta (sell = 1/true)
                $query->where('sell', true); 
            })
            ->with([
                'furnitureType', 
                'materials.materialTypes',
                'labors',
                'product.stocks'
            ])->get();

        // 2. TRANSFORMACIÓN Y COSTOS
        $furnitures->map(function ($furniture) {
            
            // --- A. CÁLCULO DE COSTOS BASE ---
            $costSupplies = 0;    // Para tipos "Insumo"
            $costUpholstery = 0;  // Para tipos "Tapicería"

            foreach ($furniture->materials as $material) {
                // Calculamos el costo de este material en este mueble
                $subtotal = $material->price * $material->pivot->quantity;

                // CLASIFICACIÓN EXACTA POR NOMBRE
                // Verificamos si la colección de tipos contiene el nombre específico
                if ($material->materialTypes->contains('name', 'Tapicería')) {
                    $costUpholstery += $subtotal;
                } 
                elseif ($material->materialTypes->contains('name', 'Insumo')) {
                    $costSupplies += $subtotal;
                }
                // Si tienes otros tipos (ej: "Madera"), puedes agregar más elseif o sumarlos a supplies por defecto.
            }

            // 2. Costo de Mano de Obra
            $costLabor = $furniture->labors->reduce(function ($carry, $labor) {
                return $carry + ($labor->daily_pay * $labor->pivot->days);
            }, 0);

            // --- B. ASIGNACIÓN AL OBJETO JSON ---
            $furniture->costs = [
                'supplies'    => round($costSupplies, 2),   // Total Insumos
                'upholstery'  => round($costUpholstery, 2), // Total Tapicería
                'labor'       => round($costLabor, 2),      // Total Mano de Obra
            ];

            // --- C. LIMPIEZA VISUAL ---
            $product = $furniture->product;

            if ($product) {
                $product->makeHidden(['sell', 'description', 'discount', 'created_at', 'updated_at']);
                
                // Limpieza de stock (si la vista ya trae hex y name, ocultamos lo técnico)
                if ($product->stocks) {
                    $product->stocks->makeHidden(['productID', 'productCode']);
                }
            }

            if ($furniture->furnitureType) {
                $furniture->furnitureType->makeHidden(['created_at', 'updated_at']);
            }

            // Ocultamos la "receta" interna para no enviar un JSON gigante
            $furniture->makeHidden([
                'materials', 
                'labors', 
                'product_id', 
                'furniture_type_id', 
                'created_at', 
                'updated_at',
                'profit_per', 
                'paint_per', 
                'labor_fab_per' 
            ]);

            return $furniture;
        });

        return response()->json($furnitures);
    }

    //Obtener mueble por ID
    public function show($id)
    {
        $furniture = Furniture::with(['furnitureType', 'product.images', 'product.colors'])->find($id); //Busca el mueble por ID

        if(!$furniture){
            return response()->json(['message'=>'Mueble no encontrado'], 404);
        }

        $product = $furniture->product;

        $product->images = $product->images->map(function ($image) {
            return asset('storage/' . $image->url); // Generar las URLs completas de las imágenes
        });

        return response()->json($furniture);
    }

    //Obtener mueble por código
    public function showCod($cod)
    {
        // 1. CARGA DE LA "RECETA"
        // Cargamos materials y labors porque el formulario necesita saber 
        // qué ingredientes componen este mueble actualmente.
        $furniture = Furniture::whereHas('product', function ($query) use ($cod) {
                $query->where('code', $cod);
            })
            ->with([
                'furnitureType', 
                'product.images', 
                'product.colors',
                'materials',
                'labors',
            ])
            ->first();

        if (!$furniture) {
            return response()->json(['message' => 'Mueble no encontrado'], 404);
        }

        // 2. LIMPIEZA DEL PADRE (PRODUCTO)
        if ($furniture->product) {
            $prod = $furniture->product;
            
            // Datos básicos
            $prod->makeHidden(['created_at', 'updated_at']); 

            // Imágenes (Array de objetos con URL absoluta)
            $prod->images->each(function ($image) {
                $image->url = asset('storage/' . $image->url);
                $image->makeHidden(['created_at', 'updated_at', 'product_id']);
            });

            // Colores (Solo limpieza visual)
            if ($prod->colors) {
                $prod->colors->makeHidden(['pivot', 'created_at', 'updated_at']);
            }
        }

        // 3. LIMPIEZA DEL HIJO (MUEBLE)
        // Ocultamos fechas y FKs que no sirven en el form
        $furniture->makeHidden(['product_id', 'furniture_type_id', 'created_at', 'updated_at']);

        // 4. PREPARACIÓN DE INGREDIENTES (Materiales y Mano de Obra)
        // Aquí NO ocultamos el 'pivot', porque ahí vive la 'cantidad' que necesitas poner en el input.
        
        if ($furniture->materials) {
            // Solo ocultamos fechas del material, pero dejamos el ID, nombre, precio y el PIVOT
            $furniture->materials->makeHidden(['created_at', 'updated_at', 'profit_per', 'paint_per', 'labor_fab_per']);
            
            // Opcional: Si quieres limpiar el pivot visualmente (quitar timestamps del pivot)
            
            $furniture->materials->each(function($mat){
                if($mat->pivot) $mat->pivot->makeHidden(['created_at', 'updated_at']);
            });
            
        }

        if ($furniture->labors) {
            $furniture->labors->makeHidden(['created_at', 'updated_at']);
        }

        // Tipo de Mueble (Solo nombre e ID para el select)
        if ($furniture->furnitureType) {
            $furniture->furnitureType->makeHidden(['created_at', 'updated_at']);
        }

        return response()->json($furniture);
    }

    //Obtener una cantidad especifica de muebles en orden aleatorio
    public function rand($quantity)
    {
        // Validar que el parámetro es un número entero positivo
        if (!is_numeric($quantity) || $quantity <= 0) {
            return response()->json([
                'error' => 'La cantidad debe ser un número entero positivo.'
            ], 400);
        }

        // Obtener registros aleatorios
        $furnitures = Product::with(['furnitureType', 'product.images'])
            ->whereHas('product', function ($query) {
                $query->where('sell', true); // Filtrar por 'sell = true'
            })
            ->inRandomOrder() // Seleccionar en orden aleatorio
            ->take($quantity) // Limitar la cantidad
            ->get()
            ->map(function ($furniture) {
                // Obtener solo la primera imagen del producto, si existe
                $furniture->product->image = $furniture->product->images->first() 
                    ? asset('storage/' . $furniture->product->images->first()->url) 
                    : null;
                return $furniture;
            });

        return response()->json($furnitures);
    }

    //Crear mueble
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:products',
            'description' => 'required|string|max:500',
            'furnitureType_id' => 'required|integer|exists:furniture_types,id',
            'materials' => 'required|array',
            'materials.*.id' => 'required|integer|exists:materials,id',
            'materials.*.quantity' => 'required|numeric|min:0',
            'labors' => 'required|array',
            'labors.*.id' => 'required|integer|exists:labors,id',
            'labors.*.days' => 'required|numeric|min:0',
            'profit_per' => 'required|numeric|min:0',
            'paint_per' => 'required|numeric|min:0',
            'labor_fab_per' => 'required|numeric|min:0',
            'sell' => 'required|boolean',
            'discount' => 'required|numeric|min:0|max:100',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'colors' => 'required|array',
            'colors.*' => 'integer|exists:colors,id'
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
                'sell' => $request->sell,
                'discount' => $request->discount
            ]);

            // Crear mueble
            $furniture = Furniture::create([
                'product_id' => $product->id,
                'furniture_type_id' => $request->furnitureType_id,
                'profit_per' => $request->profit_per,
                'paint_per' => $request->paint_per,
                'labor_fab_per' => $request->labor_fab_per
            ]);

            //procesado de imagen
            if ($request->hasFile('images')) {
                $files = $request->file('images');
                app(ProductImageController::class)->uploadImages($product->id, $files);
            }

            // Procesar materiales
            $materialsData = [];
            foreach ($request->materials as $material) {
                $materialsData[$material['id']] = ['quantity' => $material['quantity']];
            }
            $furniture->materials()->sync($materialsData);

            // Procesar manos de obra
            $laborsData = [];
            foreach ($request->labors as $labor) {
                $laborsData[$labor['id']] = ['days' => $labor['days']];
            }
            $furniture->labors()->sync($laborsData);

            // Procesar colores
            if ($request->has('colors')) {
                $colorIds = $request->input('colors');
                $product->colors()->sync($colorIds);
            }

            // Confirmar la transacción
            DB::commit();

            return response()->json($furniture, 201);

        } catch (\Exception $e) {
            // Revertir la transacción en caso de error
            DB::rollback();
            
            // Devolver el error
            return response()->json([
                'message' => 'Error al guardar el mueble.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $furniture = Furniture::find($id);

        if (!$furniture) {
            return response()->json(['message' => 'Mueble no encontrado'], 404);
        }

        $product = $furniture->product;

        // 1. VALIDACIÓN
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('products', 'code')->ignore($product->id)],
            'description' => 'sometimes|required|string|max:500',
            'furnitureType_id' => 'sometimes|required|integer|exists:furniture_types,id',
            'materials' => 'sometimes|required|array',
            'materials.*.id' => 'required|integer|exists:materials,id',
            'materials.*.quantity' => 'required|numeric|min:0',
            'labors' => 'sometimes|required|array',
            'labors.*.id' => 'required|integer|exists:labors,id',
            'labors.*.days' => 'required|numeric|min:0',
            'profit_per' => 'sometimes|required|numeric|min:0',
            'paint_per' => 'sometimes|required|numeric|min:0',
            'labor_fab_per' => 'sometimes|required|numeric|min:0',
            'sell' => 'sometimes|required|boolean',
            'discount' => 'sometimes|required|numeric|min:0|max:100',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'kept_images' => 'nullable|array',
            'kept_images.*' => 'integer',
            'colors' => 'required|array',
            'colors.*' => 'integer|exists:colors,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()->messages()], 422);
        }

        DB::beginTransaction();

        try {
            // 2. ACTUALIZACIÓN MASIVA (Cleaner Code)
            $product->fill($request->only([
                'name', 'code', 'description', 'sell', 'discount'
            ]));

            // Ojo: mapemos 'furnitureType_id' del request al campo 'furniture_type_id' de la DB si difieren
            if ($request->has('furnitureType_id')) {
                $furniture->furniture_type_id = $request->furnitureType_id;
            }
            
            $furniture->fill($request->only([
                'profit_per', 'paint_per', 'labor_fab_per'
            ]));

            // 3. GESTIÓN DE IMÁGENES (Lógica Replicada)
            if ($request->has('kept_images')) {
                $keptIds = $request->input('kept_images');
                if (!is_array($keptIds)) $keptIds = [];

                // Borramos solo las que NO están en la lista de kept_images
                $imagesToDelete = $product->images()->whereNotIn('id', $keptIds)->get();

                foreach ($imagesToDelete as $img) {
                    if (Storage::disk('public')->exists($img->url)) {
                        Storage::disk('public')->delete($img->url);
                    }
                    $img->delete();
                }
            }

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    $path = $file->store('assets/productPics', 'public');
                    $product->images()->create(['url' => $path]);
                }
            }

            // 4. SINCRONIZACIÓN DE COMPONENTES (Materiales y Mano de Obra)
            if ($request->has('materials')) {
                // Preparamos el array con pivote ['quantity' => x]
                $materialsData = collect($request->materials)->mapWithKeys(function ($material) {
                    return [$material['id'] => ['quantity' => $material['quantity']]];
                })->toArray();
                $furniture->materials()->sync($materialsData);
            }

            if ($request->has('labors')) {
                // Preparamos el array con pivote ['days' => x]
                $laborsData = collect($request->labors)->mapWithKeys(function ($labor) {
                    return [$labor['id'] => ['days' => $labor['days']]];
                })->toArray();
                $furniture->labors()->sync($laborsData);
            }

            // 5. GESTIÓN DE COLORES
            if ($request->has('colors')) {
                $colorIds = $request->input('colors');
                $product->colors()->sync($colorIds);
            }

            $product->save();
            $furniture->save();
            
            DB::commit();

            // 6. RESPUESTA ACTUALIZADA
            $furniture->load([
                'product.images', 
                'product.colors', 
                'materials', 
                'labors', 
                'furnitureType'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error al actualizar el mueble.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }

        return response()->json($furniture);
    }

    //Eliminar mueble
    public function destroy($id){
        DB::beginTransaction();

        try{
            $furniture = Furniture::find($id);

            if(!$furniture){
                return response()->json(['message' => 'Mueble no encontrado'], 404);
            }

            $product = $furniture->product;

            $furniture->delete();
            if ($product) {
                // Llamar al controlador de colores para eliminar los colores asociadas
                app(ColorController::class)->detachAndDeleteOrphanColors($product->id);

                // Llamar al controlador de imágenes para eliminar las imágenes asociadas
                app(ProductImageController::class)->deleteImages($product->id);
                
                $product->delete();
            }

            // Confirmar la transacción
            DB::commit();

            return response()->json(['message' => 'Mueble eliminado correctamente'], 200);

        } catch (\Exception $e){
            // Deshacer la transacción si ocurre un error
            DB::rollBack();

            // Devolver el error
            return response()->json([
                'message' => 'Error al eliminar el mueble.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
