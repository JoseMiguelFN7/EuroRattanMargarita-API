<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Product;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MaterialController extends Controller
{
    //Obtener todos los materiales
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 8);

        // 1. CARGA ANSIOSA (Eager Loading)
        // Incluimos 'product.stocks' para que lea la VISTA automáticamente
        $materials = Material::with([
            'materialTypes', 
            'unit', 
            'product.images', 
            'product.stocks',
        ])->paginate($perPage);

        // 2. LIMPIEZA DE DATOS
        $materials->through(function ($material) {
            
            // --- Nivel Material ---
            // Ocultamos IDs internos y timestamps que ensucian
            $material->makeHidden(['created_at', 'updated_at', 'product_id', 'unit_id']);

            // --- Nivel Producto ---
            if ($material->product) {
                $prod = $material->product;

                $prod->images->each(function ($image) {
                    // 1. Generamos la URL completa
                    $image->url = asset('storage/' . $image->url);
                    // 2. Limpiamos la basura de cada objeto imagen
                    $image->makeHidden(['created_at', 'updated_at', 'product_id']);
                });
                
                // B. Ocultamos la galería completa y datos innecesarios del producto
                $prod->makeHidden(['created_at', 'updated_at', 'sell', 'description']);

                // C. Stock (VISTA SQL): Limpiamos lo que sobra
                // Como la vista ya trae 'productID' y 'stock', solo quitamos lo que no sirva
                if ($prod->stocks) {
                    // Ocultamos productID porque ya está dentro del objeto producto
                    // y cualquier otro campo raro que traiga la vista
                    $prod->stocks->makeHidden(['productID', 'productCode']);
                }
            }

            // --- Nivel Tipos y Unidades ---
            if ($material->materialTypes) {
                $material->materialTypes->makeHidden(['pivot', 'created_at', 'updated_at']);
            }
            if ($material->unit) {
                $material->unit->makeHidden(['created_at', 'updated_at', 'id']);
            }

            return $material;
        });

        return response()->json($materials);
    }

    //Obtener material por ID
    public function show($id)
    {
        $material = Material::with(['materialTypes', 'unit', 'product.images', 'product.colors'])->find($id); //Busca el material por ID

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
            'materialTypes', 
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
        $material->makeHidden(['created_at', 'updated_at', 'product_id', 'unit_id']);

        // 3. LIMPIEZA DE RELACIONES DIRECTAS
        if ($material->unit) {
            $material->unit->makeHidden(['created_at', 'updated_at']);
        }

        if ($material->materialTypes) {
            $material->materialTypes->makeHidden(['pivot', 'created_at', 'updated_at']);
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
        $materials = Material::with(['materialTypes', 'unit', 'product.images'])
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

    public function randByMaterialType(Request $request, $quantity)
    {
        // Validar que el parámetro es un número entero positivo
        if (!is_numeric($quantity) || $quantity <= 0) {
            return response()->json([
                'error' => 'La cantidad debe ser un número entero positivo.'
            ], 400);
        }

        // Validar que se pasen los tipos de material en el request
        if (!$request->has('materialTypes') || !is_array($request->input('materialTypes'))) {
            return response()->json([
                'error' => 'Debe proporcionar un array de tipos de material.'
            ], 400);
        }

        $materialTypes = $request->input('materialTypes');

        // Validar que se pase un código y que sea válido (si es necesario)
        $codeToExclude = $request->input('code');
        if ($codeToExclude && !is_string($codeToExclude)) {
            return response()->json([
                'error' => 'El código debe ser una cadena de texto válida.'
            ], 400);
        }

        // Obtener productos cuyo tipo de material coincida con los tipos proporcionados en el request
        $materials = Material::with(['materialTypes', 'unit', 'product.images'])
            ->whereHas('materialTypes', function ($query) use ($materialTypes) {
                $query->whereIn('name', $materialTypes); // Filtrar por tipos de material
            })
            ->whereHas('product', function ($query) use ($codeToExclude) {
                $query->where('sell', true); // Filtrar por 'sell = true'

                // Excluir producto con el código proporcionado
                if ($codeToExclude) {
                    $query->where('code', '!=', $codeToExclude);
                }
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
    public function indexByMaterialType($name)
    {
        // 1. VERIFICAR EXISTENCIA DEL TIPO (Opcional, pero buena práctica)
        if (!MaterialType::where('name', $name)->exists()) {
             return response()->json(['message' => 'No se encontró el tipo de material'], 404);
        }

        // 2. QUERY OPTIMIZADA
        // Usamos whereHas para filtrar materiales por el nombre de su tipo relacionado
        $materials = Material::whereHas('materialTypes', function ($query) use ($name) {
                $query->where('name', $name);
            })
            ->with([
                'materialTypes',
                'unit',
                'product.images',
                'product.colors',
                'product.stocks'
            ])
            ->get();

        // 3. LIMPIEZA DE DATOS
        $materials->each(function ($material) {
            
            // --- Nivel Material ---
            // Ocultamos fechas, FKs y el 'pivot' que conecta con la búsqueda
            $material->makeHidden(['created_at', 'updated_at', 'product_id', 'unit_id', 'pivot']);

            // --- Nivel Relaciones Directas ---
            if ($material->unit) {
                $material->unit->makeHidden(['created_at', 'updated_at']);
            }

            if ($material->materialTypes) {
                $material->materialTypes->makeHidden(['pivot', 'created_at', 'updated_at']);
            }

            // --- Nivel Producto ---
            if ($material->product) {
                $prod = $material->product;

                // Limpieza del objeto producto
                $prod->makeHidden(['created_at', 'updated_at', 'sell', 'description', 'id']);

                // Procesar Imágenes (URLs absolutas + Limpieza)
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
            'material_type_ids' => 'required|array',
            'material_type_ids.*' => 'integer|exists:material_types,id',
            'price' => 'required|numeric|min:0',
            'discount' => 'required|numeric|min:0|max:100',
            'unit_id' => 'required|numeric|exists:units,id',
            'sell' => 'required|boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
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
                'price' => $request->price,
            ]);

            // Procesar colores
            if ($request->has('colors')) {
                $colorIds = $request->input('colors');
                $product->colors()->sync($colorIds);
            }

            // Asociar los tipos de materiales con el material creado
            $material->materialTypes()->sync($request->material_type_ids);

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
            'material_type_ids' => 'sometimes|required|array',
            'material_type_ids.*' => 'integer|exists:material_types,id',
            'price' => 'sometimes|required|numeric|min:0',
            'discount' => 'sometimes|required|numeric|min:0|max:100',
            'unit_id' => 'sometimes|required|numeric|exists:units,id',
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

        DB::beginTransaction();

        try {
            
            // Actualizar datos del Producto
            $product->fill($request->only([
                'name', 'code', 'description', 'sell', 'discount'
            ]));
            
            // Actualizar datos del Material
            $material->fill($request->only([
                'price', 'unit_id', 'profit_per', 'paint_per', 'labor_fab_per'
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

            // 3. TIPOS DE MATERIAL
            if ($request->has('material_type_ids')) {
                // Sync maneja automáticamente las relaciones (agrega nuevas y borra viejas)
                $material->materialTypes()->sync($request->material_type_ids);
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
            $material->load(['product.colors', 'product.images', 'materialTypes', 'unit']);

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
                // Llamar al controlador de colores para eliminar los colores asociadas
                app(ColorController::class)->detachAndDeleteOrphanColors($product->id);

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
}
