<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    //Obtener todos los productos
    public function index(){
        $products = Product::with(['material', 'furniture', 'set', 'colors', 'images'])->get()->map(function ($product) {
            // Agregar la URL completa de la imagen al material
            $product->image = $product->image ? asset('storage/' . $product->image) : null;
            return $product;
        });
        return response()->json($products);
    }

    //Obtener producto por ID
    public function show($id)
    {
        $product = Product::with(['material', 'furniture', 'set', 'colors', 'images'])->find($id); //Busca el producto por ID

        if(!$product){
            return response()->json(['message'=>'Producto no encontrado'], 404);
        }

        if ($product->image) {
            $product->image = asset('storage/' . $product->image); // Generar la URL completa de la imagen
        }

        return response()->json($product);
    }

    public function rand($quantity)
    {
        // Validar que el parámetro sea un número entero positivo
        if (!is_numeric($quantity) || $quantity <= 0) {
            return response()->json([
                'error' => 'La cantidad debe ser un número entero positivo.'
            ], 400);
        }

        // Obtener productos con 'sell = true'
        $products = Product::with(['material', 'furniture', 'set', 'images']) // Cargar relaciones necesarias
            ->where('sell', true) // Filtrar por 'sell = true'
            ->inRandomOrder() // Seleccionar en orden aleatorio
            ->take($quantity) // Limitar la cantidad
            ->get()
            ->map(function ($product) {
                // Agregar la URL completa de la imagen al producto
                $product->images->each(function ($image) {
                    // 1. Generamos la URL completa
                    $image->url = asset('storage/' . $image->url);
                    // 2. Limpiamos la basura de cada objeto imagen
                    $image->makeHidden(['created_at', 'updated_at', 'product_id']);
                });

                // Determinar cuál relación tiene información para obtener el precio
                if ($product->material) {
                    $product->price = $product->material->price;
                } elseif ($product->furniture) {
                    $product->price = $product->furniture->price; // Asegúrate de que el campo 'price' esté en 'furniture'
                } elseif ($product->set) {
                    $product->price = $product->set->price; // Asegúrate de que el campo 'price' esté en 'set'
                } else {
                    $product->price = null; // Si no hay precio en ninguna relación
                }

                return $product;
            });

        return response()->json($products);
    }

    //Obtener producto por codigo
    public function showCod($cod)
    {
        // 1. CARGA DE RELACIONES (Usando la vista de stocks para rendimiento)
        $product = Product::with([
            'material.materialTypes', 
            'material.unit', 
            'furniture.furnitureType', 
            'furniture.materials', 
            'furniture.labors', 
            'set', 
            'images',
            'stocks'
        ])->where('code', $cod)->first();

        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        // 2. LIMPIEZA DE PRODUCTO (Nivel Raíz)
        // Mantenemos la estructura, solo ocultamos fechas y campos internos
        $product->makeHidden(['created_at', 'updated_at']);

        // 3. LIMPIEZA DE IMÁGENES
        // NO las convertimos en strings simples. Mantenemos el array de objetos 
        // porque tu front seguro espera 'image.url' o 'image.id'.
        $product->images->each(function ($image) {
            $image->url = asset('storage/' . $image->url);
            $image->makeHidden(['created_at', 'updated_at', 'product_id']);
        });

        // 4. LIMPIEZA DEL STOCK
        // Mantenemos el array de objetos, solo quitamos la redundancia
        if ($product->stocks) {
            $product->stocks->makeHidden(['productID', 'productCode']);
        }

        // 5. LIMPIEZA POLIMÓRFICA (Material / Mueble / Set)
        
        // --- CASO MATERIAL ---
        if ($product->material) {
            // Ocultamos los hermanos nulos para no enviar "furniture": null
            $product->makeHidden(['furniture', 'set']);
            
            // Limpiamos el material
            $product->material->makeHidden(['created_at', 'updated_at', 'product_id', 'unit_id']);
            
            // Limpiamos la Unidad (Mantenemos el objeto {id, name})
            if ($product->material->unit) {
                $product->material->unit->makeHidden(['created_at', 'updated_at']);
            }

            // Limpiamos los Tipos (Mantenemos array de objetos)
            if ($product->material->materialTypes) {
                $product->material->materialTypes->makeHidden(['pivot', 'created_at', 'updated_at']);
            }
        } 
        
        // --- CASO MUEBLE ---
        elseif ($product->furniture) {
            $product->makeHidden(['material', 'set']);

            // Cálculos de precio (mantenemos tu lógica intacta)
            $precios = $product->furniture->calcularPrecios();
            $product->furniture->pvp_natural = $precios['pvp_natural'];
            $product->furniture->pvp_color = $precios['pvp_color'];

            $product->furniture->makeHidden(['created_at', 'updated_at', 'product_id']);
            
            // Limpiar sub-relaciones manteniendo estructura
            if($product->furniture->materials) $product->furniture->materials->makeHidden(['pivot', 'created_at', 'updated_at']);
            if($product->furniture->labors) $product->furniture->labors->makeHidden(['pivot', 'created_at', 'updated_at']);
        }

        return response()->json($product);
    }

    // Obtener productos por arreglo de códigos desde el request
    public function showByCodeArray(Request $request)
    {
        // Validar que el request contenga un array de códigos
        $codes = $request->input('codes');

        if (!$codes || !is_array($codes)) {
            return response()->json(['message' => 'Se requiere un arreglo de códigos válido'], 400);
        }

        // Buscar los productos por los códigos proporcionados
        $products = Product::with(['material.materialTypes', 'furniture', 'set', 'colors', 'images'])
            ->whereIn('code', $codes)
            ->get();

        // Verificar si no se encontró algún producto
        $foundCodes = $products->pluck('code')->toArray();
        $missingCodes = array_diff($codes, $foundCodes);

        if ($products->isEmpty()) {
            return response()->json(['message' => 'Ningún producto encontrado para los códigos proporcionados'], 404);
        }

        // Modificar las URLs de las imágenes y agregar el stock a cada producto
        $products = $products->map(function ($product) {
            // Ajustar URLs de las imágenes
            $product->images = $product->images->map(function ($image) {
                $image->url = asset('storage/' . $image->url);
                return $image;
            });

            $product->image = $product->images->first() ? $product->images->first()->url : null;

            // Obtener el stock del producto
            $productStock = DB::table('product_stocks')
                ->where('productID', $product->id)
                ->get();

            // Agregar el stock al producto
            $product->stock = $productStock;

            return $product;
        });

        // Construir la respuesta
        $response = [
            'products' => $products,
            'missingCodes' => $missingCodes, // Códigos que no se encontraron
        ];

        return response()->json($response);
    }

    //Obtener producto por nombre
    public function ProductSearchByName(Request $request, $search)
    {
        // 1. Configuración de Paginación
        $perPage = $request->input('per_page', 8);

        // 2. Query con Paginación
        $products = Product::where('name', 'LIKE', '%'.$search.'%')
            ->with(['material', 'furniture', 'set', 'colors', 'images'])
            ->paginate($perPage);

        // 3. Transformación
        $products = $products->through(function ($product) {
            
            if ($product->images && $product->images->isNotEmpty()) {
                // Mapeamos las URLs completas
                $product->images = $product->images->map(function ($image) {
                    $image->url = asset('storage/' . $image->url);
                    return $image;
                });

                // Asignamos la imagen principal de forma segura
                $product->image = $product->images[0]->url ?? null;
            } else {
                $product->images = [];
                $product->image = null;
            }

            if($product->furniture){
                $precios = $product->furniture->calcularPrecios();
                $product->furniture->pvp_natural = $precios['pvp_natural'];
                $product->furniture->pvp_color = $precios['pvp_color'];
            }

            return $product;
        });

        return response()->json($products);
    }

    //Obtener todos los stocks
    public function indexStocks(){
        $productStocks = DB::table('product_stocks')->get();

        return response()->json($productStocks);
    }

    //Obtener stock por ID de producto
    public function showStock($id)
    {
        $productStock = DB::table('product_stocks')
                        ->where('productID', $id)
                        ->get();

        if(!$productStock){
            return response()->json(['message'=>'Producto no encontrado'], 404);
        }

        return response()->json($productStock);
    }
}
