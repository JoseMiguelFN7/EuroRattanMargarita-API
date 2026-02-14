<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RecipeGeneratorService;
use Illuminate\Support\Facades\Validator;

class RecipeAIController extends Controller
{
    protected $aiService;

    // Inyección de Dependencias: Laravel instancia automáticamente el servicio
    public function __construct(RecipeGeneratorService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Endpoint para generar una sugerencia de receta.
     * POST /api/recipes/ai-suggest
     */
    public function suggest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'description'       => 'required|string|min:5',
            'furniture_type_id' => 'required|exists:furniture_types,id', // Validamos que la categoría exista
        ]);

        if($validator->fails()){
                return response()->json([
                    'errors' => $validator->errors()->messages()
                ], 422);
            }

        $aiService = app(RecipeGeneratorService::class);
        
        // Pasamos ambos datos al servicio
        $recipeData = $aiService->suggestRecipe(
            $request->description, 
            $request->furniture_type_id
        );

        return response()->json($recipeData);
    }
}
