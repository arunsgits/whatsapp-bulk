<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminModuleController extends Controller
{
    /**
     * Get all modules
     * GET /api/modules
     */
    public function index()
    {
        try {
            $modules = Module::orderBy('id', 'asc')->get();
            return response()->json([
                'success' => true,
                'data' => $modules
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching modules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new module
     * POST /api/modules
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'route' => 'nullable|string|max:255',
                'icon' => 'nullable|string|max:255',
                'status' => 'in:active,inactive'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $slug = Str::slug($request->name, '_');
            
            // Check if slug exists
            if (Module::where('slug', $slug)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'A module with this name already exists.'
                ], 400);
            }

            $module = Module::create([
                'name' => $request->name,
                'slug' => $slug,
                'route' => $request->route,
                'icon' => $request->icon,
                'status' => $request->status ?? 'active',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Module created successfully',
                'data' => $module
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating module: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific module
     * GET /api/modules/{id}
     */
    public function show($id)
    {
        try {
            $module = Module::find($id);

            if (!$module) {
                return response()->json([
                    'success' => false,
                    'message' => 'Module not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $module
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching module: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a module
     * PUT/POST /api/modules/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $module = Module::find($id);

            if (!$module) {
                return response()->json([
                    'success' => false,
                    'message' => 'Module not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string|max:255',
                'route' => 'nullable|string|max:255',
                'icon' => 'nullable|string|max:255',
                'status' => 'in:active,inactive'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            if ($request->has('name')) {
                $module->name = $request->name;
                $slug = Str::slug($request->name, '_');
                // Ensure slug is unique, excluding current module
                if (Module::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A module with this name already exists.'
                    ], 400);
                }
                $module->slug = $slug;
            }

            if ($request->has('route')) $module->route = $request->route;
            if ($request->has('icon')) $module->icon = $request->icon;
            if ($request->has('status')) $module->status = $request->status;

            $module->save();

            return response()->json([
                'success' => true,
                'message' => 'Module updated successfully',
                'data' => $module
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating module: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a module
     * DELETE /api/modules/{id}
     */
    public function destroy($id)
    {
        try {
            $module = Module::find($id);

            if (!$module) {
                return response()->json([
                    'success' => false,
                    'message' => 'Module not found'
                ], 404);
            }

            $module->delete();

            return response()->json([
                'success' => true,
                'message' => 'Module deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting module: ' . $e->getMessage()
            ], 500);
        }
    }
}
