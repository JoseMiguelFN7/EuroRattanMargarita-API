<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Material;
use App\Models\Furniture;
use App\Models\Currency;
use App\Models\Set;
use Illuminate\Support\Facades\DB;
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

        // --- 1. OBTENER LA TASA VES ACTUAL ---
        // Gracias a tu ajuste en el modelo, esto ya trae la tasa vigente correcta
        $vesCurrency = Currency::where('code', 'VES')->first();
        $vesRate = $vesCurrency ? $vesCurrency->current_rate : 0;

        // 2. CARGA DE RELACIONES PROFUNDA
        // Cargamos todo lo necesario para que las fórmulas de precio funcionen sin hacer consultas extra
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

        // 3. TRANSFORMACIÓN Y UNIFICACIÓN
        $products->transform(function ($product) use ($vesRate) { // <-- Pasamos $vesRate aquí
            
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

            // Asignamos el resultado al campo estandar 'price' (USD)
            $product->price = round($calculatedPrice, 2);

            // --- B. CÁLCULO EN BOLÍVARES (VES) ---
            // Multiplicamos el precio calculado por la tasa vigente y redondeamos
            $product->price_VES = round($calculatedPrice * $vesRate, 2);

            // --- C. LIMPIEZA DE IMÁGENES ---
            $product->images->each(function ($image) {
                $image->url = asset('storage/' . $image->url);
                $image->makeHidden(['created_at', 'updated_at', 'product_id']);
            });

            // --- D. LIMPIEZA FINAL ---
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

        // --- NUEVO: OBTENER LA TASA VES ACTUAL ---
        $vesCurrency = Currency::where('code', 'VES')->first();
        $vesRate = $vesCurrency ? $vesCurrency->current_rate : 0;
        // -----------------------------------------

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
            $product->material->price_VES = round($product->material->price * $vesRate, 2);

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

            // --- NUEVO: CÁLCULOS EN BOLÍVARES (VES) ---
            $furniture->pvp_natural_VES = round($precios['pvp_natural'] * $vesRate, 2);
            $furniture->pvp_color_VES   = round($precios['pvp_color'] * $vesRate, 2);

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

            // --- NUEVO: CÁLCULOS EN BOLÍVARES (VES) ---
            $set->pvp_natural_VES = round($precios['pvp_natural'] * $vesRate, 2);
            $set->pvp_color_VES   = round($precios['pvp_color'] * $vesRate, 2);

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
    public function ProductSearchByName(Request $request) // Quitamos $search de aquí
    {
        // 1. Configuración de parámetros
        $perPage = $request->input('per_page', 8);
        $search = $request->input('q'); // Capturamos la variable 'q' que envía Axios

        // --- NUEVO: OBTENER LA TASA VES ACTUAL ---
        $vesCurrency = Currency::where('code', 'VES')->first();
        $vesRate = $vesCurrency ? $vesCurrency->current_rate : 0;
        // -----------------------------------------

        // 2. Iniciamos el Query con las relaciones
        $query = Product::with([
            'images', 
            'colors',
            'material', 
            'furniture.materials.materialTypes', 
            'furniture.labors',
            'set.furnitures.materials.materialTypes',
            'set.furnitures.labors'
        ])->where('sell', true);

        // Aplicamos el filtro solo si viene algo en la búsqueda
        if ($search) {
            $query->where('name', 'LIKE', '%' . $search . '%');
        }

        // Ejecutamos la paginación
        $products = $query->paginate($perPage);

        // 3. Transformación
        $products->through(function ($product) use ($vesRate) {
            
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

            if($product->material){
                $product->material->price_VES = round($product->material->price * $vesRate, 2);
            }elseif($product->furniture){
                $precios = $product->furniture->calcularPrecios();
                
                $product->furniture->pvp_natural = $precios['pvp_natural'];
                $product->furniture->pvp_color = $precios['pvp_color'];
                
                $product->furniture->pvp_natural_VES = round($precios['pvp_natural'] * $vesRate, 2);
                $product->furniture->pvp_color_VES   = round($precios['pvp_color'] * $vesRate, 2);
            } elseif($product->set){
                $precios = $product->set->calcularPrecios();
            
                $product->set->pvp_natural = $precios['pvp_natural'];
                $product->set->pvp_color = $precios['pvp_color'];

                $product->set->pvp_natural_VES = round($precios['pvp_natural'] * $vesRate, 2);
                $product->set->pvp_color_VES   = round($precios['pvp_color'] * $vesRate, 2);
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
            'items.*.variantId' => 'nullable|integer', 
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $response = [];

        // 1. MAPEO DE COLORES
        $variantIds = collect($request->items)->pluck('variantId')->filter()->unique();
        $colorsMap = \App\Models\Color::whereIn('id', $variantIds)->pluck('is_natural', 'id');

        // 2. Carga de Productos
        $productRelations = [
            'images',
            'stocks',         // Para materiales y muebles individuales
            'material',
            'furniture.materials', 
            'furniture.labors',
            'set.furnitures.product.stocks', // CRÍTICO: Para el cuello de botella del set
            'set.furnitures.materials',
            'set.furnitures.labors'
        ];

        $productIds = collect($request->items)->pluck('productId')->unique();
        $productsData = \App\Models\Product::with($productRelations)->whereIn('id', $productIds)->get()->keyBy('id');

        // --- LA SOLUCIÓN AL PROBLEMA DE INVENTARIO COMPARTIDO ---
        // Aquí llevaremos la cuenta de cuánto stock físico vamos "consumiendo"
        // Estructura: [ productId => [ variantId => cantidad_reservada ] ]
        $allocatedStock = [];

        // Función Helper para obtener el stock físico real (para no repetir código)
        $getTotalStock = function ($productModel, $variantId, $isMaterial = false) {
            if ($isMaterial) {
                if ($variantId) {
                    $stockData = $productModel->stocks->where('colorID', $variantId)->first();
                    return $stockData ? $stockData->stock : 0;
                } else {
                    return $productModel->stocks->sum('stock');
                }
            } else {
                if ($variantId) {
                    $stockData = $productModel->stocks->where('colorID', $variantId)->first();
                    return $stockData ? $stockData->stock : 0;
                }
                return 0; // Muebles siempre requieren color
            }
        };

        foreach ($request->items as $item) {
            $product = $productsData[$item['productId']] ?? null;
            if (!$product) continue;

            $variantId = $item['variantId'] ?? null;
            $allocKey = $variantId ?? 'all'; // Llave para el array de reservas
            $reqQty = (int) $item['quantity'];
            
            $isNatural = $variantId ? ($colorsMap[$variantId] ?? true) : true;
            
            $price = 0;
            $maxCanBuy = 0; // Cuántos de ESTE item puede llevar basándonos en lo que queda libre

            // --- LÓGICA PARA JUEGOS (SETS) ---
            if ($product->set) {
                $precios = $product->set->calcularPrecios();
                $price = (!$isNatural) ? ($precios['pvp_color'] ?? $precios['pvp_natural']) : $precios['pvp_natural'];

                if ($variantId) {
                    $maxSetsPossible = 999999;
                    
                    // Recorremos cada mueble físico que conforma el set
                    foreach ($product->set->furnitures as $furniture) {
                        $fProduct = $furniture->product; // El producto físico real
                        
                        $fTotalStock = $getTotalStock($fProduct, $variantId, false);
                        $fAllocated  = $allocatedStock[$fProduct->id][$allocKey] ?? 0;
                        $fRemaining  = max(0, $fTotalStock - $fAllocated); // Stock libre

                        $qtyPerSet = $furniture->pivot->quantity;
                        $possible  = $qtyPerSet > 0 ? floor($fRemaining / $qtyPerSet) : 999999;

                        if ($possible < $maxSetsPossible) {
                            $maxSetsPossible = $possible;
                        }
                    }
                    $maxCanBuy = $maxSetsPossible;
                } else {
                    $maxCanBuy = 0;
                }

                // Hacemos la "Reserva en memoria" solo por la cantidad que SÍ puede comprar
                $actualAllocate = min($reqQty, $maxCanBuy);
                if ($actualAllocate > 0) {
                    foreach ($product->set->furnitures as $furniture) {
                        $fProduct = $furniture->product;
                        $qtyToDeduct = $actualAllocate * $furniture->pivot->quantity;
                        $allocatedStock[$fProduct->id][$allocKey] = ($allocatedStock[$fProduct->id][$allocKey] ?? 0) + $qtyToDeduct;
                    }
                }
            } 
            
            // --- LÓGICA PARA MUEBLES ---
            elseif ($product->furniture) {
                $precios = $product->furniture->calcularPrecios();
                $price = (!$isNatural) ? ($precios['pvp_color'] ?? $precios['pvp_natural']) : $precios['pvp_natural'];

                $fTotalStock = $getTotalStock($product, $variantId, false);
                $fAllocated  = $allocatedStock[$product->id][$allocKey] ?? 0;
                $maxCanBuy   = max(0, $fTotalStock - $fAllocated);

                // Hacemos la reserva en memoria
                $actualAllocate = min($reqQty, $maxCanBuy);
                if ($actualAllocate > 0) {
                    $allocatedStock[$product->id][$allocKey] = ($allocatedStock[$product->id][$allocKey] ?? 0) + $actualAllocate;
                }
            } 
            
            // --- LÓGICA PARA MATERIALES ---
            elseif ($product->material) {
                $price = $product->material->price; 

                $fTotalStock = $getTotalStock($product, $variantId, true);
                $fAllocated  = $allocatedStock[$product->id][$allocKey] ?? 0;
                $maxCanBuy   = max(0, $fTotalStock - $fAllocated);

                // Hacemos la reserva en memoria
                $actualAllocate = min($reqQty, $maxCanBuy);
                if ($actualAllocate > 0) {
                    $allocatedStock[$product->id][$allocKey] = ($allocatedStock[$product->id][$allocKey] ?? 0) + $actualAllocate;
                }
            }

            // --- Respuesta Final para el Frontend ---
            $response[] = [
                'id'                 => $product->id,
                'variant_id'         => $variantId,
                'name'               => $product->name,
                'image'              => $product->images->first() 
                                        ? asset('storage/' . $product->images->first()->url) 
                                        : null,
                'price'              => (float) $price,
                'discount'           => (float) $product->discount,
                'stock_available'    => (int) $maxCanBuy, // Stock libre *después* de procesar items anteriores
                'quantity'           => (int) $reqQty,
                'insufficient_stock' => $reqQty > $maxCanBuy,
            ];
        }

        return response()->json($response);
    }

    /**
     * Obtener listado de productos ajustables (Solo Materiales y Muebles) sin paginación
     */
    public function getAdjustableProducts()
    {
        // 1. Consulta optimizada
        // Filtramos para que traiga solo si tiene un material o un mueble asociado
        $products = Product::where(function ($query) {
                $query->has('material')->orHas('furniture');
            })
            // Solo cargamos las relaciones que necesitamos para la vista
            ->with(['colors', 'material.unit', 'furniture']) 
            ->get();

        // 2. Transformación estricta para el Frontend
        $cleanProducts = $products->map(function ($prod) {
            
            // Estructura base del Product interface
            $data = [
                'id'     => $prod->id,
                'name'   => $prod->name,
                'code'   => $prod->code,
                // Extraemos los colores limpios
                'colors' => $prod->colors->map(function ($color) {
                    return [
                        'id'   => $color->id,
                        'name' => $color->name,
                        'hex'  => $color->hex,
                    ];
                }),
            ];

            // Si es un material, incluimos la unidad
            if ($prod->material) {
                $data['material'] = [
                    'id'   => $prod->material->id,
                    'unit' => $prod->material->unit ? [
                        'id'   => $prod->material->unit->id,
                        'name' => $prod->material->unit->name,
                    ] : null,
                ];
            }

            // Si es un mueble, lo indicamos para que el frontend lo reconozca
            if ($prod->furniture) {
                $data['furniture'] = [
                    'id' => $prod->furniture->id,
                ];
            }

            return $data;
        });

        return response()->json($cleanProducts);
    }

    /**
     * Obtener el conteo total de productos y sus subcategorías
     */
    public function getProductCounts()
    {
        // Ejecutamos consultas COUNT directas a la base de datos por rendimiento
        $totalProducts   = Product::count();
        $totalMaterials  = Material::count();
        $totalFurnitures = Furniture::count();
        $totalSets       = Set::count();

        return response()->json([
            'products'   => $totalProducts,
            'materials'  => $totalMaterials,
            'furnitures' => $totalFurnitures,
            'sets'       => $totalSets,
        ]);
    }
}
