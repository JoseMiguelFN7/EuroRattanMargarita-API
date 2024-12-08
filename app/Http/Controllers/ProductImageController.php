<?php

namespace App\Http\Controllers;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function uploadImages(int $productId, array $files)
    {
        $uploadedImages = [];

        foreach ($files as $file) {
            // Generar un nombre único para la imagen
            $filename = time() . '-' . $file->getClientOriginalName();

            // Subir la imagen al directorio 'productPics' dentro de 'storage/app/public/assets'
            $url = $file->storeAs('assets/productPics', $filename, 'public');

            // Guardar en la base de datos
            $productImage = ProductImage::create([
                'url' => $url,
                'product_id' => $productId,
            ]);

            $uploadedImages[] = $url; // Guardar la URL para devolverla
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
