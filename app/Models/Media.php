<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'userId',
        'name',
        'filename',
        'path',
        'url',
        'mime_type',
        'size',
        'extension',
        'description',
        'alt_text',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'size' => 'integer',
        'userId' => 'integer',
    ];

    /**
     * Owning user / company account.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /**
     * Get the full URL for the media file (following profile image pattern).
     */
    public function getFullUrlAttribute(): string
    {
        if ($this->url) {
            // For production server, ensure /public/ is in the path (same as profile images)
            $imagePath = $this->url;
            $isProduction = app()->isProduction();
            if ($isProduction && !str_contains($imagePath, '/public/')) {
                $imagePath = '/public' . $imagePath;
            }
            return rtrim(config('app.url'), '/') . '/' . ltrim($imagePath, '/');
        }

        // Return default media URL if no file
        $isProduction = app()->isProduction();
        $basePath = $isProduction ? '/public/images/' : '/images/';
        return rtrim(config('app.url'), '/') . $basePath . 'default-avatar.svg';
    }

    /**
     * Get the file size in human readable format.
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if the media file is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Get the media URL attribute (same as profile image URL pattern).
     */
    public function getMediaUrlAttribute(): string
    {
        return $this->getFullUrlAttribute();
    }

    /**
     * Delete the model and its associated file (following profile image pattern).
     */
    public function delete()
    {
        // Delete the physical file from public directory (same as profile images)
        if ($this->path && file_exists(public_path($this->path))) {
            unlink(public_path($this->path));
        }

        return parent::delete();
    }
}
