<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Broadcast;

// Endpoint para verificar conexión con Reverb
Broadcast::routes(['middleware' => ['auth:sanctum']]);

// Rutas para el CRUD de usuarios
Route::post('/user/login',[\App\Http\Controllers\UserController::class, 'login']); //Login
Route::post('/user/auth/send-otp', [\App\Http\Controllers\UserController::class, 'sendRegistrationOtp']); // Enviar OTP para verificar correo
Route::post('/user/register',[\App\Http\Controllers\UserController::class, 'register']); //Register
Route::post('/user/auth/forgot-password/send-otp', [\App\Http\Controllers\UserController::class, 'sendResetOtp']); // Enviar OTP para flujo Olvidé Contraseña
Route::post('/user/auth/forgot-password/verify-otp', [\App\Http\Controllers\UserController::class, 'verifyResetOtp']); // Verificar OTP para flujo Olvidé Contraseña
Route::post('/user/auth/forgot-password/reset', [\App\Http\Controllers\UserController::class, 'resetPassword']); // Reset contraseña para flujo Olvidé Contraseña

// Rutas para el CRUD de productos
Route::get('/products', [\App\Http\Controllers\ProductController::class, 'index']); // Obtener todos los productos
Route::get('/product/id/{id}', [\App\Http\Controllers\ProductController::class, 'show']); // Obtener un producto específico por id
Route::get('/products/random-by-types', [\App\Http\Controllers\ProductController::class, 'randomByTypes']); // Obtener productos aleatorios balanceados por tipos (materiales, muebles, juegos)
Route::get('/products/{quantity}', [\App\Http\Controllers\ProductController::class, 'rand']); // Obtener una cantidad de productos en orden aleatorio
Route::get('/product/cod/{cod}', [\App\Http\Controllers\ProductController::class, 'showCod']); // Obtener un producto específico por Cod
Route::get('/product/search/', [\App\Http\Controllers\ProductController::class, 'ProductSearchByName']); // Obtener producto en base a busqueda por nombre

// Rutas para el CRUD de stocks
Route::get('/product/stock/{id}', [\App\Http\Controllers\ProductController::class, 'showStock']); // Obtener un stock específico

// Rutas para el CRUD de tipos de materiales
Route::get('/materialTypes', [\App\Http\Controllers\MaterialTypeController::class, 'index']); // Obtener todos los tipos de material
Route::get('/materialType/{id}', [\App\Http\Controllers\MaterialTypeController::class, 'show']); // Obtener un tipo de material específico

// Rutas para el CRUD de materiales
Route::get('/materials', [\App\Http\Controllers\MaterialController::class, 'index']); // Obtener todos los materiales con paginacion
Route::get('/materials/sell', [\App\Http\Controllers\MaterialController::class, 'indexSell']); // Obtener todos los materiales a la venta con paginacion
Route::get('/material/cod/{cod}', [\App\Http\Controllers\MaterialController::class, 'showCod']); // Obtener un material específico
Route::get('/material/{id}', [\App\Http\Controllers\MaterialController::class, 'show']); // Obtener un material específico
Route::get('/materials/{quantity}', [\App\Http\Controllers\MaterialController::class, 'rand']); // Obtener una cantidad de materiales en orden aleatorio
Route::get('/materialsByType/name/{name}', [\App\Http\Controllers\MaterialController::class, 'indexByMaterialType']); // Obtener materiales segun tipo
Route::get('/materialsByType/{quantity}', [\App\Http\Controllers\MaterialController::class, 'randByMaterialType']); // Obtener una cantidad de materiales en orden aleatorio segun tipos

// Rutas para el CRUD de unidades
Route::get('/units', [\App\Http\Controllers\UnitController::class, 'index']); // Obtener todas las unidades
Route::get('/unit/{id}', [\App\Http\Controllers\UnitController::class, 'show']); // Obtener una unidad específica

