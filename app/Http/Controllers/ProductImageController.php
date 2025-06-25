<?php

namespace App\Http\Controllers;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ProductImageController extends Controller
{
    public function uploadImages(int $productId, array $files)
    {
        $uploadedImages = [];

        foreach ($files as $file) {
            // Generar un nombre único para la imagen
            $filename = time() . '-' . pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.webp';
            
            // Crear una instancia de ImageManager
            $manager = new ImageManager(new Driver());

            // Cargar la imagen y convertirla a WebP usando Intervention Image
            $webpImage = $manager->read($file)->toWebp(80);

            // Definir la ruta para guardar la imagen convertida
            $path = storage_path('app/public/assets/productPics/' . $filename);

            // Guardar la imagen en la ubicación deseada
            $webpImage->save($path);

            // Guardar en la base de datos
            $productImage = ProductImage::create([
                'url' => 'assets/productPics/' . $filename,
                'product_id' => $productId,
            ]);

            $uploadedImages[] = 'assets/productPics/' . $filename; // Guardar la URL para devolverla
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
