<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

// Rutas para el CRUD de usuarios
Route::post('/user/login',[App\Http\Controllers\UserController::class, 'login']); //Login
Route::post('/user/register',[App\Http\Controllers\UserController::class, 'register']); //Register

// Rutas para el CRUD de productos
Route::get('/products', [App\Http\Controllers\ProductController::class, 'index']); // Obtener todos los productos
Route::get('/product/id/{id}', [App\Http\Controllers\ProductController::class, 'show']); // Obtener un producto específico por id
Route::get('/products/{quantity}', [App\Http\Controllers\ProductController::class, 'rand']); // Obtener una cantidad de productos en orden aleatorio
Route::get('/product/cod/{cod}', [App\Http\Controllers\ProductController::class, 'showCod']); // Obtener un producto específico por Cod
Route::get('/product/search/{search}', [App\Http\Controllers\ProductController::class, 'ProductSearchByName']); // Obtener producto en base a busqueda por nombre

// Rutas para el CRUD de stocks
Route::get('/product/stock/{id}', [App\Http\Controllers\ProductController::class, 'showStock']); // Obtener un stock específico

// Rutas para el CRUD de tipos de materiales
Route::get('/materialTypes', [App\Http\Controllers\MaterialTypeController::class, 'index']); // Obtener todos los tipos de material
Route::get('/materialType/{id}', [App\Http\Controllers\MaterialTypeController::class, 'show']); // Obtener un tipo de material específico

// Rutas para el CRUD de materiales
Route::get('/materials', [App\Http\Controllers\MaterialController::class, 'index']); // Obtener todos los materiales con paginacion
Route::get('/material/cod/{cod}', [App\Http\Controllers\MaterialController::class, 'showCod']); // Obtener un material específico
Route::get('/material/{id}', [App\Http\Controllers\MaterialController::class, 'show']); // Obtener un material específico
Route::get('/materials/{quantity}', [App\Http\Controllers\MaterialController::class, 'rand']); // Obtener una cantidad de materiales en orden aleatorio
Route::get('/materialsByType/name/{name}', [App\Http\Controllers\MaterialController::class, 'indexByMaterialType']); // Obtener materiales segun tipo
Route::get('/materialsByType/{quantity}', [App\Http\Controllers\MaterialController::class, 'randByMaterialType']); // Obtener una cantidad de materiales en orden aleatorio segun tipos

// Rutas para el CRUD de unidades
Route::get('/units', [App\Http\Controllers\UnitController::class, 'index']); // Obtener todas las unidades
Route::get('/unit/{id}', [App\Http\Controllers\UnitController::class, 'show']); // Obtener una unidad específica

// Rutas para el CRUD de muebles
Route::get('/furnitures', [App\Http\Controllers\FurnitureController::class, 'index']); // Obtener todos los materiales
Route::get('/furniture/{id}', [App\Http\Controllers\FurnitureController::class, 'show']); // Obtener un material específico
Route::get('/furnitures/{quantity}', [App\Http\Controllers\FurnitureController::class, 'rand']); // Obtener una cantidad de muebles en orden aleatorio


// Rutas para el CRUD de tipos de muebles
Route::get('/furnitureTypes', [App\Http\Controllers\FurnitureTypeController::class, 'index']); // Obtener todos los tipos de material
Route::get('/furnitureType/name/{name}', [App\Http\Controllers\FurnitureTypeController::class, 'showByName']); // Obtener un tipo de mueble específico por nombre
Route::get('/furnitureType/{id}', [App\Http\Controllers\FurnitureTypeController::class, 'show']); // Obtener un tipo de mueble específico por id

// Rutas para el CRUD de juegos
Route::get('/sets', [App\Http\Controllers\SetController::class, 'index']); // Obtener todos los juegos
Route::get('/set/{id}', [App\Http\Controllers\SetController::class, 'show']); // Obtener un juego específico
Route::get('/sets/{quantity}', [App\Http\Controllers\SetController::class, 'rand']); // Obtener una cantidad de juegos en orden aleatorio

