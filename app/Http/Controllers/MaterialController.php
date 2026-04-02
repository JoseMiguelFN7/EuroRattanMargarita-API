<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Product;
use App\Models\Currency;
use App\Models\ProductMovement;
use App\Models\Color;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Jobs\GenerateMaterialsPdf;

class MaterialController extends Controller
{
    //Obtener todos los materiales
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 8);
        $search  = $request->input('search');
        $typeIds = $request->input('type_id');
        $categoryId = $request->input('category_id');

        $query = Material::with([
            'materialType.category', 
            'unit', 
            'product.images', 
            'product.stocks',
        ]);

        if ($search) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        // NUEVA LÓGICA DE FILTRADO CONDICIONAL
        if (!empty($typeIds)) {
            // Si hay un Tipo específico, la búsqueda es directa y rápida
            $typeIdsArray = is_array($typeIds) ? $typeIds : [$typeIds];
            $query->whereIn('material_type_id', $typeIdsArray);

        } elseif (!empty($categoryId)) {
            // Si NO hay Tipo, pero SÍ hay Categoría, buscamos a través de la relación
            $query->whereHas('materialType', function ($q) use ($categoryId) {
                $q->where('material_category_id', $categoryId);
            });
        }

        $materials = $query->paginate($perPage);

        $materials->through(function ($material) {
            // --- Nivel Material ---
            $material->makeHidden(['created_at', 'updated_at', 'product_id', 'unit_id', 'material_type_id']);

            // --- Nivel Producto ---
            if ($material->product) {
                $prod = $material->product;

                $prod->images->each(function ($image) {
                    $image->url = asset('storage/' . $image->url);
                    $image->makeHidden(['created_at', 'updated_at', 'product_id']);
                });
                
                $prod->makeHidden(['created_at', 'updated_at', 'sell', 'description']);

                if ($prod->stocks) {
                    $prod->stocks->makeHidden(['productID', 'productCode']);
                }
            }

            // --- Nivel Tipos y Unidades ---
            if ($material->materialType) {
                $material->materialType->makeHidden(['created_at', 'updated_at', 'material_category_id']);
                
                if ($material->materialType->category) {
                    $material->materialType->category->makeHidden(['created_at', 'updated_at']);
                }
            }
            if ($material->unit) {
                $material->unit->makeHidden(['created_at', 'updated_at', 'id']);
            }

            return $material;
        });

        return response()->json($materials);
    }

    public function indexSell(Request $request)
    {
        $perPage = $request->input('per_page', 8);
        $typeIds = $request->input('type_ids');          // <-- CAMBIO: Ahora esperamos un arreglo o CSV ('type_ids' en plural)
        $categoryId = $request->input('category_id');  

        // --- OBTENER LA TASA VES ACTUAL ---
        $vesCurrency = Currency::where('code', 'VES')->first();
        $vesRate = $vesCurrency ? $vesCurrency->current_rate : 0;
        // -----------------------------------------

        // 1. INICIAMOS EL QUERY BUILDER
        $query = Material::query()
            ->whereHas('product', function ($q) {
                $q->where('sell', true);
            });

        // 2. NUEVA LÓGICA DE FILTROS EN CASCADA (Soporta múltiples tipos)
        if (!empty($typeIds)) {
            // Convertimos la entrada a un arreglo seguro
            $typesArray = is_array($typeIds) ? $typeIds : explode(',', $typeIds);
            
            // Filtramos directo en la tabla con un whereIn, que es rapidísimo
            $query->whereIn('material_type_id', $typesArray);

        } elseif (!empty($categoryId)) {
            // Si NO enviaron tipos específicos, pero SÍ enviaron la categoría, traemos todos los de esa categoría
            $query->whereHas('materialType', function ($q) use ($categoryId) {
                $q->where('material_category_id', $categoryId);
            });
        }

        // 3. CARGA ANSIOSA Y PAGINACIÓN
        $materials = $query->with([
                'materialType.category', 
                'unit', 
                'product.images', 
                'product.stocks'
            ])
            ->paginate($perPage);

        // 4. LIMPIEZA DE DATOS (Transformación)
        $materials->through(function ($material) use ($vesRate) { 
            
            // --- CÁLCULO EN BOLÍVARES ---
            $material->price_VES = round($material->price * $vesRate, 2);

            // --- Nivel Material ---
            $material->makeHidden(['created_at', 'updated_at', 'product_id', 'unit_id', 'material_type_id']);

            // --- Nivel Producto ---
            if ($material->product) {
                $prod = $material->product;

                // A. Imágenes: URL Absoluta
                if ($prod->images) {
                    $prod->images->each(function ($image) {
                        $image->url = asset('storage/' . $image->url);
                        $image->makeHidden(['created_at', 'updated_at', 'product_id']);
                    });
                }
                
                // B. Stock: Limpieza de vista
                if ($prod->stocks) {
                    $prod->stocks->makeHidden(['productID', 'productCode']);
                }

                // C. Ocultar datos del padre
                $prod->makeHidden(['created_at', 'updated_at', 'description']);
            }

            // --- Nivel Tipos y Categorías ---
            if ($material->materialType) {
                $material->materialType->makeHidden(['created_at', 'updated_at', 'material_category_id']);
                
                if ($material->materialType->category) {
                    $material->materialType->category->makeHidden(['created_at', 'updated_at']);
                }
            }
            
            if ($material->unit) {
                $material->unit->makeHidden(['created_at', 'updated_at', 'id']);
            }

            return $material;
        });

        return response()->json($materials);
    }

    //Obtener todos los materiales para ingresar una compra (Solo id, name y code)
    public function listMaterialsPurchase()
    {
        $products = Product::has('material') // Solo productos que sean materiales
            ->select('id', 'name', 'code')   // Datos base del producto
            ->with([
                'colors:id,name,hex', 
                'material:id,product_id,unit_id', 
                // Añadimos 'allows_decimals' a la consulta de la tabla units
                'material.unit:id,name,allows_decimals'
            ])
            ->orderBy('name', 'asc')
            ->get()
            // 3. Transformación (Map)
            // Esto se ejecuta en memoria para limpiar el JSON final
            ->map(function ($product) {
                return [
                    'id'              => $product->id,
                    'code'            => $product->code,
                    'name'            => $product->name,
                    'unit'            => $product->material->unit->name ?? '', 
                    // Extraemos el booleano y nos aseguramos de que sea nativo (false por defecto)
                    'allows_decimals' => (bool) ($product->material->unit->allows_decimals ?? false),
                    'colors'          => $product->colors
                ];
            });

        return response()->json($products);
    }

    //Obtener material por ID
    public function show($id)
    {
        $material = Material::with(['materialType.category', 'unit', 'product.images', 'product.colors'])->find($id); //Busca el material por ID

        if(!$material){
            return response()->json(['message'=>'Material no encontrado'], 404);
        }

        $product = $material->product;

        $product->images = $product->images->map(function ($image) {
            return asset('storage/' . $image->url); // Generar las URLs completas de las imágenes
        });

        return response()->json($material);
    }

    public function showCod($cod)
    {
        // Usamos 'whereHas' para buscar el Material cuyo Producto tenga ese código.
        $material = Material::with([
            'unit', 
            'materialType.category',
            'product.images', 
            'product.colors'
        ])
        ->whereHas('product', function ($query) use ($cod) {
            $query->where('code', $cod);
        })
        ->first();

        if (!$material) {
            return response()->json(['message' => 'Material no encontrado'], 404);
        }

        // 2. LIMPIEZA NIVEL MATERIAL
        // Ocultamos las FKs y fechas
        $material->makeHidden(['created_at', 'updated_at', 'product_id', 'unit_id', 'material_type_id']);

        // 3. LIMPIEZA DE RELACIONES DIRECTAS
        if ($material->unit) {
            $material->unit->makeHidden(['created_at', 'updated_at']);
        }

        if ($material->materialType) {
            // Limpiamos la subcategoría
            $material->materialType->makeHidden(['created_at', 'updated_at', 'material_category_id']);
            
            // Limpiamos la categoría padre si vino en la carga
            if ($material->materialType->category) {
                $material->materialType->category->makeHidden(['created_at', 'updated_at']);
            }
        }

        // 4. LIMPIEZA DEL PRODUCTO PADRE
        if ($material->product) {
            $prod = $material->product;

            // Limpieza básica del objeto producto
            $prod->makeHidden(['created_at', 'updated_at', 'id']); // Ocultamos ID si ya tenemos el code

            // Gestión de Imágenes (URL completa + limpieza)
            $prod->images->each(function ($image) {
                $image->url = asset('storage/' . $image->url);
                $image->makeHidden(['created_at', 'updated_at', 'product_id']);
            });

            // Gestión de Colores (Limpieza)
            if ($prod->colors) {
                $prod->colors->makeHidden(['pivot', 'created_at', 'updated_at']);
            }
        }

        return response()->json($material);
    }

    public function materialCostHistory($code)
    {
        // 1. Buscamos el producto por su código
        $product = Product::where('code', $code)
            ->whereHas('material')
            ->with([
                'material.unit',
                'purchases' => function ($query) {
                    // Ordenamos: La compra [0] siempre será la más reciente
                    $query->with('supplier')
                          ->orderBy('date', 'desc')
                          ->orderBy('created_at', 'desc');
                }
            ])
            ->firstOrFail();

        $purchases = $product->purchases;

        // --- CORRECCIÓN: El costo actual es el de la última compra ---
        // Si hay compras, tomamos el costo del pivote de la primera (la más reciente).
        // Si no hay compras aún (material nuevo), el costo es 0.00
        $currentCost = $purchases->isNotEmpty() 
            ? (float) $purchases->first()->pivot->cost 
            : 0.00;

        // 2. Armamos la cabecera del Material
        $materialData = [
            'code'         => $product->code,
            'name'         => $product->name,
            'current_cost' => round($currentCost, 2), 
            'unit'         => $product->material->unit ? $product->material->unit->name : 'N/A'
        ];

        // 3. Reconstruimos el historial comparando los costos
        $history = [];

        foreach ($purchases as $index => $purchase) {
            $newCost = (float) $purchase->pivot->cost;
            
            // Buscamos la compra anterior en el tiempo (índice + 1)
            $olderPurchase = $purchases->get($index + 1);
            $oldCost = $olderPurchase ? (float) $olderPurchase->pivot->cost : 0.00;

            $history[] = [
                'id'            => $purchase->pivot->id ?? $purchase->id, 
                'date'          => $purchase->created_at ? $purchase->created_at->format('Y-m-d H:i:s') : $purchase->date->format('Y-m-d 00:00:00'),
                'old_cost'      => round($oldCost, 2),
                'new_cost'      => round($newCost, 2),
                'purchase_id'   => $purchase->id,
                'purchase_code' => $purchase->code,
                'provider_name' => $purchase->supplier ? $purchase->supplier->name : 'Sin Proveedor',
            ];
        }

        return response()->json([
            'material' => $materialData,
            'history'  => $history
        ]);
    }

    //Obtener una cantidad especifica de materiales en orden aleatorio
    public function rand($quantity)
    {
        // Validar que el parámetro es un número entero positivo
        if (!is_numeric($quantity) || $quantity <= 0) {
            return response()->json([
                'error' => 'La cantidad debe ser un número entero positivo.'
            ], 400);
        }

        // Obtener registros aleatorios
        $materials = Material::with(['materialType.category', 'unit', 'product.images'])
            ->whereHas('product', function ($query) {
                $query->where('sell', true); // Filtrar por 'sell = true'
            })
            ->inRandomOrder() // Seleccionar en orden aleatorio
            ->take($quantity) // Limitar la cantidad
            ->get()
            ->map(function ($material) {
                // Obtener solo la primera imagen del producto, si existe
                $material->product->image = $material->product->images->first() 
                    ? asset('storage/' . $material->product->images->first()->url) 
                    : null;
                return $material;
            });

        return response()->json($materials);
    }

    //Obtener todos los materiales de un tipo de material
    public function indexByCategoryName($name)
    {
        // 1. BUSCAR LA CATEGORÍA
        // Usamos first() para traernos el objeto completo y usar su relación
        $category = \App\Models\MaterialCategory::where('name', $name)->first();

        if (!$category) {
             return response()->json(['message' => 'No se encontró la categoría de material'], 404);
        }

        // 2. QUERY OPTIMIZADA (Usando tu hasManyThrough)
        // A partir de la categoría, llamamos a ->materials()
        $materials = $category->materials()
            ->with([
                'materialType.category', // Actualizado a singular y anidado
                'unit',
                'product.images',
                'product.colors',
                'product.stocks'
            ])
            ->get();

        // 3. LIMPIEZA DE DATOS
        $materials->each(function ($material) {
            
            // --- Nivel Material ---
            // Añadimos 'material_type_id' para ocultarlo y quitamos 'pivot' que ya no existe
            $material->makeHidden(['created_at', 'updated_at', 'product_id', 'unit_id', 'material_type_id']);

            // --- Nivel Relaciones Directas ---
            if ($material->unit) {
                $material->unit->makeHidden(['created_at', 'updated_at', 'id']); // id oculto opcionalmente para más limpieza
            }

            // Actualizado para manejar el objeto singular 'materialType'
            if ($material->materialType) {
                $material->materialType->makeHidden(['created_at', 'updated_at', 'material_category_id']);
                
                if ($material->materialType->category) {
                    $material->materialType->category->makeHidden(['created_at', 'updated_at']);
                }
            }

            // --- Nivel Producto ---
            if ($material->product) {
                $prod = $material->product;

                $prod->makeHidden(['created_at', 'updated_at', 'sell', 'description', 'id']);

                // Procesar Imágenes
                $prod->images->each(function ($image) {
                    $image->url = asset('storage/' . $image->url);
                    $image->makeHidden(['created_at', 'updated_at', 'product_id']);
                });

                // Procesar Stock
                if ($prod->stocks) {
                    $prod->stocks->makeHidden(['productID', 'productCode']);
                }

                // Procesar Colores
                if ($prod->colors) {
                    $prod->colors->makeHidden(['pivot', 'created_at', 'updated_at']);
                }
            }
        });

        return response()->json($materials);
    }

    //Crear material
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:products',
            'description' => 'required|string|max:500',
            'material_type_id' => 'required|integer|exists:material_types,id',
            'price' => 'required|numeric|min:0',
            'discount' => 'required|numeric|min:0|max:100',
            'unit_id' => 'required|numeric|exists:units,id',
            'min_stock' => 'required|numeric|min:0',
            'max_stock' => 'nullable|numeric|gte:min_stock',
            'sell' => 'required|boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
            'colors' => 'nullable|array',
            'colors.*' => 'integer|exists:colors,id'
        ]);

        //enviar error si es necesario
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        DB::beginTransaction();

        try{
            //Crear el producto
            $product = Product::create([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'sell' => $request->sell,
                'discount' => $request->discount
            ]);

            //procesado de imagen
            if ($request->hasFile('images')) {
                $files = $request->file('images');
                app(ProductImageController::class)->uploadImages($product->id, $files);
            }

            //Crear el material
            $material = Material::create([
                'product_id' => $product->id,
                'unit_id' => $request->unit_id,
                'material_type_id' => $request->material_type_id,
                'price' => $request->price,
                'min_stock' => $request->min_stock,
                'max_stock' => $request-> max_stock
            ]);

            // Procesar colores
            if ($request->has('colors')) {
                $colorIds = $request->input('colors');
                $product->colors()->sync($colorIds);
            }

            // Confirmar la transacción
            DB::commit();

            return response()->json($material, 201);
        } catch(\Exception $e){
            // Revertir la transacción en caso de error
            DB::rollback();

            // Devolver el error
            return response()->json([
                'message' => 'Error al guardar el material.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    //actualizar material
    public function update(Request $request, $id)
    {
        $material = Material::find($id);

        if (!$material) {
            return response()->json(['message' => 'Material no encontrado'], 404);
        }

        $product = $material->product;

        // Validación (La mantuve igual, está correcta)
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:255|unique:products,code,' . $product->id,
            'description' => 'sometimes|required|string|max:500',
            'sell' => 'sometimes|required|boolean',
            'material_type_id' => 'sometimes|required|integer|exists:material_types,id',
            'price' => 'sometimes|required|numeric|min:0',
            'discount' => 'sometimes|required|numeric|min:0|max:100',
            'unit_id' => 'sometimes|required|numeric|exists:units,id',
            'min_stock' => 'sometimes|required|numeric|min:0',
            'max_stock' => 'nullable|numeric|gte:min_stock',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'kept_images' => 'nullable|array',
            'kept_images.*' => 'integer',
            'colors' => 'nullable|array',
            'colors.*' => 'integer|exists:colors,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        // --- NUEVA VALIDACIÓN DE INVENTARIO PARA COLORES ---
        if ($request->has('colors')) {
            $requestedColors = $request->input('colors');
            
            // 1. Obtenemos los colores que el producto tiene ACTUALMENTE
            $currentColors = $product->colors()->pluck('colors.id')->toArray();
            
            // 2. Comparamos para saber cuáles colores se están intentando QUITAR
            $colorsToDetach = array_diff($currentColors, $requestedColors);

            if (!empty($colorsToDetach)) {
                // 3. Verificamos si existe AL MENOS UN movimiento para esos colores y este producto
                $colorsWithMovements = ProductMovement::where('product_id', $product->id)
                    ->whereIn('color_id', $colorsToDetach)
                    ->pluck('color_id')
                    ->unique()
                    ->toArray();

                // 4. Si encontramos movimientos, abortamos con un error 422
                if (!empty($colorsWithMovements)) {
                    // Buscamos los nombres de los colores para darle un mensaje claro al usuario
                    $colorNames = Color::whereIn('id', $colorsWithMovements)->pluck('name')->implode(', ');
                    
                    return response()->json([
                        'message' => 'Validación de inventario fallida.',
                        'errors' => [
                            'colors' => ["No puedes desvincular los siguientes colores porque ya tienen movimientos en el inventario: {$colorNames}."]
                        ]
                    ], 422);
                }
            }
        }

        DB::beginTransaction();

        try {
            
            // Actualizar datos del Producto
            $product->fill($request->only([
                'name', 'code', 'description', 'sell', 'discount'
            ]));
            
            // Actualizar datos del Material
            $material->fill($request->only([
                'price', 'unit_id', 'min_stock', 'max_stock', 'material_type_id'
            ]));

            // 2. GESTIÓN DE IMÁGENES
            if ($request->has('kept_images')) {
            $keptIds = $request->input('kept_images');

            // Aseguramos que sea un array (por si el front manda null o string vacío)
            if (!is_array($keptIds)) {
                $keptIds = [];
            }

            // OPTIMIZACIÓN: Buscamos directamente las imágenes que NO están en la lista
            // Esto evita hacer un loop foreach sobre todas las imágenes si no es necesario
            $imagesToDelete = $product->images()->whereNotIn('id', $keptIds)->get();

            foreach ($imagesToDelete as $img) {
                // 1. Borrar archivo físico
                if (Storage::disk('public')->exists($img->url)) {
                    Storage::disk('public')->delete($img->url);
                }
                // 2. Borrar registro de la BD
                $img->delete();
            }
        }

        // PASO B: AGREGAR NUEVAS (Las que vienen en el array images)
        if ($request->hasFile('images')) {
            // Aquí puedes llamar a tu ProductImageController o hacerlo inline.
            // Lo hago inline para que veas la lógica completa aquí:
            foreach ($request->file('images') as $file) {
                // Guardamos en assets/productPics dentro del disco public
                $path = $file->store('assets/productPics', 'public');
                
                // Creamos la relación
                $product->images()->create([
                    'url' => $path,
                    // 'product_id' se asigna automático por la relación
                ]);
            }
        }

            // 4. GESTIÓN DE COLORES
            if ($request->has('colors')) {
                $colorIds = $request->input('colors');
                $product->colors()->sync($colorIds);
            }

            $product->save();
            $material->save();
            
            DB::commit();

            // 5. RESPUESTA ACTUALIZADA
            $material->load(['product.colors', 'product.images', 'materialType.category', 'unit']);

        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error al actualizar el material.',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json($material);
    }

    //Eliminar material
    public function destroy($id){
        DB::beginTransaction();

        try{
            $material = Material::find($id);

            if(!$material){
                return response()->json(['message' => 'Material no encontrado'], 404);
            }

            $product = $material->product;

            $material->delete();
            // Eliminar las imágenes asociadas al producto

            if ($product) {
                // Llamar al controlador de imágenes para eliminar las imágenes asociadas
                app(ProductImageController::class)->deleteImages($product->id);

                $product->delete();
            }

            // Confirmar la transacción
            DB::commit();

            return response()->json(['message' => 'Material eliminado correctamente'], 200);

        } catch (\Exception $e){
            // Deshacer la transacción si ocurre un error
            DB::rollBack();

            // Devolver el error
            return response()->json([
                'message' => 'Error al eliminar el material.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function exportPdf(Request $request)
    {
        $search = $request->input('search');
        $typeId = $request->input('type_id');
        $categoryId = $request->input('category_id'); // NUEVO: Capturamos la categoría
        $userId = auth('sanctum')->id();

        // NUEVO: Pasamos el categoryId al Job
        GenerateMaterialsPdf::dispatch($search, $typeId, $categoryId, $userId);

        return response()->json([
            'message' => 'Generando reporte. Te notificaremos cuando esté listo.',
            'status' => 'processing'
        ], 202); 
    }
}
