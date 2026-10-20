<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMediaRequest;
use App\Http\Requests\UpdateMediaRequest;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaController extends Controller
{
    /**
     * Resolve the company/owner id for the authenticated user (staff → parent).
     */
    protected function resolveOwnerId(Request $request): int
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        // Admin may inspect a specific user's library via ?userId= or ?user_id=
        if (strtolower((string) ($user->role ?? '')) === 'admin' && ($request->filled('userId') || $request->filled('user_id'))) {
            return (int) $request->input('userId', $request->input('user_id'));
        }

        return (int) $user->getCompanyId();
    }

    /**
     * Base query scoped to the current user's media library.
     * Non-admins always see only their own (company) media — never other users'.
     */
    protected function scopedQuery(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        $query = Media::query();
        $isAdmin = strtolower((string) ($user->role ?? '')) === 'admin';

        // Admin + ?all=1 → every user's media (admin panel only)
        if ($isAdmin && $request->boolean('all')) {
            return $query;
        }

        return $query->where('userId', $this->resolveOwnerId($request));
    }

    /**
     * Find media that belongs to the current scope, or 404.
     */
    protected function findOwnedMedia(Request $request, string $id): Media
    {
        return $this->scopedQuery($request)->findOrFail($id);
    }

    /**
     * Display a listing of the resource (user-wise).
     */
    public function index(Request $request)
    {
        try {
            $query = $this->scopedQuery($request);

            // Filter by type (image, video, document, etc.)
            if ($request->filled('type')) {
                if ($request->type === 'image') {
                    $query->where('mime_type', 'like', 'image/%');
                } elseif ($request->type === 'video') {
                    $query->where('mime_type', 'like', 'video/%');
                } elseif ($request->type === 'document') {
                    $query->where('mime_type', 'not like', 'image/%')
                          ->where('mime_type', 'not like', 'video/%');
                }
            }

            // Search by name or description
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $media = $query->orderBy('created_at', 'desc')->get();

            $media->transform(function ($item) {
                $item->full_url = $item->getFullUrlAttribute();

                return $item;
            });

            return response()->json([
                'success' => true,
                'data' => $media,
                'meta' => [
                    'userId' => $this->resolveOwnerId($request),
                    'count' => $media->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMediaRequest $request)
    {
        try {
            $ownerId = $this->resolveOwnerId($request);

            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $mimeType = $file->getMimeType();
            $size = $file->getSize();

            // Generate unique filename
            $filename = time() . '_' . Str::uuid() . '.' . $extension;

            // Create directory if it doesn't exist (following profile image pattern)
            $mediaPath = public_path('uploads/media');
            if (! file_exists($mediaPath)) {
                mkdir($mediaPath, 0755, true);
            }

            // Move file to storage (following profile image pattern)
            $file->move($mediaPath, $filename);

            $isProduction = app()->isProduction();
            $storedPath = $isProduction
                ? '/public/uploads/media/' . $filename
                : '/uploads/media/' . $filename;

            // Create media record — always tied to company/user
            $media = Media::create([
                'userId' => $ownerId,
                'name' => $originalName,
                'filename' => $filename,
                'path' => $storedPath,
                'url' => $storedPath,
                'mime_type' => $mimeType,
                'size' => $size,
                'extension' => $extension,
                'description' => $request->description,
                'alt_text' => $request->alt_text,
            ]);

            $media->full_url = $media->getFullUrlAttribute();

            return response()->json([
                'success' => true,
                'message' => 'Media file uploaded successfully',
                'data' => $media,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload media file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        try {
            $media = $this->findOwnedMedia($request, $id);
            $media->full_url = $media->getFullUrlAttribute();

            return response()->json([
                'success' => true,
                'data' => $media,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Media file not found',
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMediaRequest $request, string $id)
    {
        try {
            $media = $this->findOwnedMedia($request, $id);

            $media->update([
                'description' => $request->description,
                'alt_text' => $request->alt_text,
            ]);

            $media->full_url = $media->getFullUrlAttribute();

            return response()->json([
                'success' => true,
                'message' => 'Media file updated successfully',
                'data' => $media,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update media file: ' . $e->getMessage(),
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        try {
            $media = $this->findOwnedMedia($request, $id);
            $media->delete();

            return response()->json([
                'success' => true,
                'message' => 'Media file deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete media file: ' . $e->getMessage(),
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Get media statistics (user-wise).
     */
    public function stats(Request $request)
    {
        try {
            $query = $this->scopedQuery($request);

            $totalMedia = (clone $query)->count();
            $totalSize = (clone $query)->sum('size');
            $imageCount = (clone $query)->where('mime_type', 'like', 'image/%')->count();
            $videoCount = (clone $query)->where('mime_type', 'like', 'video/%')->count();
            $documentCount = (clone $query)->where('mime_type', 'not like', 'image/%')
                                 ->where('mime_type', 'not like', 'video/%')
                                 ->count();

            $stats = [
                'total_media' => $totalMedia,
                'total_size' => $totalSize,
                'human_size' => $this->formatBytes((int) $totalSize),
                'image_count' => $imageCount,
                'video_count' => $videoCount,
                'document_count' => $documentCount,
                'userId' => $this->resolveOwnerId($request),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve media statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
