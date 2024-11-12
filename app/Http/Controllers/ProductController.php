<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    //Obtener todos los usuarios
    public function index(){
        $products = Product::with(['material', 'furniture'])->get();
        return response()->json($products);
    }

    //Obtener producto por ID
    public function show($id)
    {
        $product = Product::with(['material', 'furniture'])->find($id); //Busca el producto por ID

        if(!$product){
            return response()->json(['message'=>'Producto no encontrado'], 404);
        }

        return response()->json($product);
    }
}
