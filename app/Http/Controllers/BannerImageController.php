<?php

namespace App\Http\Controllers;

use App\Models\BannerImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BannerImageController extends Controller
{
    public function index(Request $request) 
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search'); // Para buscar por título

        // Iniciamos el Query Builder
        $banners = BannerImage::query()
            ->when($search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            // Verificamos si enviaron 'is_active' y que no sea un string vacío
            ->when($request->has('is_active') && $request->input('is_active') !== '', function ($query) use ($request) {
                $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
                $query->where('is_active', $isActive);
            })
            ->orderBy('order', 'asc')
            ->paginate($perPage);
        
        // Transformación de la ruta de imagen
        $banners->through(function ($banner) {
            $banner->image_path = asset('storage/' . $banner->image_path);
            return $banner;
        });

        return response()->json($banners);
    }

    public function active()
    {
        $banners = BannerImage::where('is_active', true)
                         ->orderBy('order', 'asc')
                         ->get();
                         
        $banners->transform(function ($banner) {
            $banner->image_path = asset('storage/' . $banner->image_path);
            return $banner;
        });

        return response()->json($banners);
    }

    public function show(string $id)
    {
        $banner = BannerImage::find($id);

        if (!$banner) {
            return response()->json(['message' => 'Banner no encontrado'], 404);
        }

        $banner->image_path = asset('storage/' . $banner->image_path);

        return response()->json($banner);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'     => 'nullable|string|max:255',
            'image'     => 'required|image|mimes:jpeg,png,jpg,webp,gif|max:5120',
            'url'       => 'nullable|url|max:255',
            'order'     => 'integer',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // LÓGICA DE ORDEN: Verificamos si el orden está repetido
            $order = $request->input('order', 0);
            if (BannerImage::where('order', $order)->exists()) {
                // Si existe, lo mandamos al final (el máximo actual + 1)
                $order = BannerImage::max('order') + 1;
            }

            $path = $request->file('image')->store('banners', 'public');

            $banner = BannerImage::create([
                'title'      => $request->input('title'),
                'image_path' => $path,
                'url'        => $request->input('url'),
                'order'      => $order, // Usamos la variable calculada
                'is_active'  => $request->input('is_active', true),
            ]);

            $banner->image_path = asset('storage/' . $banner->image_path);

            return response()->json([
                'message' => 'Banner creado correctamente',
                'data'    => $banner
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al subir la imagen', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $banner = BannerImage::find($id);

        if (!$banner) return response()->json(['message' => 'Banner no encontrado'], 404);

        $validator = Validator::make($request->all(), [
            'title'     => 'nullable|string|max:255',
            'image'     => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:5120',
            'url'       => 'nullable|url|max:255',
            'order'     => 'integer',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        try {
            $dataToUpdate = $request->only(['title', 'url', 'is_active']);

            // LÓGICA DE ORDEN: Solo revisamos si enviaron un nuevo orden
            if ($request->has('order')) {
                $order = $request->input('order');
                
                // Verificamos si ese orden lo tiene OTRO banner diferente al actual
                if (BannerImage::where('order', $order)->where('id', '!=', $banner->id)->exists()) {
                    $order = BannerImage::max('order') + 1;
                }
                
                $dataToUpdate['order'] = $order;
            }

            if ($request->hasFile('image')) {
                if (Storage::disk('public')->exists($banner->image_path)) {
                    Storage::disk('public')->delete($banner->image_path);
                }
                $dataToUpdate['image_path'] = $request->file('image')->store('banners', 'public');
            }

            $banner->update($dataToUpdate);
            
            $banner->image_path = asset('storage/' . $banner->image_path);

            return response()->json([
                'message' => 'Banner actualizado correctamente',
                'data'    => $banner
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar', 'error' => $e->getMessage()], 500);
        }
    }

    public function reorder(Request $request)
    {
        // 1. Validamos que nos envíen un arreglo con la estructura correcta
        $validator = Validator::make($request->all(), [
            'banners'           => 'required|array',
            'banners.*.id'      => 'required|integer|exists:banner_images,id',
            'banners.*.order'   => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()->messages()], 422);
        }

        DB::beginTransaction();

        try {
            // 2. Recorremos el arreglo y actualizamos solo la columna 'order'
            foreach ($request->banners as $bannerData) {
                BannerImage::where('id', $bannerData['id'])
                           ->update(['order' => $bannerData['order']]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Banners reordenados correctamente.'
            ], 200);

        } catch (Throwable $e) {
            DB::rollBack();
            
            Log::error('Error al reordenar banners: ' . $e->getMessage());

            return response()->json([
                'message' => 'Ocurrió un error al intentar reordenar los banners.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        $banner = BannerImage::find($id);

        if (!$banner) return response()->json(['message' => 'Banner no encontrado'], 404);

        try {
            if (Storage::disk('public')->exists($banner->image_path)) {
                Storage::disk('public')->delete($banner->image_path);
            }
            
            $banner->delete();

            return response()->json(['message' => 'Banner eliminado correctamente']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar', 'error' => $e->getMessage()], 500);
        }
    }
}