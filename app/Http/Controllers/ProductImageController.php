<?php

namespace App\Http\Controllers;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageController extends Controller
{
    public function uploadImages(int $productId, array $files)
    {
        $uploadedImages = [];

        foreach ($files as $file) {
            // 1. Extraemos la extensión original (jpg, png, etc.)
            $extension = $file->getClientOriginalExtension();
            
            // 2. Generamos un nombre limpio (usamos Str::slug para evitar espacios o caracteres raros que rompan la URL)
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $filename = time() . '-' . Str::slug($originalName) . '.' . $extension;
            
            // 3. Guardamos el archivo directamente en el disco 'public' (storage/app/public)
            // Esto devuelve la ruta relativa, por ejemplo: "assets/productPics/168...-foto.jpg"
            $path = $file->storeAs('assets/productPics', $filename, 'public');

            // 4. Guardar en la base de datos
            $productImage = ProductImage::create([
                'url' => $path, 
                'product_id' => $productId,
            ]);

            // 5. Guardar la URL para devolverla
            $uploadedImages[] = $path; 
        }

        return $uploadedImages;
    }

    // Función para eliminar una imagen de producto
    public function deleteImages($productId)
    {
        // Obtener todas las imágenes asociadas al producto
        $images = ProductImage::where('product_id', $productId)->get();

        // Verificar si hay imágenes asociadas
        if ($images->isEmpty()) {
            return response()->json(['message' => 'No hay imágenes asociadas a este producto'], 404);
        }

        foreach ($images as $image) {
            // Eliminar el archivo de la imagen físicamente
            if (Storage::disk('public')->exists($image->url)) {
                Storage::disk('public')->delete($image->url);
            }

            // Eliminar el registro de la base de datos
            $image->delete();
        }

        return response()->json(['message' => 'Imágenes del producto eliminadas correctamente'], 200);
    }
}
