<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecretKey;
use App\Services\SecretManager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SecretKeyController extends Controller
{
    public function index(): JsonResponse
    {
        $secrets = SecretKey::active()->select('id', 'name', 'value', 'environment', 'description', 'is_active', 'updated_at')
            ->get();

        return response()->json([
            'data' => $secrets
        ]);
    }

    public function show(SecretKey $secret): JsonResponse
    {

        return response()->json([
            'data' => [
                'id'           => $secret->id,
                'name'         => $secret->name,
                'value'        => $secret->value,
                'environment'  => $secret->environment,
                'description'  => $secret->description,
                'is_active'    => $secret->is_active,
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|unique:secret_keys,name',
            'value'       => 'required|string',
            'environment' => 'sometimes|string|in:production,staging,testing',
            'description' => 'nullable|string',
        ]);

        $secret = SecretKey::create($validated);

        return response()->json([
            'message' => 'Secret key created successfully',
            'data'    => $secret->only(['id', 'name', 'environment'])
        ], 201);
    }

    public function update(Request $request, SecretKey $secret): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|unique:secret_keys,name,' . $secret->id,
            'value'       => 'sometimes|required|string',
            'description' => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);

        $secret->update($validated);

        return response()->json([
            'message' => 'Secret key updated successfully',
            'data'    => $secret->only(['id', 'name', 'environment', 'is_active'])
        ]);
    }

    public function destroy(SecretKey $secret): JsonResponse
    {
        $secret->update(['is_active' => false]);

        return response()->json(['message' => 'Secret key deactivated']);
    }

    public function restore(SecretKey $secret): JsonResponse
    {
        $secret->update(['is_active' => true]);

        return response()->json(['message' => 'Secret key restored']);
    }

    public function getByName($name): JsonResponse
    {
        $value = SecretManager::get($name);

        if (!$value) {
            return response()->json(['error' => 'Secret not found'], 404);
        }

        return response()->json(['name' => $name, 'value' => $value]);
    }
}