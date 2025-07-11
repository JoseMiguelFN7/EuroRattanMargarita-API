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
                $product->image = $product->images->first() ? asset('storage/' . $product->images->first()->url) : null;

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
        $product = Product::with(['material.materialTypes', 'furniture.furnitureType', 'furniture.materials', 'furniture.labors', 'set', 'colors', 'images'])->where('code', $cod)->first(); //Busca el producto por codigo

        if(!$product){
            return response()->json(['message'=>'Producto no encontrado'], 404);
        }

        if ($product) {
            // Modifica las URLs de todas las imágenes
            $product->images = $product->images->map(function ($image) {
                $image->url = asset('storage/' . $image->url);
                return $image;
            });
        }

        // Obtener el stock del producto
        $productStock = DB::table('product_stocks')
            ->where('productID', $product->id)
            ->get(); // Devuelve el stock asociado al producto

        // Agregar el stock al producto en la respuesta
        $product->stock = $productStock;

        // Si es mueble, calcular los precios
        if ($product->furniture) {
            $precios = $product->furniture->calcularPrecios();
            $product->furniture->pvp_natural = $precios['pvp_natural'];
            $product->furniture->pvp_color = $precios['pvp_color'];
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

    //Obtener producto por codigo
    public function ProductSearchByName($search)
    {
        $products = Product::where('name', 'LIKE', '%'.$search.'%')
        ->with(['material', 'furniture', 'set', 'colors', 'images'])
        ->get();

        // Verificar si se encontraron productos
        if ($products->isEmpty()) {
            return response()->json(['message' => 'No se encontraron productos'], 404);
        }

        // Modificar las URLs de todas las imágenes de cada producto
        $products->each(function ($product) {
            $product->images = $product->images->map(function ($image) {
                $image->url = asset('storage/' . $image->url);
                return $image;
            });

            $product->image = $product->images[0]->url;
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