// Rutas para el CRUD de muebles
Route::get('/furnitures', [\App\Http\Controllers\FurnitureController::class, 'index']); // Obtener todos los muebles con paginacion
Route::get('/furnitures/sell', [\App\Http\Controllers\FurnitureController::class, 'indexSell']); // Obtener todos los muebles a la venta con paginacion
Route::get('/furniture/cod/{cod}', [\App\Http\Controllers\FurnitureController::class, 'showCod']); // Obtener un mueble específico
Route::get('/furniture/{id}', [\App\Http\Controllers\FurnitureController::class, 'show']); // Obtener un mueble específico
Route::get('/furnitures/{quantity}', [\App\Http\Controllers\FurnitureController::class, 'rand']); // Obtener una cantidad de muebles en orden aleatorio


// Rutas para el CRUD de tipos de muebles
Route::get('/furnitureTypes', [\App\Http\Controllers\FurnitureTypeController::class, 'index']); // Obtener todos los tipos de material
Route::get('/furnitureType/name/{name}', [\App\Http\Controllers\FurnitureTypeController::class, 'showByName']); // Obtener un tipo de mueble específico por nombre
Route::get('/furnitureType/{id}', [\App\Http\Controllers\FurnitureTypeController::class, 'show']); // Obtener un tipo de mueble específico por id

// Rutas para el CRUD de juegos
Route::get('/sets', [\App\Http\Controllers\SetController::class, 'index']); // Obtener todos los juegos con paginacion
Route::get('/sets/sell', [\App\Http\Controllers\SetController::class, 'indexSell']); // Obtener todos los juegos a la venta con paginacion
Route::get('/set/{id}', [\App\Http\Controllers\SetController::class, 'show']); // Obtener un juego específico
Route::get('/sets/{quantity}', [\App\Http\Controllers\SetController::class, 'rand']); // Obtener una cantidad de juegos en orden aleatorio

// Rutas para el CRUD de tipos de juegos
Route::get('/setTypes', [\App\Http\Controllers\SetTypeController::class, 'index']); // Obtener todos los tipos de juego
Route::get('/setType/{id}', [\App\Http\Controllers\SetTypeController::class, 'show']); // Obtener un tipo de juego específico

// Ruta para obtener tasa de cambio
Route::get('/currencyExchange/{code}/latest', [\App\Http\Controllers\CurrencyExchangeController::class, 'latest']);

// Rutas para imagenes de banners
Route::get('/banners/active', [\App\Http\Controllers\BannerImageController::class, 'active']); //Obtener imagenes activas para index de tienda

