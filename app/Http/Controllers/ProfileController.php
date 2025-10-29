<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function profile($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Profile fetched successfully',
            'data'    => ['user' => $user]
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'User not found',
            ], 404);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'name'     => 'sometimes|string|max:255',
            'email'    => 'sometimes|email|unique:users,email,' . $user->id,
            'phone'    => 'sometimes|string|max:20',
            'address'  => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status'  => 422,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // শুধুমাত্র allowed fields update হবে
        $allowedFields = $request->only(['name', 'email', 'phone', 'address']);
        $user->update($allowedFields);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Profile updated successfully',
            'data'    => ['user' => $user]
        ], 200);
    }
}
