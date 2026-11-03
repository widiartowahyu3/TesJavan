<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiLimitException;
use App\Models\NamePrediction;
use App\Services\NameLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class NamePredictionController extends Controller
{
    public function lookup(Request $request, NameLookupService $service): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[\pL\s\'-]+$/u'],
        ]);

        try {
            return response()->json($service->lookup(trim($data['name'])));
        } catch (ApiLimitException $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function index(): JsonResponse
    {
        return response()->json(NamePrediction::latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'string', 'max:10'],
            'gender_probability' => ['nullable', 'numeric', 'between:0,1'],
            'gender_count' => ['nullable', 'integer', 'min:0'],
            'country_id' => ['nullable', 'string', 'size:2'],
            'country_name' => ['nullable', 'string', 'max:255'],
            'country_region' => ['nullable', 'string', 'max:255'],
            'country_probability' => ['nullable', 'numeric', 'between:0,1'],
            'nationality_count' => ['nullable', 'integer', 'min:0'],
            'countries' => ['nullable', 'array'],
        ]);

        return response()->json(NamePrediction::create($data), 201);
    }

    public function destroy(NamePrediction $prediction): JsonResponse
    {
        $prediction->delete();

        return response()->json(null, 204);
    }
}
