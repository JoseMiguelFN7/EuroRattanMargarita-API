<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Auth\AuthenticationException;

class UserController extends Controller
{
    //obtener usuario logeado
    public function getAuth(){
        $user = Auth::user(); // Obtener el usuario autenticado
    
        if (!$user) {
            throw new AuthenticationException('Usuario no autenticado');
        }

        $user->load('role.permissions');

        if ($user->image) {
            $user->image = asset('storage/' . $user->image); // Generar la URL completa de la imagen
        }

        $permissions = [];
        $roleName = null;

        if ($user->role) {
            $permissions = $user->role->permissions->pluck('slug');
            $roleName = $user->role->name;

            $user->unsetRelation('role');
        }

        $user->role = $roleName; 
        $user->permissions = $permissions;

        return response()->json($user);
    }

    //Obtener todos los usuarios
    public function index()
    {
        $users = User::with('role')->get()->map(function ($user) {
            // Agregar la URL completa de la imagen al usuario
            $user->image = $user->image ? asset('storage/' . $user->image) : null;
            return $user;
        });

        return response()->json($users); // Devuelve todos los usuarios en formato JSON con la URL de la imagen
    }

    private function uploadPhoto(Request $r){
        // Obtener el archivo de la solicitud
        $file = $r->file('image');

        // Generar un nombre único para la imagen
        $filename = time() . '-' . pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.webp';

        // Crear una instancia de ImageManager
        $manager = new ImageManager(new Driver());

        // Cargar la imagen y convertirla a WebP usando Intervention Image
        $webpImage = $manager->read($file)->toWebp(80);

        // Definir la ruta para guardar la imagen convertida
        $path = storage_path('app/public/assets/profilePics/' . $filename);

        // Guardar la imagen en la ubicación deseada
        $webpImage->save($path);

        return 'assets/profilePics/' . $filename;
    }

