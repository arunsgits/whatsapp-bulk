<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Allow authenticated users to upload media
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'bail',
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    if (!$value instanceof \Illuminate\Http\UploadedFile) {
                        return;
                    }

                    $ext = strtolower($value->getClientOriginalExtension());
                    $mime = strtolower($value->getMimeType() ?? '');
                    $clientMime = strtolower($value->getClientMimeType() ?? '');
                    $sizeKb = $value->getSize() / 1024;

                    // WhatsApp Cloud API Template Header Supported Categories:
                    $supportedImages = ['jpeg', 'jpg', 'png'];
                    $supportedVideos = ['mp4', '3gp', '3gpp'];
                    $supportedDocs = ['pdf'];

                    // Specific error messaging for common unsupported formats:
                    if ($ext === 'webp' || str_contains($mime, 'webp') || str_contains($clientMime, 'webp')) {
                        $fail('WebP images (.webp) are not supported by WhatsApp Cloud API templates. Please upload JPG or PNG images.');
                        return;
                    }

                    if (in_array($ext, ['gif', 'svg', 'bmp', 'tiff', 'ico', 'heic', 'heif'])) {
                        $fail("The .{$ext} image format is not supported by WhatsApp templates. Please upload JPG or PNG images.");
                        return;
                    }

                    if (in_array($ext, ['avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', 'm4v'])) {
                        $fail("The .{$ext} video format is not supported by WhatsApp templates. Please upload MP4 or 3GP videos.");
                        return;
                    }

                    if (in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'])) {
                        $fail("The .{$ext} format cannot be used in WhatsApp template headers. WhatsApp templates strictly require PDF documents.");
                        return;
                    }

                    // Size limits enforced by Meta WhatsApp Cloud API:
                    if (in_array($ext, $supportedImages)) {
                        if ($sizeKb > 5120) { // 5MB limit
                            $fail('WhatsApp template images must not exceed 5MB. Your file is ' . round($sizeKb / 1024, 2) . 'MB.');
                        }
                    } elseif (in_array($ext, $supportedVideos)) {
                        if ($sizeKb > 16384) { // 16MB limit
                            $fail('WhatsApp template videos must not exceed 16MB. Your file is ' . round($sizeKb / 1024, 2) . 'MB.');
                        }
                    } elseif (in_array($ext, $supportedDocs)) {
                        if ($sizeKb > 51200) { // 50MB limit
                            $fail('WhatsApp template documents must not exceed 50MB. Your file is ' . round($sizeKb / 1024, 2) . 'MB.');
                        }
                    }
                },
                'mimes:jpeg,jpg,png,mp4,3gp,3gpp,pdf',
                'max:51200',
            ],
            'description' => 'nullable|string|max:1000',
            'alt_text' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A file is required.',
            'file.file' => 'The uploaded file must be a valid file.',
            'file.mimes' => 'This file format is not supported for WhatsApp template bulk messages. Allowed formats: Images (JPG, JPEG, PNG up to 5MB), Videos (MP4, 3GP up to 16MB), Documents (PDF up to 50MB).',
            'file.max' => 'The file size must not exceed 50MB.',
            'description.max' => 'The description must not exceed 1000 characters.',
            'alt_text.max' => 'The alt text must not exceed 255 characters.',
        ];
    }
}
