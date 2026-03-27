<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Furniture;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\Color;
use App\Models\Currency;
use App\Services\InventoryService;
use App\Jobs\GenerateFurnituresPdf;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class FurnitureController extends Controller
{
    protected $inventoryService;

    // --- NUEVO: Inyectamos el servicio en el constructor ---
    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 8);
        $search  = $request->input('search');
        $typeId  = $request->input('type_id');

        $query = Furniture::with([
            'furnitureType', 
            'product.images', 
            'product.stocks',
            'materials.materialType.category',
            'labors'
        ]);

        if ($search) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        if ($typeId) {
            $query->where('furniture_type_id', $typeId); 
        }

        $furnitures = $query->paginate($perPage);

        $furnitures->through(function ($furniture) {
            $product = $furniture->product;

            if ($product && $product->images) {
                $product->images->each(function ($image) {
                    $image->url = asset('storage/' . $image->url);
                    $image->makeHidden(['created_at', 'updated_at', 'product_id']);
                });
            }

            $precios = $furniture->calcularPrecios();
            $furniture->pvp_natural = $precios['pvp_natural'];
            $furniture->pvp_color = $precios['pvp_color'];

            if ($product && $product->stocks) {
                $product->stocks->makeHidden(['productID', 'productCode']);
            }

            if ($product) {
                $product->makeHidden(['created_at', 'updated_at']);
            }

            if ($furniture->furnitureType) {
                $furniture->furnitureType->makeHidden(['created_at', 'updated_at']);
            }
            
            $furniture->makeHidden(['materials', 'labors', 'product_id', 'furniture_type_id', 'created_at', 'updated_at']);

            return $furniture;
        });

        return response()->json($furnitures);
    }

    public function indexSell(Request $request)
    {
        $perPage = $request->input('per_page', 8);
        $furnitureTypeIds = $request->input('furniture_type_id');

        $vesCurrency = Currency::where('code', 'VES')->first();
        $vesRate = $vesCurrency ? $vesCurrency->current_rate : 0;

        $query = Furniture::whereHas('product', function ($q) {
            $q->where('sell', true);
        });

        if (!empty($furnitureTypeIds)) {
            $typesArray = is_array($furnitureTypeIds) 
                ? $furnitureTypeIds 
                : explode(',', $furnitureTypeIds);
            
            $query->whereIn('furniture_type_id', $typesArray);
        }

        $furnitures = $query->with([
            'furnitureType', 
            'product.images', 
            'product.stocks',
            'materials.materialType.category', // <-- CAMBIO CRÍTICO: Nueva estructura anidada
            'labors'
        ])->paginate($perPage);

        $furnitures->through(function ($furniture) use ($vesRate) {
            $product = $furniture->product;

            if ($product && $product->images) {
                $product->images->each(function ($image) {
                    $image->url = asset('storage/' . $image->url);
                    $image->makeHidden(['created_at', 'updated_at', 'product_id']);
                });
            }

            // Al tener la carga ansiosa correcta arriba, este método se ejecuta al instante
            $precios = $furniture->calcularPrecios();
            $furniture->pvp_natural = $precios['pvp_natural'];
            $furniture->pvp_color = $precios['pvp_color'];

            $furniture->pvp_natural_VES = round($precios['pvp_natural'] * $vesRate, 2);
            $furniture->pvp_color_VES   = round($precios['pvp_color'] * $vesRate, 2);

            if ($product && $product->stocks) {
                $product->stocks->makeHidden(['productID', 'productCode']);
            }

            if ($product) {
                $product->makeHidden(['created_at', 'updated_at']);
            }

            if ($furniture->furnitureType) {
                $furniture->furnitureType->makeHidden(['created_at', 'updated_at']);
            }
            
            $furniture->makeHidden(['materials', 'labors', 'product_id', 'furniture_type_id', 'created_at', 'updated_at']);

            return $furniture;
        });

        return response()->json($furnitures);
    }

    public function listAll()
    {
        $furnitures = Furniture::whereHas('product', function ($query) {
                $query->where('sell', true); 
            })
            ->with([
                'furnitureType', 
                'materials.materialType.category', // <-- CAMBIO 1: Relación anidada singular
                'labors',
                'product.stocks'
            ])->get();

        $furnitures->map(function ($furniture) {
            $costStructural = 0; // <-- CAMBIO 3: Renombrado por coherencia
            $costUpholstery = 0;

            foreach ($furniture->materials as $material) {
                $subtotal = $material->price * $material->pivot->quantity;
                
                // Extraemos el nombre de la categoría para no repetir código
                $categoriaName = $material->materialType->category->name ?? '';

                // <-- CAMBIO 2: Evaluación directa
                if ($categoriaName === 'Tapicería') {
                    $costUpholstery += $subtotal;
                } 
                elseif ($categoriaName === 'Estructural') {
                    $costStructural += $subtotal;
                }
            }

            $costLabor = $furniture->labors->reduce(function ($carry, $labor) {
                return $carry + ($labor->daily_pay * $labor->pivot->days);
            }, 0);

            $furniture->costs = [
                'structural'  => round($costStructural, 2), // <-- CAMBIO 3: Nueva llave para el front
                'upholstery'  => round($costUpholstery, 2),
                'labor'       => round($costLabor, 2),
            ];

            $product = $furniture->product;

            if ($product) {
                $product->makeHidden(['sell', 'description', 'discount', 'created_at', 'updated_at']);
                
                if ($product->stocks) {
                    $product->stocks->makeHidden(['productID', 'productCode']);
                }
            }

            if ($furniture->furnitureType) {
                $furniture->furnitureType->makeHidden(['created_at', 'updated_at']);
            }

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

    public function show($id)
    {
        $furniture = Furniture::with(['furnitureType', 'product.images', 'product.colors'])->find($id);

        if(!$furniture){
            return response()->json(['message'=>'Mueble no encontrado'], 404);
        }

        $product = $furniture->product;

        $product->images = $product->images->map(function ($image) {
            return asset('storage/' . $image->url);
        });

        return response()->json($furniture);
    }

    public function showCod($cod)
    {
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

        if ($furniture->product) {
            $prod = $furniture->product;
            
            $prod->makeHidden(['created_at', 'updated_at']); 

            $prod->images->each(function ($image) {
                $image->url = asset('storage/' . $image->url);
                $image->makeHidden(['created_at', 'updated_at', 'product_id']);
            });

            if ($prod->colors) {
                $prod->colors->makeHidden(['pivot', 'created_at', 'updated_at']);
            }
        }

        $furniture->makeHidden(['product_id', 'furniture_type_id', 'created_at', 'updated_at']);

        if ($furniture->materials) {
            $furniture->materials->makeHidden(['created_at', 'updated_at', 'min_stock', 'max_stock']);
            
            $furniture->materials->each(function($mat){
                if($mat->pivot) $mat->pivot->makeHidden(['created_at', 'updated_at']);
            });
        }

        if ($furniture->labors) {
            $furniture->labors->makeHidden(['created_at', 'updated_at']);
        }

        if ($furniture->furnitureType) {
            $furniture->furnitureType->makeHidden(['created_at', 'updated_at']);
        }

        return response()->json($furniture);
    }

    public function rand($quantity)
    {
        if (!is_numeric($quantity) || $quantity <= 0) {
            return response()->json([
                'error' => 'La cantidad debe ser un número entero positivo.'
            ], 400);
        }

        $furnitures = Product::with(['furnitureType', 'product.images'])
            ->whereHas('product', function ($query) {
                $query->where('sell', true);
            })
            ->inRandomOrder()
            ->take($quantity)
            ->get()
            ->map(function ($furniture) {
                $furniture->product->image = $furniture->product->images->first() 
                    ? asset('storage/' . $furniture->product->images->first()->url) 
                    : null;
                return $furniture;
            });

        return response()->json($furnitures);
    }

    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:products',
            'description' => 'required|string|max:500',
            'furnitureType_id' => 'required|integer|exists:furniture_types,id',
            'materials' => 'required|array',
            'materials.*.id' => 'required|integer|exists:materials,id',
            'materials.*.quantity' => 'required|numeric|min:0',
            'materials.*.color_id' => 'nullable|integer|exists:colors,id',
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
            'colors.*' => 'integer|exists:colors,id',
            'commission_code' => 'nullable|string|exists:commissions,code'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $product = Product::create([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'sell' => $request->sell,
                'discount' => $request->discount
            ]);

            // Buscamos el ID del encargo usando el código provisto (si existe)
            // Usamos value('id') para que la consulta sea ultra ligera y traiga solo ese número
            $commissionId = null;
            if ($request->filled('commission_code')) {
                $commissionId = \App\Models\Commission::where('code', $request->commission_code)->value('id');
            }

            $furniture = Furniture::create([
                'product_id' => $product->id,
                'furniture_type_id' => $request->furnitureType_id,
                'profit_per' => $request->profit_per,
                'paint_per' => $request->paint_per,
                'labor_fab_per' => $request->labor_fab_per,
                'commission_id' => $commissionId,
            ]);

            if ($request->hasFile('images')) {
                $files = $request->file('images');
                app(ProductImageController::class)->uploadImages($product->id, $files);
            }

            $materialsData = [];
            foreach ($request->materials as $material) {
                $materialsData[$material['id']] = [
                    'quantity' => $material['quantity'],
                    'color_id' => $material['color_id'] ?? null 
                ];
            }
            $furniture->materials()->sync($materialsData);

            $laborsData = [];
            foreach ($request->labors as $labor) {
                $laborsData[$labor['id']] = ['days' => $labor['days']];
            }
            $furniture->labors()->sync($laborsData);

            if ($request->has('colors')) {
                $colorIds = $request->input('colors');
                $product->colors()->sync($colorIds);
            }

            DB::commit();

            return response()->json($furniture, 201);

        } catch (\Exception $e) {
            DB::rollback();
            
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

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => ['sometimes', 'required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('products', 'code')->ignore($product->id)],
            'description' => 'sometimes|required|string|max:500',
            'furnitureType_id' => 'sometimes|required|integer|exists:furniture_types,id',
            'materials' => 'sometimes|required|array',
            'materials.*.id' => 'required|integer|exists:materials,id',
            'materials.*.quantity' => 'required|numeric|min:0',
            'materials.*.color_id' => 'nullable|integer|exists:colors,id',
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

        if ($request->has('colors')) {
            $requestedColors = $request->input('colors');
            
            // 1. Obtenemos los colores que el producto (mueble) tiene ACTUALMENTE
            $currentColors = $product->colors()->pluck('colors.id')->toArray();
            
            // 2. Comparamos para aislar los colores que el usuario intenta QUITAR
            $colorsToDetach = array_diff($currentColors, $requestedColors);

            if (!empty($colorsToDetach)) {
                // 3. Buscamos si existe algún movimiento histórico para este mueble y esos colores
                $colorsWithMovements = ProductMovement::where('product_id', $product->id)
                    ->whereIn('color_id', $colorsToDetach)
                    ->pluck('color_id')
                    ->unique()
                    ->toArray();

                // 4. Bloqueamos la petición si hay conflictos
                if (!empty($colorsWithMovements)) {
                    $colorNames = Color::whereIn('id', $colorsWithMovements)->pluck('name')->implode(', ');
                    
                    return response()->json([
                        'message' => 'Validación de inventario fallida.',
                        'errors' => [
                            'colors' => ["No puedes desvincular los siguientes colores porque este mueble ya tiene movimientos de inventario registrados con ellos: {$colorNames}."]
                        ]
                    ], 422);
                }
            }
        }

        DB::beginTransaction();

        try {
            $product->fill($request->only([
                'name', 'code', 'description', 'sell', 'discount'
            ]));

            if ($request->has('furnitureType_id')) {
                $furniture->furniture_type_id = $request->furnitureType_id;
            }
            
            $furniture->fill($request->only([
                'profit_per', 'paint_per', 'labor_fab_per'
            ]));

            if ($request->has('kept_images')) {
                $keptIds = $request->input('kept_images');
                if (!is_array($keptIds)) $keptIds = [];

                $imagesToDelete = $product->images()->whereNotIn('id', $keptIds)->get();

                foreach ($imagesToDelete as $img) {
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($img->url)) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($img->url);
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

            if ($request->has('materials')) {
                $materialsData = collect($request->materials)->mapWithKeys(function ($material) {
                    return [
                        $material['id'] => [
                            'quantity' => $material['quantity'],
                            'color_id' => $material['color_id'] ?? null 
                        ]
                    ];
                })->toArray();
                
                $furniture->materials()->sync($materialsData);
            }

            if ($request->has('labors')) {
                $laborsData = collect($request->labors)->mapWithKeys(function ($labor) {
                    return [$labor['id'] => ['days' => $labor['days']]];
                })->toArray();
                $furniture->labors()->sync($laborsData);
            }

            if ($request->has('colors')) {
                $colorIds = $request->input('colors');
                $product->colors()->sync($colorIds);
            }

            $product->save();
            $furniture->save();
            
            DB::commit();

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

    public function destroy($id){
        DB::beginTransaction();

        try{
            $furniture = Furniture::find($id);

            if(!$furniture){
                return response()->json(['message' => 'Mueble no encontrado'], 404);
            }

            $product = $furniture->product;

            $furniture->materials()->detach();
            $furniture->labors()->detach();

            $furniture->delete();
            
            if ($product) {
                $product->colors()->detach();

                app(ProductImageController::class)->deleteImages($product->id);
                
                $product->delete();
            }

            DB::commit();

            return response()->json(['message' => 'Mueble eliminado correctamente'], 200);

        } catch (\Exception $e){
            DB::rollBack();

            return response()->json([
                'message' => 'Error al eliminar el mueble.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function manufacture(Request $request)
    {
        $request->validate([
            'furniture_id' => 'required|integer|exists:furnitures,id',
            'quantity' => 'required|integer|min:1',
            'furniture_color_id' => 'required|integer|exists:colors,id'
        ]);

        // Ya no necesitamos cargar 'materials.product' aquí porque el modelo Furniture se encarga de eso internamente
        $furniture = Furniture::with(['product.colors'])->findOrFail($request->furniture_id);
        $quantityToBuild = $request->quantity;
        $colorToBuild = $request->furniture_color_id;

        $allowedColors = $furniture->product->colors->pluck('id')->toArray();
        
        if (!in_array($colorToBuild, $allowedColors)) {
            return response()->json([
                'message' => 'El color seleccionado no está asociado a este mueble.',
                'allowed_colors' => $furniture->product->colors 
            ], 422); 
        }

        DB::beginTransaction();

        try {
            $now = now();

            $furniture->manufacture(
                $quantityToBuild,
                $colorToBuild,
                $this->inventoryService,
                $furniture, 
                $now
            );

            DB::commit();

            return response()->json([
                'message' => 'Fabricación registrada con éxito. Inventario actualizado.',
                'manufactured_quantity' => $quantityToBuild
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Ocurrió un error al procesar el inventario.',
                'error' => $e->getMessage()
            ], 400); 
        }
    }

    public function checkManufacturability(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.productId' => 'required|exists:products,id',
            'items.*.variantId' => 'nullable|integer', 
            'items.*.quantity'  => 'required|integer|min:1',
        ]);

        // Iniciamos la transacción para nuestra "simulación"
        DB::beginTransaction();

        try {
            $now = now();

            foreach ($request->items as $item) {
                // Buscamos el modelo Furniture asociado a ese producto
                $furniture = Furniture::where('product_id', $item['productId'])->first();

                if (!$furniture) {
                    throw new \Exception("El producto no es un mueble fabricable.");
                }

                // Simulamos la fabricación. 
                // Si falta material, tu InventoryService arrojará una excepción y saltaremos al catch.
                $furniture->manufacture(
                    $item['quantity'],
                    $item['variantId'] ?? null,
                    $this->inventoryService,
                    $furniture,
                    $now
                );
            }

            // Si llegamos a esta línea, significa que hay materiales de sobra para TODO el pedido.
            // MUY IMPORTANTE: Hacemos rollBack porque esto era solo una prueba, no queremos guardar nada.
            DB::rollBack();

            return response()->json([
                'can_manufacture' => true,
                'message' => 'Todo listo para la fabricación.'
            ], 200);

        } catch (\Exception $e) {
            // Faltó algún material o hubo un error en la simulación.
            // Revertimos cualquier movimiento parcial que se haya simulado en el bucle.
            DB::rollBack();

            // Devolvemos exactamente lo que pediste: sin dar detalles al usuario.
            return response()->json([
                'can_manufacture' => false,
                'message' => 'Actualmente no se pueden fabricar los muebles solicitados.'
            ], 400); // Puedes usar 400 o 422 según lo maneje tu frontend
        }
    }

    public function exportPdf(Request $request)
    {
        $search = $request->input('search');
        $typeId = $request->input('type_id'); // Un solo ID
        $userId = auth()->id();

        // Despachamos el Job (recuerda importar la clase GenerateFurnituresPdf arriba)
        GenerateFurnituresPdf::dispatch($search, $typeId, $userId);

        return response()->json([
            'message' => 'Generando reporte. Te notificaremos cuando esté listo.',
            'status' => 'processing'
        ]);
    }
}