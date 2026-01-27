<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Product;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

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
            'product.stocks'
        ])->paginate($perPage);

        // 2. LIMPIEZA DE DATOS
        $materials->through(function ($material) {
            
            // --- Nivel Material ---
            // Ocultamos IDs internos y timestamps que ensucian
            $material->makeHidden(['created_at', 'updated_at', 'product_id', 'unit_id']);

            // --- Nivel Producto ---
            if ($material->product) {
                $prod = $material->product;

                // A. Imagen: Solo enviamos la URL principal (string), no el array
                $firstImage = $prod->images->first();
                $prod->image = $firstImage ? asset('storage/' . $firstImage->url) : null;
                
                // B. Ocultamos la galería completa y datos innecesarios del producto
                $prod->makeHidden(['created_at', 'updated_at', 'images', 'sell', 'description']);

                // C. Stock (VISTA SQL): Limpiamos lo que sobra
                // Como la vista ya trae 'productID' y 'stock', solo quitamos lo que no sirva
                if ($prod->stocks) {
                    // Ocultamos productID porque ya está dentro del objeto producto
                    // y cualquier otro campo raro que traiga la vista
                    $prod->stocks->makeHidden(['productID', 'productCode']); 
                }
                
                // Nota: No necesitamos cargar 'colors' aparte si la vista ya trae el color
                // Si la vista trae: { color: "#Hex", stock: 5 }, ya tienes todo.
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
        // Obtener el tipo de material junto con los materiales y sus relaciones necesarias
        $materialType = MaterialType::where('name', $name)
            ->with(['materials.materialTypes', 'materials.unit', 'materials.product.images', 'materials.product.colors'])
            ->first();

        // Validar si se encontró el tipo de material
        if (!$materialType) {
            return response()->json(['message' => 'No se encontró el tipo de material'], 404);
        }

        // Mapear los materiales para aplicar el mismo formato que en index()
        $materials = $materialType->materials->map(function ($material) {
            $product = $material->product;

            if ($product) {
                // Mapear las imágenes a URLs completas
                if ($product->images->isNotEmpty()) {
                    $product->images = $product->images->map(function ($image) {
                        return asset('storage/' . $image->url);
                    });

                    // Asignar la primera imagen como `image`
                    $material->product->image = $product->images[0];
                } else {
                    $material->product->images = [];
                    $material->product->image = null;
                }

                // Obtener el stock desde la tabla product_stocks
                $productStock = DB::table('product_stocks')
                    ->where('productID', $product->id)
                    ->get();

                $product->stock = $productStock;
            }

            return $material;
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
            'colors.*' => 'string|regex:/^#([A-Fa-f0-9]{6})$/'
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
                $colorIds = app(ColorController::class)->getOrCreateColors($request->colors);
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
    public function update(Request $request, $id){
        $material = Material::find($id);

        if(!$material){
            return response()->json(['message'=>'Material no encontrado'], 404);
        }

        $product = $material->product;

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
            'colors' => 'nullable|array',
            'colors.*' => 'string|regex:/^#([A-Fa-f0-9]{6})$/'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        DB::beginTransaction();

        try{
            if($request->has('name')){
                $product->name = $request->name;
            }

            if($request->has('code')){
                $product->code = $request->code;
            }

            if($request->has('description')){
                $product->description = $request->description;
            }

            if($request->has('sell')){
                $product->sell = $request->sell;
            }

            if($request->has('discount')){
                $product->discount = $request->discount;
            }

            if($request->has('price')){
                $material->price = $request->price;
            }

            // Procesar y almacenar nuevas imágenes
            if ($request->hasFile('images')) {
                // Llamar al controlador de imágenes para eliminar las imágenes asociadas
                app(ProductImageController::class)->deleteImages($product->id);

                // Procesar las nuevas imágenes
                $files = $request->file('images');
                app(ProductImageController::class)->uploadImages($product->id, $files);
            }

            // Sincronizar los tipos de material
            if ($request->has('material_type_ids')) {
                $material->materialTypes()->sync($request->material_type_ids);
            }

            if($request->has('unit_id')){
                $material->unit_id = $request->unit_id;
            }

            if($request->has('profit_per')){
                $material->profit_per = $request->profit_per;
            }

            if($request->has('paint_per')){
                $material->paint_per = $request->paint_per;
            }

            if($request->has('labor_fab_per')){
                $material->labor_fab_per = $request->labor_fab_per;
            }

            // Procesar colores
            if ($request->has('colors')) {
                $colorIds = app(ColorController::class)->getOrCreateColors($request->colors);
                $product->colors()->sync($colorIds);
            }

            $product->save();
            $material->save();
            DB::commit();
        }catch(Exception $e){
            DB::rollback();

            // Devolver el error
            return response()->json([
                'message' => 'Error al actualizar el material.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
