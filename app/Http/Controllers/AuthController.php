<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoginUserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class AuthController extends Controller
{
    /**
     * POST /api/auth/register
     */
    public function register(Request $request)
    {

        try {
            $data = $request->validate([
                'name'     => ['required', 'string', 'max:120'],
                'email'    => ['required', 'email', 'max:191', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
                'phone'    => ['nullable', 'string', 'max:20'],
                'address'  => ['nullable', 'string', 'max:255'],
                'role'   => ['nullable', 'string|in:Customer,Admin', 'max:255'],
            ]);

            $user = User::create([
                'name'           => $data['name'],
                'email'          => $data['email'],
                'password'       => Hash::make($data['password']),
                'role'           => $data['role'] ?? 'Customer',
                'phone'          => $data['phone'] ?? null,
                'address'        => $data['address'] ?? null,
                'remember_token' => \Str::random(10),
            ]);

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'success' => true,
                'status'  => 201,
                'message' => 'User registered successfully',
                'data'    => [
                    'token' => $token,
                    'user'  => LoginUserResource::make($user),
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'status'  => 422,
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Database error',
                'error'   => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Token creation failed',
                'error'   => $e->getMessage(),
            ], 500);
        } catch (\Throwable $e) {
            \Log::error('Register failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Unexpected server error',
            ], 500);
        }
    }

    /**
     * POST /api/auth/login
     */
    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'email'    => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            if (!$token = auth('api')->attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'status'  => 401,
                    'message' => 'Invalid credentials',
                ], 401);
            }

            $user = auth('api')->user();

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Login successful',
                'data'    => [
                    'token' => $token,
                    'user'  => LoginUserResource::make($user),
                ],
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Login failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Something went wrong during login',
            ], 500);
        }
    }

    /**
     * GET /api/auth/me
     */
    public function me()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

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
                'message' => 'User fetched successfully',
                'data'    => $user,
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'status'  => 401,
                'message' => 'Unauthorized or token expired',
            ], 401);
        } catch (\Throwable $e) {
            \Log::error('ME failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Server error',
            ], 500);
        }
    }

    /**
     * POST /api/auth/refresh
     */
    public function refresh()
    {
        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Token refreshed',
            'data'    => [
                'token' => auth('api')->refresh(),
            ],
        ], 200);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout()
    {
        auth('api')->logout();

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Logged out successfully',
            'data'    => null,
        ], 200);
    }
}