    //Crear un nuevo usuario
    public function store(Request $request)
    {
        // Validar los datos de la solicitud
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:9|confirmed',
            'document' => 'required|string|max:15|unique:users',
            'cellphone' => 'required|string|min:12|max:12|unique:users',
            'address' => 'required|string|max:500',
            'role_id' => 'sometimes|required|integer|exists:roles,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048'
        ]);

        //enviar error si es necesario
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        //procesar la imagen
        if ($request->hasFile('image')) {
            $image = $this->uploadPhoto($request);
        } else{
            $image = null;
        }

        if($request->has('role_id')){
            $role = $request->role_id;
        } else{
            $role = 2;
        }

        // Crear el nuevo usuario
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'document' => $request->document,
            'cellphone' => $request->cellphone,
            'address' => $request->address,
            'role_id' => $role,
            'image' => $image
        ]);

        // Retornar la respuesta
        return response()->json($user, 201); // Devuelve el usuario creado con un código de estado 201
    }

    //Registrar un nuevo usuario (sin rol)
    public function register(Request $request)
    {
        // Validar los datos de la solicitud
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:9|confirmed',
            'document' => 'required|string|max:15|unique:users',
            'cellphone' => 'required|string|min:12|max:12|unique:users',
            'address' => 'required|string|max:500',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048'
        ]);

        //enviar error si es necesario
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        //procesar la imagen
        if ($request->hasFile('image')) {
            $image = $this->uploadPhoto($request);
        } else{
            $image = null;
        }

        $clientRole = Role::getClientId();

        $user = new User();

        // Crear el nuevo usuario
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'document' => $request->document,
            'cellphone' => $request->cellphone,
            'address' => $request->address,
            'role_id' => $clientRole,
            'image' => $image
        ]);

        // Retornar la respuesta
        return response()->json($user, 201); // Devuelve el usuario creado con un código de estado 201
    }

    //Obtener un usuario específico
    public function show($id)
    {
        $user = User::with('role')->find($id); //Busca el usuario por ID

        if(!$user){
            return response()->json(['message'=>'Usuario no encontrado'], 404);
        }

        if ($user->image) {
            $user->image = asset('storage/' . $user->image); // Generar la URL completa de la imagen
        }

        return response()->json($user);
    }

    //Obtener un usuario específico por correo
    public function showEmail($email)
    {
        $user = User::with('role')->where('email', $email)->first(); //Busca el usuario por email

        if(!$user){
            return response()->json(['message'=>'Usuario no encontrado'], 404);
        }

        if ($user->image) {
            $user->image = asset('storage/' . $user->image); // Generar la URL completa de la imagen
        }

        return response()->json($user);
    }

    //Actualizar un usuario
    public function update(Request $request, $id)
    {

        $user = User::find($id); // Buscar el usuario por ID
        if(!$user){
            return response()->json(['message'=>'Usuario no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'sometimes|required|string|min:9|confirmed',
            'document' => 'sometimes|required|string|max:15|unique:users,document,' . $id,
            'cellphone' => 'sometimes|required|string|min:12|max:12|unique:users,cellphone,' . $id,
            'address' => 'sometimes|required|string|max:500',
            'role_id' => 'sometimes|required|integer|exists:roles,id',
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,webp,gif|max:2048'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        if($request->has('name')){
            $user->name = $request->name;
        }

        if($request->has('email')){
            $user->email = $request->email;
        }

        if($request->has('password')){
            $user->password = Hash::make($request->password);
        }

        if($request->has('document')){
            $user->document = $request->document;
        }

        if($request->has('cellphone')){
            $user->cellphone = $request->cellphone;
        }

        if($request->has('address')){
            $user->address = $request->address;
        }

        if($request->has('role_id')){
            $user->role_id = $request->role_id;
        }

        if ($request->hasFile('image')) {
            if($user->image){
                // Eliminar la imagen anterior
                $previousImagePath = storage_path('app/public/' . $user->image);

                if (file_exists($previousImagePath)) {
                    unlink($previousImagePath); // Elimina la imagen anterior
                }
            }
            $user->image = $this->uploadPhoto($request);
        }

        $user->save(); // Guardar los cambios en la base de datos

        return response()->json($user);
    }

    //Actualizar usuario logeado
    public function updateAuthUser(Request $request)
    {
        if(!Auth::check()){
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        $user = User::find(Auth::id()); // Buscar el usuario por ID

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . Auth::id(),
            'password' => 'sometimes|required|string|min:9|confirmed',
            'document' => 'sometimes|required|string|max:15|unique:users,document,' . Auth::id(),
            'cellphone' => 'sometimes|required|string|min:12|max:12|unique:users,cellphone,' . Auth::id(),
            'address' => 'sometimes|required|string|max:500',
            'role_id' => 'sometimes|required|integer|exists:roles,id',
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,webp,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        if($request->has('name')){
            $user->name = $request->name;
        }

        if($request->has('email')){
            $user->email = $request->email;
        }

        if($request->has('password')){
            $user->password = Hash::make($request->password);
        }

        if($request->has('document')){
            $user->document = $request->document;
        }

        if($request->has('cellphone')){
            $user->cellphone = $request->cellphone;
        }

        if($request->has('address')){
            $user->address = $request->address;
        }

        if($request->has('role_id')){
            $user->role_id = $request->role_id;
        }

        if ($request->hasFile('image')) {
            if($user->image){
                // Eliminar la imagen anterior
                Storage::disk('public')->delete($user->image);
            }
            $user->image = $this->uploadPhoto($request);
        }

        $user->save(); // Guardar los cambios en la base de datos

        return response()->json($user);
    }

    //Iniciar sesion
    public function login(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:9',
        ]);
        
        if ( $validate->fails() ) {
            return response()->json([
                'title' => 'Por favor verifique los datos ingresados',
                'message' => $validate->errors(),
            ],400);
        }
        if ( Auth::attempt($request->only('email', 'password'))){
            $request->user()->tokens()->delete();
            return response()->json([
                'token' => $request->user()->createToken(Hash::make('token'), ['server:update'])->plainTextToken,
                'token_type' => 'Bearer',
                'title' => 'Inicio de Sesión Exitoso',
                'user' => [
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role->name,
                    'image' => $request->user()->image ? asset('storage/' . $request->user()->image) : null,
                    'permissions' => $request->user()->role->permissions->pluck('slug'),
                ]
            ],200);
        }

        return response()->json([
            'title' => 'Correo o contraseña incorrectos'
        ], 404);
    }

    //Cerrar sesion
    public function logout(Request $request)
    {
        // Verificar si el usuario está autenticado
        if (Auth::check()) {
            $request->user()->tokens->each(function ($token) { //eliminar tokens de usuario
                $token->delete();
            });
            return response()->json([
                'message' => 'Sesión cerrada con éxito'
            ], 200);
        } else {
            return response()->json([
                'message' => 'No se ha encontrado un usuario autenticado'
            ], 401); // Error 401 si el usuario no está autenticado
        }
    }

    //Eliminar usuario
    public function destroy($id)
    {
        $user = User::find($id);

        if(!$user){
            return response()->json(['message'=>'Usuario no encontrado'], 404);
        }

        if($user->image){
            // Eliminar la imagen
            Storage::disk('public')->delete($user->image);
        }

        $user->delete();
        return response()->json(['message' => 'Usuario eliminado con éxito']); // Retornar mensaje de éxito
    }

    //Eliminar usuario logeado
    public function destroyAuthUser()
    {
        if(Auth::check()){
            $user = User::find(Auth::id());
            if($user->image){
                // Eliminar la imagen
                Storage::disk('public')->delete($user->image);
            }
            $user->tokens->first()->delete();
            $user->delete();
            return response()->json(['message' => 'Usuario eliminado exitosamente'], 200);
        }
        return response()->json(['message' => 'Usuario no autenticado'], 401);
    }
}