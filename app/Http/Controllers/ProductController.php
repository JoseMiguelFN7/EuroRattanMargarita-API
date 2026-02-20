<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Color;

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

        // 1. CARGA DE RELACIONES PROFUNDA
        // Cargamos todo lo necesario para que las fórmulas de precio (calcularPrecios / calculatePrices)
        // funcionen sin hacer consultas extra a la base de datos.
        $products = Product::with([
            'images',
            'material',                            // Precio directo
            'furniture.materials.materialTypes',   // Fórmula Mueble
            'furniture.labors',                    // Fórmula Mueble
            'set.furnitures.materials.materialTypes', // Fórmula Set
            'set.furnitures.labors'                // Fórmula Set
        ])
        ->where('sell', true)
        ->inRandomOrder()
        ->take($quantity)
        ->get();

        // 2. TRANSFORMACIÓN Y UNIFICACIÓN
        $products->transform(function ($product) {
            
            // --- A. LÓGICA DE PRECIOS EN CAMPO 'price' ---
            $calculatedPrice = 0;

            if ($product->material) {
                // Caso Material: El precio es directo
                $calculatedPrice = $product->material->price;
            } 
            elseif ($product->furniture) {
                // Caso Mueble: Calculamos PVP Natural
                $prices = $product->furniture->calcularPrecios();
                $calculatedPrice = $prices['pvp_natural'];
            } 
            elseif ($product->set) {
                // Caso Juego: Calculamos PVP Natural
                $prices = $product->set->calcularPrecios();
                $calculatedPrice = $prices['pvp_natural'];
            }

            // Asignamos el resultado al campo estandar 'price'
            $product->price = $calculatedPrice;


            // --- B. LIMPIEZA DE IMÁGENES ---
            $product->images->each(function ($image) {
                $image->url = asset('storage/' . $image->url);
                $image->makeHidden(['created_at', 'updated_at', 'product_id']);
            });


            // --- C. LIMPIEZA FINAL ---
            // Ocultamos las relaciones complejas para dejar el objeto plano
            $product->makeHidden([
                'created_at', 
                'updated_at', 
                'sell', 
                'discount',
                'material', 
                'furniture', 
                'set'
            ]);

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
            'furniture.materials.materialTypes', 
            'furniture.labors', 
            'set.setType',
            'set.furnitures.product.images',
            'set.furnitures.product.stocks',
            'set.furnitures.materials.materialTypes',
            'set.furnitures.labors',
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
            $furniture = $product->furniture;

            // A. Precios (Tu lógica existente)
            $precios = $furniture->calcularPrecios();
            $furniture->pvp_natural = $precios['pvp_natural'];
            $furniture->pvp_color = $precios['pvp_color'];

            // B. Limpieza GENERAL del Mueble
            // Ocultamos 'product' para evitar el bucle infinito y datos repetidos
            $furniture->makeHidden(['product', 'materials', 'labors', 'created_at', 'updated_at', 'product_id']);

            if ($furniture->furnitureType) {
                $furniture->furnitureType->makeHidden(['created_at', 'updated_at']);
            }

            // Ocultamos relaciones hermanas vacías
            $product->makeHidden(['material', 'set']);
        }

        // --- CASO SET (JUEGO) ---
        elseif ($product->set) {
            $product->makeHidden(['material', 'furniture']);
            $set = $product->set;

            // A. Cálculos usando tus funciones del Modelo
            $precios = $set->calcularPrecios();
            $set->pvp_natural = $precios['pvp_natural'];
            $set->pvp_color = $precios['pvp_color'];

            $set->available_colors = $set->calcularColoresDisponibles();

            // B. Transformar Componentes (Muebles del juego)
            // Creamos un array limpio 'components' con solo lo que pide el front
            $set->components = $set->furnitures->map(function ($furniture) {
                $prod = $furniture->product;
                
                // Gestionar imagen del componente
                $imgUrl = null;
                if ($prod && $prod->images->isNotEmpty()) {
                    $imgUrl = asset('storage/' . $prod->images->first()->url);
                }

                return [
                    'id'          => $furniture->id,
                    'code'        => $prod ? $prod->code : 'N/A',
                    'name'        => $prod ? $prod->name : 'Desconocido',
                    'description' => $prod ? $prod->description : '',
                    'quantity'    => $furniture->pivot->quantity,
                    'image'       => $imgUrl
                ];
            });

            // C. Limpieza del Set
            if ($set->setType) {
                $set->setType->makeHidden(['created_at', 'updated_at']);
            }

            // Ocultamos la relación original 'furnitures' (muy pesada) y dejamos solo 'components'
            $set->makeHidden([
                'furnitures', 
                'product_id', 
                'set_types_id', 
                'created_at', 
                'updated_at',
                'profit_per', 
                'paint_per', 
                'labor_fab_per'
            ]);
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

    public function validateCartItems(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.productId' => 'required|exists:products,id',
            'items.*.variantId' => 'nullable|integer', // Esto es el ID de la tabla colors
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $response = [];

        // ---------------------------------------------------------
        // 1. MAPEO DE COLORES (LA SOLUCIÓN AL BUG)
        // ---------------------------------------------------------
        // Extraemos todos los IDs de variantes solicitados en el carrito
        $variantIds = collect($request->items)->pluck('variantId')->filter()->unique();

        // Consultamos la tabla 'colors' directamente.
        // Obtenemos un array simple: [ ID_COLOR => IS_NATURAL (bool) ]
        // Esto es independiente de si hay stock o no.
        $colorsMap = Color::whereIn('id', $variantIds)->pluck('is_natural', 'id');

        // ---------------------------------------------------------
        // 2. Carga de Productos
        // ---------------------------------------------------------
        $productRelations = [
            'images',
            'stocks',         // Solo para materiales
            'material',
            'furniture.materials', 
            'furniture.labors',
            'set.furnitures.product.stocks', // Para calcular el cuello de botella del set
            'set.furnitures.materials',
            'set.furnitures.labors'
        ];

        foreach ($request->items as $item) {
            $product = Product::with($productRelations)->find($item['productId']);
            
            if (!$product) continue;

            $variantId = $item['variantId'];
            
            // ---------------------------------------------------------
            // 3. DETERMINAR PROPIEDAD DEL PRECIO (SIN MIRAR STOCK)
            // ---------------------------------------------------------
            // Si hay variantId, buscamos en nuestro mapa maestro. 
            // Si no existe variantId, asumimos que es Natural.
            $isNatural = $variantId ? ($colorsMap[$variantId] ?? true) : true;

            $price = 0;
            $stockAvailable = 0;

            // --- LÓGICA PARA JUEGOS (SETS) ---
            if ($product->set) {
                // Precios base del set
                $precios = $product->set->calcularPrecios();
                
                // Aquí decidimos el precio solo basándonos en el COLOR, no en el stock
                $price = (!$isNatural) ? ($precios['pvp_color'] ?? $precios['pvp_natural']) : $precios['pvp_natural'];

                // Ahora calculamos stock (Cuello de Botella)
                $coloresDisponibles = $product->set->calcularColoresDisponibles();
                
                if ($variantId) {
                    // Buscamos si es posible fabricar este color específico
                    $colorData = collect($coloresDisponibles)->firstWhere('id', $variantId);
                    $stockAvailable = $colorData ? $colorData['stock'] : 0;
                } else {
                    $stockAvailable = 0; // Obligar a elegir color
                }
            } 
            
            // --- LÓGICA PARA MUEBLES ---
            elseif ($product->furniture) {
                $precios = $product->furniture->calcularPrecios();
                
                // Precio decidido por la definición del color
                $price = (!$isNatural) ? ($precios['pvp_color'] ?? $precios['pvp_natural']) : $precios['pvp_natural'];

                // Stock calculado vía cuello de botella (igual que el set, o similar)
                // OJO: Si usas la misma lógica de "colores disponibles" para muebles individuales:
                $coloresDisponibles = $product->furniture->calcularColoresDisponibles(); // Asumo que el mueble también tiene esto o similar
                
                // Si el mueble usa lógica simple de stocks (aunque dijiste que no tienen stock físico directo):
                // Ajusta esto según cómo calculas disponibilidad de un mueble individual.
                // Si usas la misma función que el Set:
                 if ($variantId) {
                    $colorData = collect($coloresDisponibles)->firstWhere('id', $variantId);
                    $stockAvailable = $colorData ? $colorData['stock'] : 0;
                } else {
                    $stockAvailable = 0;
                }
            } 
            
            // --- LÓGICA PARA MATERIALES ---
            elseif ($product->material) {
                $price = $product->material->price; // Precio fijo usualmente

                // Materiales SÍ suelen tener stock físico directo en la vista
                if ($variantId) {
                    $stockData = $product->stocks->where('colorID', $variantId)->first();
                    $stockAvailable = $stockData ? $stockData->stock : 0;
                } else {
                    $stockAvailable = $product->stocks->sum('stock');
                }
            }

            // --- Respuesta Final ---
            $response[] = [
                'id'              => $product->id,
                'variant_id'      => $variantId,
                'name'            => $product->name,
                'image'           => $product->images->first() 
                                     ? asset('storage/' . $product->images->first()->url) 
                                     : null,
                'price'           => (float) $price,
                'discount'        => (float) $product->discount,
                'stock_available' => (int) $stockAvailable,
                'quantity'        => (int) $item['quantity'],
                'insufficient_stock' => $item['quantity'] > $stockAvailable,
            ];
        }

        return response()->json($response);
    }
}
