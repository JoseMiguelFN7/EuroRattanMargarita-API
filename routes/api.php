<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

// Rutas para el CRUD de usuarios
Route::post('/user/login',[App\Http\Controllers\UserController::class, 'login']); //Login
Route::post('/user', [App\Http\Controllers\UserController::class, 'store']); // Crear un nuevo usuario

// Rutas para el CRUD de productos
Route::get('/products', [App\Http\Controllers\ProductController::class, 'index']); // Obtener todos los productos
Route::get('/product/{id}', [App\Http\Controllers\ProductController::class, 'show']); // Obtener un producto específico

// Rutas para el CRUD de tipos de materiales
Route::get('/materialTypes', [App\Http\Controllers\MaterialTypeController::class, 'index']); // Obtener todos los tipos de material
Route::get('/materialType/{id}', [App\Http\Controllers\MaterialTypeController::class, 'show']); // Obtener un tipo de material específico

// Rutas para el CRUD de materiales
Route::get('/materials', [App\Http\Controllers\MaterialController::class, 'index']); // Obtener todos los materiales
Route::get('/material/{id}', [App\Http\Controllers\MaterialController::class, 'show']); // Obtener un material específico

// Rutas para el CRUD de unidades
Route::get('/units', [App\Http\Controllers\UnitController::class, 'index']); // Obtener todas las unidades
Route::get('/unit/{id}', [App\Http\Controllers\UnitController::class, 'show']); // Obtener una unidad específica

// Rutas para el CRUD de muebles
Route::get('/furnitures', [App\Http\Controllers\FurnitureController::class, 'index']); // Obtener todos los materiales
Route::get('/furniture/{id}', [App\Http\Controllers\FurnitureController::class, 'show']); // Obtener un material específico

// Rutas para el CRUD de tipos de muebles
Route::get('/furnitureTypes', [App\Http\Controllers\FurnitureTypeController::class, 'index']); // Obtener todos los tipos de material
Route::get('/furnitureType/{id}', [App\Http\Controllers\FurnitureTypeController::class, 'show']); // Obtener un tipo de material específico

// Rutas para el CRUD de juegos
Route::get('/sets', [App\Http\Controllers\SetController::class, 'index']); // Obtener todos los juegos
Route::get('/set/{id}', [App\Http\Controllers\SetController::class, 'show']); // Obtener un juego específico

// Rutas para el CRUD de tipos de juegos
Route::get('/setTypes', [App\Http\Controllers\SetTypeController::class, 'index']); // Obtener todos los tipos de juego
Route::get('/setType/{id}', [App\Http\Controllers\SetTypeController::class, 'show']); // Obtener un tipo de juego específico

Route::middleware([EnsureFrontendRequestsAreStateful::class, 'auth:sanctum'])->group(function () {
    // Rutas para el CRUD de usuarios
    Route::get('/user/auth', [App\Http\Controllers\UserController::class, 'getAuth']); //Obtener datos de usuario autenticado
    Route::post('/user/auth', [App\Http\Controllers\UserController::class, 'updateAuthUser']); //Actualizar datos de usuario autenticado
    Route::delete('/user/auth', [App\Http\Controllers\UserController::class, 'destroyAuthUser']); //Eliminar usuario autenticado
    Route::get('/users', [App\Http\Controllers\UserController::class, 'index']); // Obtener todos los usuarios
    Route::post('/user/logout',[App\Http\Controllers\UserController::class, 'logout']); //Logout
    Route::post('/user/{id}', [App\Http\Controllers\UserController::class, 'update']); // Actualizar un usuario
    Route::get('/user/{id}', [App\Http\Controllers\UserController::class, 'show']); // Obtener un usuario específico
    Route::delete('/user/{id}', [App\Http\Controllers\UserController::class, 'destroy']); // Eliminar un usuario

    // Rutas para el CRUD de roles
    Route::get('/roles', [App\Http\Controllers\RoleController::class, 'index']); // Obtener todos los roles
    Route::get('/role/{id}', [App\Http\Controllers\RoleController::class, 'show']); // Obtener un rol específico
    Route::post('/role', [App\Http\Controllers\RoleController::class, 'store']); //Crear un rol
    Route::post('/role/{id}', [App\Http\Controllers\RoleController::class, 'update']); //Actualizar un rol
    Route::delete('/role/{id}', [App\Http\Controllers\RoleController::class, 'destroy']); //Eliminar un rol

    //Rutas para el CRUD de unidades
    Route::post('/unit', [App\Http\Controllers\UnitController::class, 'store']); //Crear una unidad
    Route::post('/unit/{id}', [App\Http\Controllers\UnitController::class, 'update']); //Actualizar una unidad
    Route::delete('/unit/{id}', [App\Http\Controllers\UnitController::class, 'destroy']); //Eliminar una unidad

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
});