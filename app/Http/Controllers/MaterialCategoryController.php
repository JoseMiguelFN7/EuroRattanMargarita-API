<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MaterialCategory;

class MaterialCategoryController extends Controller
{
    // Obtener todas las categorías con sus respectivas subcategorías
    public function index()
    {
        // Traemos todas las categorías y anidamos sus tipos
        $categories = MaterialCategory::with('materialTypes')->get();

        // Limpieza de datos para no enviar basura al frontend
        $categories->each(function ($category) {
            $category->makeHidden(['created_at', 'updated_at']);
            
            if ($category->materialTypes) {
                $category->materialTypes->each(function ($type) {
                    $type->makeHidden(['created_at', 'updated_at', 'material_category_id']);
                });
            }
        });

        return response()->json($categories);
    }

    public function getOnlyCategories()
    {
        $categories = MaterialCategory::all(['id', 'name']);
        return response()->json($categories);
    }
}
