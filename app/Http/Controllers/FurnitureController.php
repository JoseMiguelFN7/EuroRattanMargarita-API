<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Furniture;
use App\Models\Product;
use App\Models\Currency;
use App\Services\InventoryService;
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

        $query = Furniture::with([
            'furnitureType', 
            'product.images', 
            'product.stocks',
            'materials.materialTypes',
            'labors'
        ]);

        if ($search) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%");
            });
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
            'materials.materialTypes',
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
                'materials.materialTypes',
                'labors',
                'product.stocks'
            ])->get();

        $furnitures->map(function ($furniture) {
            $costSupplies = 0;
            $costUpholstery = 0;

            foreach ($furniture->materials as $material) {
                $subtotal = $material->price * $material->pivot->quantity;

                if ($material->materialTypes->contains('name', 'Tapicería')) {
                    $costUpholstery += $subtotal;
                } 
                elseif ($material->materialTypes->contains('name', 'Insumo')) {
                    $costSupplies += $subtotal;
                }
            }

            $costLabor = $furniture->labors->reduce(function ($carry, $labor) {
                return $carry + ($labor->daily_pay * $labor->pivot->days);
            }, 0);

            $furniture->costs = [
                'supplies'    => round($costSupplies, 2),
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
            'colors.*' => 'integer|exists:colors,id'
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

            $furniture = Furniture::create([
                'product_id' => $product->id,
                'furniture_type_id' => $request->furnitureType_id,
                'profit_per' => $request->profit_per,
                'paint_per' => $request->paint_per,
                'labor_fab_per' => $request->labor_fab_per
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

        $furniture = Furniture::with(['materials.product', 'product.colors'])->find($request->furniture_id);
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

            // --- A. Sumar el mueble terminado al inventario ---
            $this->inventoryService->recordMovement(
                $furniture->product_id,
                $quantityToBuild, // Positivo (Entrada)
                $colorToBuild,
                $now,
                $furniture
            );

            // --- B. Descontar los materiales utilizados ---
            // Si el servicio detecta que falta algún material, arrojará una excepción,
            // detendrá el ciclo foreach y mandará el error directamente al catch.
            foreach ($furniture->materials as $material) {
                $totalUsed = $material->pivot->quantity * $quantityToBuild;

                $this->inventoryService->recordMovement(
                    $material->product_id,
                    -$totalUsed, // Negativo (Salida controlada)
                    $material->pivot->color_id,
                    $now,
                    $furniture
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Fabricación registrada con éxito. Inventario actualizado.',
                'manufactured_quantity' => $quantityToBuild
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            // Atrapamos la excepción del InventoryService si hay un stock negativo
            // o cualquier otro error general.
            return response()->json([
                'message' => 'Ocurrió un error al procesar el inventario.',
                'error' => $e->getMessage()
            ], 400); // 400 Bad Request es más semántico para reglas de negocio que 500
        }
    }
}