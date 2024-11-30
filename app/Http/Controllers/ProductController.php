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
        $product = Product::with(['material.materialTypes', 'furniture', 'set', 'colors', 'images'])->where('code', $cod)->first(); //Busca el producto por codigo

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

        return response()->json($product);
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
                        ->where('id', $id)
                        ->first();

        if(!$productStock){
            return response()->json(['message'=>'Producto no encontrado'], 404);
        }

        return response()->json($productStock);
    }
}
