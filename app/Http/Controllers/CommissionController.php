<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\CommissionImage;
use App\Models\Furniture;
use App\Jobs\GenerateCommissionsPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommissionController extends Controller
{
    /**
     * Lista TODOS los encargos paginados (Para el panel de Euro Rattan / Staff)
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        $commissions = Commission::with([
            'user:id,name,email', // Datos básicos del cliente
            'order:id,code'       // Traemos el código de la orden si existe
        ])
        // 1. Búsqueda General (Texto en Código, Usuario u Orden)
        ->when($request->filled('search'), function ($query) use ($request) {
            $search = $request->search;
            
            // Agrupamos en un where() para que los orWhere no rompan los demás filtros
            $query->where(function ($q) use ($search) {
                // Busca en el código del encargo
                $q->where('code', 'like', '%' . $search . '%')
                  // O busca en la relación del Usuario (Nombre o Email)
                  ->orWhereHas('user', function ($qUser) use ($search) {
                      $qUser->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                  })
                  // O busca en la relación de la Orden (Código de la factura)
                  ->orWhereHas('order', function ($qOrder) use ($search) {
                      $qOrder->where('code', 'like', '%' . $search . '%');
                  });
            });
        })
        // 2. Filtro por Estado exacto
        ->when($request->filled('status'), function ($query) use ($request) {
            $query->where('status', $request->status);
        })
        // 3. Filtro por Rango de Fechas (Desde)
        ->when($request->filled('start_date'), function ($query) use ($request) {
            $query->whereDate('created_at', '>=', $request->start_date);
        })
        // 4. Filtro por Rango de Fechas (Hasta)
        ->when($request->filled('end_date'), function ($query) use ($request) {
            $query->whereDate('created_at', '<=', $request->end_date);
        })
        ->orderBy('created_at', 'desc')
        ->paginate($perPage)
        ->withQueryString(); // Mantiene los filtros en la URL al cambiar de página

        // Limpiamos los datos de la página actual usando through
        $commissions->through(function ($commission) {
            $commission->makeHidden(['user_id', 'order_id', 'updated_at']);
            return $commission;
        });

        return response()->json($commissions);
    }

    /**
     * Lista SOLO los encargos del usuario logueado (Para el perfil del Cliente)
     */
    public function myCommissions(Request $request)
    {
        $loggedUserId = auth('sanctum')->id();
        $perPage = $request->input('per_page', 10);

        $commissions = Commission::with(['order:id,code'])
            ->where('user_id', $loggedUserId)
            // Filtro por Código (Usamos LIKE para búsquedas parciales)
            ->when($request->filled('code'), function ($query) use ($request) {
                $query->where('code', 'like', '%' . $request->code . '%');
            })
            // Filtro por Estado exacto
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            // Filtro por Rango de Fechas (Desde)
            ->when($request->filled('start_date'), function ($query) use ($request) {
                $query->whereDate('created_at', '>=', $request->start_date);
            })
            // Filtro por Rango de Fechas (Hasta)
            ->when($request->filled('end_date'), function ($query) use ($request) {
                $query->whereDate('created_at', '<=', $request->end_date);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString(); // Mantiene los filtros en los links de las páginas siguientes

        // Usamos through para iterar, ocultar y retornar cada modelo modificado
        $commissions->through(function ($commission) {
            $commission->makeHidden(['user_id', 'order_id', 'updated_at']);
            
            return $commission;
        });

        return response()->json($commissions);
    }

    /**
     * El Cliente crea el encargo inicial
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string',
            'images' => 'nullable|array|max:5', // Máximo 5 imágenes
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        try {
            DB::beginTransaction();

            // Creamos el encargo (Por defecto el status es 'created')
            $commission = Commission::create([
                'user_id' => $request->user()->id,
                'description' => $request->description,
                'status' => 'created', 
            ]);

            // Guardamos las imágenes si el cliente las envió
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('commissions', 'public');
                    
                    CommissionImage::create([
                        'commission_id' => $commission->id,
                        'image_path' => $path,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Encargo creado exitosamente.',
                'commission' => $commission->load('images')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Hubo un error al crear el encargo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * El Hilo de Negociación (Staff y Cliente)
     */
    public function addSuggestion(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $commission = Commission::findOrFail($id);
        $loggedUserId = auth('sanctum')->id();

        // LÓGICA AUTOMÁTICA DE ROLES
        // Si el usuario logueado no es el dueño, asumimos que es el personal
        $isStaff = $commission->user_id !== $loggedUserId;

        // 1. Creamos la sugerencia
        $commission->suggestions()->create([
            'user_id' => $loggedUserId,
            'message' => $request->message,
            'is_staff' => $isStaff,
        ]);

        // 2. LA NUEVA MÁQUINA DE ESTADOS 
        if ($isStaff) {
            // Si responde Euro Rattan, la pelota pasa al cliente (Sugerencia enviada)
            $commission->update(['status' => 'suggestion_sent']);
        } else {
            // Si responde el cliente, el estado cambia a respondido (Sugerencia respondida)
            $commission->update(['status' => 'suggestion_replied']); 
        }

        return response()->json([
            'success' => true,
            'message' => 'Respuesta enviada.',
            'status_actual' => $commission->status,
            'autor' => $isStaff ? 'Personal de Euro Rattan' : 'Cliente'
        ]);
    }

    /**
     * Ver detalles completos de un encargo (Para el Staff)
     */
    public function show($code)
    {
        $commission = Commission::with([
            'user:id,name,email,cellphone', 
            'order.products', 
            'images',
            'suggestions' => function ($query) {
                $query->orderBy('created_at', 'asc'); 
            },
            'furnitures.furnitureType:id,name',
            'furnitures.product.images',
            'furnitures.product.colors',
            // CAMBIO CRÍTICO AQUÍ: Usamos la nueva relación singular y anidada
            'furnitures.materials.materialType.category', 
            'furnitures.labors'
        ])
        ->where('code', $code)
        ->firstOrFail();

        // Limpieza de datos base
        $commission->makeHidden(['user_id', 'order_id', 'updated_at']);
        $commission->images->makeHidden(['commission_id', 'created_at', 'updated_at']);
        $commission->suggestions->makeHidden(['commission_id', 'user_id', 'updated_at']);
        
        if ($commission->furnitures) {
            $commission->furnitures->each(function ($furniture) use ($commission) {
                $product = $furniture->product;

                // 1. Formatear URLs de imágenes
                if ($product && $product->images) {
                    $product->images->each(function ($image) {
                        $image->url = asset('storage/' . $image->url);
                        $image->makeHidden(['created_at', 'updated_at', 'product_id']);
                    });
                }

                // 2. Limpiar colores
                if ($product && $product->colors) {
                    $product->colors->makeHidden(['pivot', 'created_at', 'updated_at']);
                }

                // 3. Calcular precios (Ahora funcionará ultra rápido gracias a la carga ansiosa)
                $precios = $furniture->calcularPrecios();
                $furniture->pvp_natural = $precios['pvp_natural'];
                $furniture->pvp_color = $precios['pvp_color'];

                // 4. Lógica de Cantidad y Colores Confirmados
                $furniture->confirmed_quantity = 0;
                $furniture->confirmed_colors = []; 

                if ($commission->order && $product) {
                    // Traemos TODOS los items de este producto en la orden (por si pidió varios colores)
                    $orderProducts = $commission->order->products->where('id', $product->id);
                    
                    $totalQuantity = 0;
                    $colorsBreakdown = [];

                    foreach ($orderProducts as $orderItem) {
                        $qty = $orderItem->pivot->quantity;
                        $colorId = $orderItem->pivot->variant_id; // variante = color_id
                        
                        $totalQuantity += $qty;

                        // Buscamos el nombre del color en la relación que ya cargamos
                        $colorData = null;
                        if ($colorId) {
                            $colorObj = $product->colors->firstWhere('id', $colorId);
                            $colorData = $colorObj ? ['id' => $colorObj->id, 'name' => $colorObj->name] : null;
                        } else {
                            $colorData = ['id' => null, 'name' => 'Natural / Sin Pintar'];
                        }

                        // Agrupamos por color para evitar duplicados si hay varias líneas del mismo
                        $colorKey = $colorId ?? 'natural';
                        if (!isset($colorsBreakdown[$colorKey])) {
                            $colorsBreakdown[$colorKey] = [
                                'color' => $colorData,
                                'quantity' => 0
                            ];
                        }
                        $colorsBreakdown[$colorKey]['quantity'] += $qty;
                    }

                    $furniture->confirmed_quantity = $totalQuantity;
                    // array_values quita las llaves personalizadas y lo deja como un array JSON limpio
                    $furniture->confirmed_colors = array_values($colorsBreakdown); 
                }

                // Limpieza de atributos
                if ($product) {
                    $product->makeHidden(['created_at', 'updated_at']);
                }
                if ($furniture->furnitureType) {
                    $furniture->furnitureType->makeHidden(['created_at', 'updated_at']);
                }

                $furniture->makeHidden(['commission_id', 'materials', 'labors', 'product_id', 'furniture_type_id', 'created_at', 'updated_at']);
            });
        }

        // Limpiamos los productos de la orden para no enviar data duplicada al frontend
        if ($commission->order) {
            $commission->order->makeHidden(['products', 'created_at', 'updated_at', 'user_id']);
        }

        return response()->json($commission);
    }

    /**
     * Ver detalles del encargo propio (Para el Cliente)
     */
    public function showMyCommission($code)
    {
        $loggedUserId = auth('sanctum')->id();

        $commission = Commission::with([
            'user:id,name',
            'order.products', 
            'images',
            'suggestions' => function ($query) {
                $query->orderBy('created_at', 'asc'); 
            },
            'furnitures.furnitureType:id,name',
            'furnitures.product.images',
            'furnitures.product.colors',
            // CAMBIO CRÍTICO: Nueva estructura singular y anidada
            'furnitures.materials.materialType.category',
            'furnitures.labors'
        ])
        ->where('code', $code)
        ->where('user_id', $loggedUserId)
        ->firstOrFail();

        // Limpieza de datos base
        $commission->makeHidden(['user_id', 'order_id', 'updated_at']);
        $commission->images->makeHidden(['commission_id', 'created_at', 'updated_at']);
        $commission->suggestions->makeHidden(['commission_id', 'user_id', 'updated_at']);
        
        if ($commission->furnitures) {
            $commission->furnitures->each(function ($furniture) use ($commission) {
                $product = $furniture->product;

                if ($product && $product->images) {
                    $product->images->each(function ($image) {
                        $image->url = asset('storage/' . $image->url);
                        $image->makeHidden(['created_at', 'updated_at', 'product_id']);
                    });
                }

                if ($product && $product->colors) {
                    $product->colors->makeHidden(['pivot', 'created_at', 'updated_at']);
                }

                // Calcular precios (ahora corre súper rápido por la carga ansiosa)
                $precios = $furniture->calcularPrecios();
                $furniture->pvp_natural = $precios['pvp_natural'];
                $furniture->pvp_color = $precios['pvp_color'];

                // Cantidad y Colores Confirmados
                $furniture->confirmed_quantity = 0;
                $furniture->confirmed_colors = [];

                if ($commission->order && $product) {
                    $orderProducts = $commission->order->products->where('id', $product->id);
                    
                    $totalQuantity = 0;
                    $colorsBreakdown = [];

                    foreach ($orderProducts as $orderItem) {
                        $qty = $orderItem->pivot->quantity;
                        $colorId = $orderItem->pivot->variant_id;
                        
                        $totalQuantity += $qty;

                        $colorData = null;
                        if ($colorId) {
                            $colorObj = $product->colors->firstWhere('id', $colorId);
                            $colorData = $colorObj ? ['id' => $colorObj->id, 'name' => $colorObj->name] : null;
                        } else {
                            $colorData = ['id' => null, 'name' => 'Natural / Sin Pintar'];
                        }

                        $colorKey = $colorId ?? 'natural';
                        if (!isset($colorsBreakdown[$colorKey])) {
                            $colorsBreakdown[$colorKey] = [
                                'color' => $colorData,
                                'quantity' => 0
                            ];
                        }
                        $colorsBreakdown[$colorKey]['quantity'] += $qty;
                    }

                    $furniture->confirmed_quantity = $totalQuantity;
                    $furniture->confirmed_colors = array_values($colorsBreakdown);
                }

                // Limpieza de atributos
                if ($product) {
                    $product->makeHidden(['created_at', 'updated_at']);
                }
                if ($furniture->furnitureType) {
                    $furniture->furnitureType->makeHidden(['created_at', 'updated_at']);
                }

                $furniture->makeHidden(['commission_id', 'materials', 'labors', 'product_id', 'furniture_type_id', 'created_at', 'updated_at']);
            });
        }

        // Limpieza final de la orden
        if ($commission->order) {
            $commission->order->makeHidden(['products', 'created_at', 'updated_at', 'user_id']);
        }

        return response()->json($commission);
    }

    /**
     * Euro Rattan aprueba el encargo para pasar a Producción/Cotización
     */
    public function approve($id)
    {
        $commission = Commission::findOrFail($id);

        $commission->update(['status' => 'approved']);

        return response()->json([
            'success' => true,
            'message' => 'Encargo aprobado exitosamente. Listo para generar los muebles y la cotización.',
            'commission' => $commission
        ]);
    }

    public function cancel($id)
    {
        $commission = Commission::with('order')->find($id);

        if (!$commission) {
            return response()->json(['message' => 'Encargo no encontrado.'], 404);
        }

        // 1. Verificamos si ya está cancelado
        if ($commission->status === 'rejected') {
            return response()->json([
                'message' => 'Este encargo ya se encuentra cancelado o rechazado.'
            ], 400);
        }

        // 2. EL ESCUDO: Verificamos si ya tiene una orden activa asociada.
        // Si tiene una orden y no está cancelada, obligamos a cancelar la orden primero
        // para que se dispare la reversión de inventario del OrderObserver.
        if ($commission->order && $commission->order->status !== 'cancelled') {
            return response()->json([
                'message' => 'No puedes cancelar este encargo directamente porque ya tiene la Orden #' . $commission->order->code . ' generada. Debes cancelar la orden para revertir el inventario correctamente.'
            ], 400);
        }

        // 3. Si pasó las validaciones, simplemente actualizamos el estado
        $commission->update(['status' => 'rejected']);

        return response()->json([
            'message' => 'Encargo cancelado exitosamente.',
            'data' => [
                'id' => $commission->id,
                'code' => $commission->code,
                'status' => $commission->status
            ]
        ], 200);
    }

    /**
     * 4. Finaliza la cotización (Pasa el estado a 'quoted')
     */
    public function markAsQuoted($code)
    {
        // Buscamos el encargo por su código alfanumérico
        $commission = Commission::where('code', $code)->firstOrFail();

        // Validación de seguridad: 
        // Evitamos cotizar un encargo vacio
        if ($commission->furnitures()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes enviar la cotización porque aún no has agregado ningún mueble a este encargo.'
            ], 422);
        }

        // Todo en orden, pasamos a estado cotizado
        $commission->update(['status' => 'quoted']);

        return response()->json([
            'success' => true,
            'message' => 'Cotización finalizada y enviada. El cliente ya puede proceder con la selección y el pago.',
            'status_actual' => $commission->status
        ]);
    }

    /**
     * Obtiene la cotización (muebles) de un encargo específico usando su código
     */
    public function getQuotationByCommission(Request $request, $code)
    {
        // Mantenemos la paginación y la búsqueda por si en el futuro un encargo tiene muchos muebles
        $perPage = $request->input('per_page', 8);
        $search  = $request->input('search');

        // Iniciamos la consulta base con las mismas relaciones
        $query = Furniture::with([
            'furnitureType', 
            'product.images', 
            'product.stocks',
            'materials.materialType.category',
            'labors'
        ])
        // EL FILTRO CLAVE: Solo los muebles que pertenezcan al encargo con este código
        ->whereHas('commission', function ($q) use ($code) {
            $q->where('code', $code);
        });

        // Mantenemos tu lógica de búsqueda intacta
        if ($search) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        $furnitures = $query->paginate($perPage);

        // Mantenemos exactamente tu misma lógica de formateo y limpieza
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
            
            // Agregué 'commission_id' al hidden para que quede totalmente limpio
            $furniture->makeHidden(['materials', 'labors', 'product_id', 'furniture_type_id', 'commission_id', 'created_at', 'updated_at']);

            return $furniture;
        });

        return response()->json($furnitures);
    }

    public function exportPdf(Request $request)
    {
        $search    = $request->input('search');
        $status    = $request->input('status');
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
        $userId    = auth()->id();

        // Despachamos el Job pasándole todos los filtros
        GenerateCommissionsPdf::dispatch($search, $status, $startDate, $endDate, $userId);

        return response()->json([
            'message' => 'Generando reporte. Te notificaremos cuando esté listo.',
            'status' => 'processing'
        ]);
    }
}