Route::middleware(['auth:sanctum'])->group(function () {
    // Rutas para el CRUD de usuarios
    Route::get('/user/auth', [\App\Http\Controllers\UserController::class, 'getAuth']); //Obtener datos de usuario autenticado
    Route::post('/user/auth', [\App\Http\Controllers\UserController::class, 'updateAuthUser']); //Actualizar datos de usuario autenticado
    Route::delete('/user/auth', [\App\Http\Controllers\UserController::class, 'destroyAuthUser']); //Eliminar usuario autenticado
    Route::get('/users', [\App\Http\Controllers\UserController::class, 'index']); // Obtener todos los usuarios
    Route::post('/user', [\App\Http\Controllers\UserController::class, 'store']); // Crear un nuevo usuario (Staff)
    Route::post('/user/logout',[\App\Http\Controllers\UserController::class, 'logout']); //Logout
    Route::post('/user/{user}', [\App\Http\Controllers\UserController::class, 'update']); // Actualizar un usuario
    Route::get('/user/{user}', [\App\Http\Controllers\UserController::class, 'show']); // Obtener un usuario específico
    Route::get('/user/email/{email}', [\App\Http\Controllers\UserController::class, 'showEmail']); // Obtener un usuario específico por Email
    Route::delete('/user/{user}', [\App\Http\Controllers\UserController::class, 'destroy']); // Eliminar un usuario

    // Rutas para el CRUD de roles y permisos
    Route::get('/roles', [\App\Http\Controllers\RoleController::class, 'index']); // Obtener todos los roles
    Route::get('/role/{id}', [\App\Http\Controllers\RoleController::class, 'show']); // Obtener un rol específico
    Route::post('/role', [\App\Http\Controllers\RoleController::class, 'store']); //Crear un rol
    Route::post('/role/{id}', [\App\Http\Controllers\RoleController::class, 'update']); //Actualizar un rol
    Route::delete('/role/{id}', [\App\Http\Controllers\RoleController::class, 'destroy']); //Eliminar un rol
    Route::get('/permissions', [\App\Http\Controllers\PermissionController::class, 'index']); //Obtener todos los permisos

    //Rutas para el CRUD de unidades
    Route::post('/unit', [\App\Http\Controllers\UnitController::class, 'store']); //Crear una unidad
    Route::post('/unit/{id}', [\App\Http\Controllers\UnitController::class, 'update']); //Actualizar una unidad
    Route::delete('/unit/{id}', [\App\Http\Controllers\UnitController::class, 'destroy']); //Eliminar una unidad

    //Rutas para el CRUD de Productos
    Route::post('/product/codes/', [\App\Http\Controllers\ProductController::class, 'showByCodeArray']); // Obtener productos por arreglo de Codigos
    Route::get('/product/check-code/{code}', [\App\Http\Controllers\ProductController::class, 'checkExists']); // Verificar si un código existe
    Route::get('/product/inventoryAdjustables/', [\App\Http\Controllers\ProductController::class, 'getAdjustableProducts']); // Obtener productos cuyo inventario se puede ajustar (materiales y muebles)
    Route::get('/products/{code}/colors', [\App\Http\Controllers\ProductController::class, 'getColors']);
    Route::get('/products/stats/counts', [\App\Http\Controllers\ProductController::class, 'getProductCounts']);

    //Rutas para el CRUD de materiales
    Route::get('/materials/noPage/all', [\App\Http\Controllers\MaterialController::class, 'listMaterialsPurchase']); //Obtener todos los materiales sin paginación (para compras)
    Route::get('/materials/export/pdf', [\App\Http\Controllers\MaterialController::class, 'exportPdf']); //Exportar materiales a PDF
    Route::post('/material', [\App\Http\Controllers\MaterialController::class, 'store']); //Crear un material
    Route::get('/material/costHistory/{cod}', [\App\Http\Controllers\MaterialController::class, 'materialCostHistory']); //Obtener historico de costos de material
    Route::post('/material/{id}', [\App\Http\Controllers\MaterialController::class, 'update']); //Actualizar un material
    Route::delete('/material/{id}', [\App\Http\Controllers\MaterialController::class, 'destroy']); //Eliminar un material

    //Rutas para el CRUD de tipos de materiales
    Route::post('/materialType', [\App\Http\Controllers\MaterialTypeController::class, 'store']); //Crear un tipo de material
    Route::post('/materialType/{id}', [\App\Http\Controllers\MaterialTypeController::class, 'update']); //Actualizar un tipo de material
    Route::delete('/materialType/{id}', [\App\Http\Controllers\MaterialTypeController::class, 'destroy']); //Eliminar un tipo de material

    //Rutas para el CRUD de muebles
    Route::get('/furnitures/noPage/all', [\App\Http\Controllers\FurnitureController::class, 'listAll']); //Obtener todos los muebles sin paginación
    Route::post('/furniture', [\App\Http\Controllers\FurnitureController::class, 'store']); //Crear un mueble
    Route::post('/furniture/manufacture/{id}', [\App\Http\Controllers\FurnitureController::class, 'manufacture']); // adicionar existencias de un mueble
    Route::post('/furniture/{id}', [\App\Http\Controllers\FurnitureController::class, 'update']); //Actualizar un mueble
    Route::delete('/furniture/{id}', [\App\Http\Controllers\FurnitureController::class, 'destroy']); //Eliminar un mueble

    //Rutas para el CRUD de tipos de muebles
    Route::post('/furnitureType', [\App\Http\Controllers\FurnitureTypeController::class, 'store']); //Crear un tipo de mueble
    Route::post('/furnitureType/{id}', [\App\Http\Controllers\FurnitureTypeController::class, 'update']); //Actualizar un tipo de mueble
    Route::delete('/furnitureType/{id}', [\App\Http\Controllers\FurnitureTypeController::class, 'destroy']); //Eliminar un tipo de mueble

    // Rutas para el CRUD de MO
    Route::get('/labors', [\App\Http\Controllers\LaborController::class, 'index']); // Obtener todas las MO
    Route::get('/labor/name/{name}', [\App\Http\Controllers\LaborController::class, 'showByName']); // Obtener MO específica por nombre
    Route::get('/labor/{id}', [\App\Http\Controllers\LaborController::class, 'show']); // Obtener MO específica
    Route::post('/labor', [\App\Http\Controllers\LaborController::class, 'store']); //Crear una MO
    Route::post('/labor/{id}', [\App\Http\Controllers\LaborController::class, 'update']); //Actualizar una MO
    Route::delete('/labor/{id}', [\App\Http\Controllers\LaborController::class, 'destroy']); //Eliminar MO

    //Rutas para el CRUD de juegos
    Route::post('/set', [\App\Http\Controllers\SetController::class, 'store']); //Crear un juego
    Route::get('/set/cod/{cod}', [\App\Http\Controllers\SetController::class, 'showCod']); // Obtener un juego específico por Cod
    Route::post('/set/{id}', [\App\Http\Controllers\SetController::class, 'update']); //Actualizar un juego
    Route::delete('/set/{id}', [\App\Http\Controllers\SetController::class, 'destroy']); //Eliminar un juego

    //Rutas para el CRUD de tipos de juegos
    Route::post('/setType', [\App\Http\Controllers\SetTypeController::class, 'store']); //Crear un tipo de juego
    Route::post('/setType/{id}', [\App\Http\Controllers\SetTypeController::class, 'update']); //Actualizar un tipo de juego
    Route::delete('/setType/{id}', [\App\Http\Controllers\SetTypeController::class, 'destroy']); //Eliminar un tipo de juego

    // Rutas para el CRUD de colores
    Route::get('/colors', [\App\Http\Controllers\ColorController::class, 'index']); // Obtener todos los colores
    Route::post('/color', [\App\Http\Controllers\ColorController::class, 'store']); //Crear un color
    Route::post('/color/{id}', [\App\Http\Controllers\ColorController::class, 'update']); //Actualizar un color
    Route::delete('/color/{id}', [\App\Http\Controllers\ColorController::class, 'delete']); //Eliminar un color

    //Rutas para el CRUD de movimientos de productos
    Route::get('/productMovements', [\App\Http\Controllers\ProductMovementController::class, 'index']); // Obtener todos los movimientos
    Route::get('/productMovements/code/{code}', [\App\Http\Controllers\ProductMovementController::class, 'getMovementsByProductCode']); // Obtener movimientos de un producto por su código con paginación
    Route::get('/productMovements/{id}', [\App\Http\Controllers\ProductMovementController::class, 'indexProduct']); // Obtener todos los movimientos de un producto
    Route::get('/productMovement/{id}', [\App\Http\Controllers\ProductMovementController::class, 'show']); // Obtener movimiento específico
    Route::post('/productMovement', [\App\Http\Controllers\ProductMovementController::class, 'store']); //Crear movimiento
    Route::post('/productMovement/{id}', [\App\Http\Controllers\ProductMovementController::class, 'update']); //Actualizar movimiento
    Route::delete('/productMovement/{id}', [\App\Http\Controllers\ProductMovementController::class, 'destroy']); //Eliminar movimiento

    // Rutas para el CRUD de stocks
    Route::get('/products/stocks', [\App\Http\Controllers\ProductController::class, 'indexStocks']); // Obtener todos los stocks

    // Rutas para el CRUD de Ordenes
    Route::get('/orders', [\App\Http\Controllers\OrderController::class, 'index']); // Obtener todas las ordenes
    Route::get('/orders/auth', [\App\Http\Controllers\OrderController::class, 'myOrders']); // Obtener todas las ordenes del usuario autenticado
    Route::get('/order/auth/paymentDetails/code/{code}', [\App\Http\Controllers\OrderController::class, 'getPaymentDetails']); // Obtener los detalles para reportar nuevos pagos en una orden del cliente
    Route::get('/order/auth/code/{code}', [\App\Http\Controllers\OrderController::class, 'showMyOrderByCode']); // Obtener una orden por codigo validando que sea del usuario autenticado
    Route::get('/order/code/{code}', [\App\Http\Controllers\OrderController::class, 'showByCode']); // Obtener una orden por codigo
    Route::post('/order/from-commission', [\App\Http\Controllers\OrderController::class, 'storeFromCommission']); // Crear orden a partir de un encargo aprobado
    Route::post('/order/cancel/{id}', [\App\Http\Controllers\OrderController::class, 'cancel']); // Cancelar orden
    Route::get('/order/{id}', [\App\Http\Controllers\OrderController::class, 'show']); // Obtener una orden específica
    Route::post('/order', [\App\Http\Controllers\OrderController::class, 'store']); // Crear orden
    Route::delete('/order/{id}', [\App\Http\Controllers\OrderController::class, 'destroy']); // Eliminar una orden

    // Rutas para el CRUD de Ajustes de Inventario
    Route::get('/inventoryAdjustments', [\App\Http\Controllers\InventoryAdjustmentController::class, 'index']); // Obtener todos los ajustes con paginacion
    Route::get('/inventoryAdjustment/{id}', [\App\Http\Controllers\InventoryAdjustmentController::class, 'show']); // Obtener un ajuste específic
    Route::post('/inventoryAdjustment', [\App\Http\Controllers\InventoryAdjustmentController::class, 'store']); // Crear un ajuste
    Route::post('/inventoryAdjustment/{id}', [\App\Http\Controllers\InventoryAdjustmentController::class, 'update']); // Actualizar un ajuste
    Route::delete('/inventoryAdjustment/{id}', [\App\Http\Controllers\InventoryAdjustmentController::class, 'destroy']); // Eliminar un ajuste
    
    // Rutas para el CRUD de Facturas
    Route::get('/invoices', [\App\Http\Controllers\InvoiceController::class, 'index']); // Obtener todos los invoices
    Route::get('/invoices/verify/{token}', [\App\Http\Controllers\InvoiceController::class, 'verifyToken']); // Verificar validez con QR
    
    // Rutas para el CRUD de compras
    Route::get('/purchases', [\App\Http\Controllers\PurchaseController::class, 'index']); // Obtener todas las compras con paginacion
    Route::get('/purchase/{id}', [\App\Http\Controllers\PurchaseController::class, 'show']); // Obtener una compra específica
    Route::post('/purchase', [\App\Http\Controllers\PurchaseController::class, 'store']); // Crear una compra
    Route::post('/purchase/{id}', [\App\Http\Controllers\PurchaseController::class, 'update']); // Actualizar una compra
    Route::delete('/purchase/{id}', [\App\Http\Controllers\PurchaseController::class, 'destroy']); // Eliminar una compra

    // Rutas para el CRUD de proveedores
    Route::get('/suppliers', [\App\Http\Controllers\SupplierController::class, 'index']); // Obtener todos los proveedores con paginacion
    Route::get('/supplier/{id}', [\App\Http\Controllers\SupplierController::class, 'show']); // Obtener un proveedor específico
    Route::post('/supplier', [\App\Http\Controllers\SupplierController::class, 'store']); // Crear un proveedor
    Route::post('/supplier/{id}', [\App\Http\Controllers\SupplierController::class, 'update']); // Actualizar un proveedor
    Route::delete('/supplier/{id}', [\App\Http\Controllers\SupplierController::class, 'destroy']); // Eliminar un proveedor

    //Ruta para solicitar receta a IA
    Route::post('/recipes/ai-suggest', [\App\Http\Controllers\RecipeAIController::class, 'suggest']);

    // Ruta para el chat interactivo
    Route::post('/chat/send', [\App\Http\Controllers\AiConsultantController::class, 'sendMessage']);

    // Rutas para el carrito de compras
    Route::post('/cart/validate-items', [\App\Http\Controllers\ProductController::class, 'validateCartItems']); // Validar items del carrito

    // Rutas para Monedas
    Route::get('/currencies', [\App\Http\Controllers\CurrencyController::class, 'index']); //Listar todas las monedas

    // Rutas para el CRUD de Metodos de pago
    Route::get('/payment-methods', [\App\Http\Controllers\PaymentMethodController::class, 'index']); // Obtener todos los metodos de pago
    Route::get('/payment-methods/paginated', [\App\Http\Controllers\PaymentMethodController::class, 'indexPaginated']); // Obtener todos los metodos de pago con paginacion
    Route::get('/payment-method/{id}', [\App\Http\Controllers\PaymentMethodController::class, 'show']); // Obtener un metodo de pago
    Route::post('/payment-method', [\App\Http\Controllers\PaymentMethodController::class, 'store']); // Crear un metodo de pago
    Route::post('/payment-method/{id}', [\App\Http\Controllers\PaymentMethodController::class, 'update']); // Actualizar un metodo de pago
    Route::delete('/payment-method/{id}', [\App\Http\Controllers\PaymentMethodController::class, 'destroy']); // Eliminar un metodo de pago

    // Rutas para pagos
    Route::get('/payments', [\App\Http\Controllers\PaymentController::class, 'index']); // Obtener todos los pagos con paginacion
    Route::post('/payments', [\App\Http\Controllers\PaymentController::class, 'storeMany']); // Crear varios pagos de una orden
    Route::post('/payment', [\App\Http\Controllers\PaymentController::class, 'store']); // Crear pago
    Route::post('/payment/{id}/verify', [\App\Http\Controllers\PaymentController::class, 'verify']); // Aprobar/Rechazar pago

    // Rutas para el CRUD de encargos
    Route::get('/commissions', [\App\Http\Controllers\CommissionController::class, 'index']); // Obtener todos los encargos con paginacion
    Route::get('/commission/{code}', [\App\Http\Controllers\CommissionController::class, 'show']); // Obtener un encargo específico
    Route::get('/commission/{code}/quotation', [\App\Http\Controllers\CommissionController::class, 'getQuotationByCommission']); // Obtener la cotización de un encargo específico
    Route::get('/my-commissions', [\App\Http\Controllers\CommissionController::class, 'myCommissions']); // Obtener todos los encargos con paginacion del usuario autenticado
    Route::get('/my-commission/{code}', [\App\Http\Controllers\CommissionController::class, 'showMyCommission']); // Obtener un encargo específico del usuario autenticado
    Route::post('/commission', [\App\Http\Controllers\CommissionController::class, 'store']); // Crear un encargo
    Route::post('/commission/check-manufacturability', [\App\Http\Controllers\FurnitureController::class, 'checkManufacturability']); // Verificar si un encargo es fabricable
    Route::post('/commission/{id}/suggestion', [\App\Http\Controllers\CommissionController::class, 'addSuggestion']); // Agregar sugerencia a un encargo
    Route::post('/commission/{id}/approve', [\App\Http\Controllers\CommissionController::class, 'approve']); // Aprobar encargo para pasar a producción/cotización
    Route::post('/commission/{id}/cancel', [\App\Http\Controllers\CommissionController::class, 'cancel']); // Cancelar encargo
    Route::post('/commission/{code}/quote', [\App\Http\Controllers\CommissionController::class, 'markAsQuoted']); // Marcar encargo como cotizado después de que se le asigna una cotización
    Route::post('/chat/generate-description', [\App\Http\Controllers\AiConsultantController::class, 'generateOrderDescription']); // Generar descripción para encargo usando IA

    // Rutas para imagenes de banners
    Route::get('/banners', [\App\Http\Controllers\BannerImageController::class, 'index']); // Obtener todos los Banners con paginación
    Route::post('/banner', [\App\Http\Controllers\BannerImageController::class, 'store']); // Crear Banner
    Route::get('/banner/{id}', [\App\Http\Controllers\BannerImageController::class, 'show']); // Obtener Banner
    Route::post('/banner/{id}', [\App\Http\Controllers\BannerImageController::class, 'update']); // Actualizar Banner
    Route::delete('/banner/{id}', [\App\Http\Controllers\BannerImageController::class, 'destroy']); // Eliminar Banner

    // Rutas para datos del dashboard
    Route::get('/dashboard/chart', [\App\Http\Controllers\DashboardController::class, 'getChartData']);

    //Rutas para las tasas de cambio
    Route::get('/currencyExchange/ves/history', [\App\Http\Controllers\CurrencyExchangeController::class, 'getHistoryTable']);
    Route::get('/currencyExchange/ves/chart', [\App\Http\Controllers\CurrencyExchangeController::class, 'getHistoryChart']);

    //Rutas de notificaciones
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead']);
    Route::post('/notification/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead']);
    Route::delete('/notification/{id}/delete', [\App\Http\Controllers\NotificationController::class, 'destroy']);
    Route::delete('/notifications/delete-all', [\App\Http\Controllers\NotificationController::class, 'destroyAll']);
});