// Rutas para el CRUD de tipos de juegos
Route::get('/setTypes', [App\Http\Controllers\SetTypeController::class, 'index']); // Obtener todos los tipos de juego
Route::get('/setType/{id}', [App\Http\Controllers\SetTypeController::class, 'show']); // Obtener un tipo de juego específico

Route::middleware(['auth:sanctum'])->group(function () {
    // Rutas para el CRUD de usuarios
    Route::get('/user/auth', [App\Http\Controllers\UserController::class, 'getAuth']); //Obtener datos de usuario autenticado
    Route::post('/user/auth', [App\Http\Controllers\UserController::class, 'updateAuthUser']); //Actualizar datos de usuario autenticado
    Route::delete('/user/auth', [App\Http\Controllers\UserController::class, 'destroyAuthUser']); //Eliminar usuario autenticado
    Route::get('/users', [App\Http\Controllers\UserController::class, 'index']); // Obtener todos los usuarios
    Route::post('/user', [App\Http\Controllers\UserController::class, 'store']); // Crear un nuevo usuario (Staff)
    Route::post('/user/logout',[App\Http\Controllers\UserController::class, 'logout']); //Logout
    Route::post('/user/{user}', [App\Http\Controllers\UserController::class, 'update']); // Actualizar un usuario
    Route::get('/user/{user}', [App\Http\Controllers\UserController::class, 'show']); // Obtener un usuario específico
    Route::get('/user/email/{email}', [App\Http\Controllers\UserController::class, 'showEmail']); // Obtener un usuario específico por Email
    Route::delete('/user/{user}', [App\Http\Controllers\UserController::class, 'destroy']); // Eliminar un usuario

    // Rutas para el CRUD de roles y permisos
    Route::get('/roles', [App\Http\Controllers\RoleController::class, 'index']); // Obtener todos los roles
    Route::get('/role/{id}', [App\Http\Controllers\RoleController::class, 'show']); // Obtener un rol específico
    Route::post('/role', [App\Http\Controllers\RoleController::class, 'store']); //Crear un rol
    Route::post('/role/{id}', [App\Http\Controllers\RoleController::class, 'update']); //Actualizar un rol
    Route::delete('/role/{id}', [App\Http\Controllers\RoleController::class, 'destroy']); //Eliminar un rol
    Route::get('/permissions', [App\Http\Controllers\PermissionController::class, 'index']); //Obtener todos los permisos

    //Rutas para el CRUD de unidades
    Route::post('/unit', [App\Http\Controllers\UnitController::class, 'store']); //Crear una unidad
    Route::post('/unit/{id}', [App\Http\Controllers\UnitController::class, 'update']); //Actualizar una unidad
    Route::delete('/unit/{id}', [App\Http\Controllers\UnitController::class, 'destroy']); //Eliminar una unidad

    //Rutas para el CRUD de Productos
    Route::post('/product/codes/', [App\Http\Controllers\ProductController::class, 'showByCodeArray']); // Obtener productos por arreglo de Codigos

    //Rutas para el CRUD de materiales
    Route::post('/material', [App\Http\Controllers\MaterialController::class, 'store']); //Crear un material
    Route::post('/material/{id}', [App\Http\Controllers\MaterialController::class, 'update']); //Actualizar un material
    Route::delete('/material/{id}', [App\Http\Controllers\MaterialController::class, 'destroy']); //Eliminar un material

    //Rutas para el CRUD de tipos de materiales
    Route::post('/materialType', [App\Http\Controllers\MaterialTypeController::class, 'store']); //Crear un tipo de material
    Route::post('/materialType/{id}', [App\Http\Controllers\MaterialTypeController::class, 'update']); //Actualizar un tipo de material
    Route::delete('/materialType/{id}', [App\Http\Controllers\MaterialTypeController::class, 'destroy']); //Eliminar un tipo de material

    //Rutas para el CRUD de muebles
    Route::post('/furniture', [App\Http\Controllers\FurnitureController::class, 'store']); //Crear un mueble
    Route::post('/furniture/{id}', [App\Http\Controllers\FurnitureController::class, 'update']); //Actualizar un mueble
    Route::delete('/furniture/{id}', [App\Http\Controllers\FurnitureController::class, 'destroy']); //Eliminar un mueble

    //Rutas para el CRUD de tipos de muebles
    Route::post('/furnitureType', [App\Http\Controllers\FurnitureTypeController::class, 'store']); //Crear un tipo de mueble
    Route::post('/furnitureType/{id}', [App\Http\Controllers\FurnitureTypeController::class, 'update']); //Actualizar un tipo de mueble
    Route::delete('/furnitureType/{id}', [App\Http\Controllers\FurnitureTypeController::class, 'destroy']); //Eliminar un tipo de mueble

    // Rutas para el CRUD de MO
    Route::get('/labors', [App\Http\Controllers\LaborController::class, 'index']); // Obtener todas las MO
    Route::get('/labor/name/{name}', [App\Http\Controllers\LaborController::class, 'showByName']); // Obtener MO específica por nombre
    Route::get('/labor/{id}', [App\Http\Controllers\LaborController::class, 'show']); // Obtener MO específica
    Route::post('/labor', [App\Http\Controllers\LaborController::class, 'store']); //Crear una MO
    Route::post('/labor/{id}', [App\Http\Controllers\LaborController::class, 'update']); //Actualizar una MO
    Route::delete('/labor/{id}', [App\Http\Controllers\LaborController::class, 'destroy']); //Eliminar MO

    //Rutas para el CRUD de juegos
    Route::post('/set', [App\Http\Controllers\SetController::class, 'store']); //Crear un juego
    Route::post('/set/{id}', [App\Http\Controllers\SetController::class, 'update']); //Actualizar un juego
    Route::delete('/set/{id}', [App\Http\Controllers\SetController::class, 'destroy']); //Eliminar un juego

    //Rutas para el CRUD de tipos de juegos
    Route::post('/setType', [App\Http\Controllers\SetTypeController::class, 'store']); //Crear un tipo de juego
    Route::post('/setType/{id}', [App\Http\Controllers\SetTypeController::class, 'update']); //Actualizar un tipo de juego
    Route::delete('/setType/{id}', [App\Http\Controllers\SetTypeController::class, 'destroy']); //Eliminar un tipo de juego

    //Rutas para el CRUD de movimientos de productos
    Route::get('/productMovements', [App\Http\Controllers\ProductMovementController::class, 'index']); // Obtener todos los movimientos
    Route::get('/productMovements/{id}', [App\Http\Controllers\ProductMovementController::class, 'indexProduct']); // Obtener todos los movimientos de un producto
    Route::get('/productMovement/{id}', [App\Http\Controllers\ProductMovementController::class, 'show']); // Obtener movimiento específico
    Route::post('/productMovement', [App\Http\Controllers\ProductMovementController::class, 'store']); //Crear movimiento
    Route::post('/productMovement/{id}', [App\Http\Controllers\ProductMovementController::class, 'update']); //Actualizar movimiento
    Route::delete('/productMovement/{id}', [App\Http\Controllers\ProductMovementController::class, 'destroy']); //Eliminar movimiento

    // Rutas para el CRUD de stocks
    Route::get('/products/stocks', [App\Http\Controllers\ProductController::class, 'indexStocks']); // Obtener todos los stocks

    // Rutas para el CRUD de Facturas
    Route::get('/receipts', [App\Http\Controllers\ReceiptController::class, 'index']); // Obtener todas las facturas
    Route::get('/receipt/{id}', [App\Http\Controllers\ReceiptController::class, 'show']); // Obtener una factura específica
    Route::post('/receipt', [App\Http\Controllers\ReceiptController::class, 'store']); // Crear factura
    Route::delete('/receipt/{id}', [App\Http\Controllers\ReceiptController::class, 'destroy']); // Eliminar una factura
});