<?php

namespace App\Services\Funnel;

use App\Models\Upload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

/**
 * Images for funnel pages, stored in the same media library (`uploads`
 * table, uploads/all folder, optional cloud disk) as every other admin
 * upload, so they appear in "Uploaded Files" and nowhere else.
 *
 * Stricter than the general uploader on purpose: raster images only (no SVG,
 * which can carry script), content checked by its bytes rather than its name.
 */
class FunnelMediaService
{
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function search(?string $term, int $perPage = 30)
    {
        return Upload::query()
            ->where('type', 'image')
            ->whereIn('extension', self::EXTENSIONS)
            ->when(trim((string) $term) !== '', function ($q) use ($term) {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term)) . '%';
                $q->where('file_original_name', 'like', $like);
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @throws FunnelException when the file is not an acceptable image
     */
    public function store(UploadedFile $file, User $user): Upload
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::EXTENSIONS, true)) {
            throw new FunnelException('Upload a JPG, PNG, WebP or GIF image.', 'invalid_image');
        }

        $maxBytes = (int) config('funnel.media.max_kb') * 1024;
        if ($file->getSize() > $maxBytes) {
            throw new FunnelException('The image is larger than ' . config('funnel.media.max_kb') . ' KB.', 'image_too_large');
        }

        // The name says nothing about the bytes; check what the file really is.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $size = @getimagesize($file->getRealPath());
        if (!in_array($mime, self::MIME_TYPES, true) || $size === false) {
            throw new FunnelException('This file is not a valid image.', 'invalid_image');
        }

        $path = $file->store('uploads/all', 'local');
        $absolute = Storage::disk('local')->path($path);
        $bytes = $file->getSize();

        // Same downscaling as the admin uploader, so large photos from a phone
        // do not slow down the funnel page.
        if (get_setting('disable_image_optimization') != 1 && $extension !== 'gif') {
            try {
                $image = Image::make($absolute);
                if ($image->width() > 1500 || $image->height() > 1500) {
                    $image->resize(1500, 1500, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                    $image->save($absolute);
                    clearstatcache();
                    $bytes = filesize($absolute);
                }
            } catch (\Exception $e) {
                // Keep the original file; optimisation is best effort.
            }
        }

        $disk = config('filesystems.default');
        if ($disk !== 'local') {
            Storage::disk($disk)->put($path, file_get_contents($absolute), [
                'visibility' => 'public',
                'ContentType' => $mime,
            ]);
            @unlink($absolute);
        }

        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $upload = new Upload();
        $upload->file_original_name = mb_substr($name, 0, 255);
        $upload->file_name = $path;
        $upload->user_id = $user->id;
        $upload->extension = $extension;
        $upload->type = 'image';
        $upload->file_size = $bytes;
        $upload->save();

        return $upload;
    }

    public function present(Upload $upload): array
    {
        return [
            'id' => $upload->id,
            'url' => $upload->external_link ?: my_asset($upload->file_name),
            'name' => $upload->file_original_name,
            'extension' => $upload->extension,
            'size' => (int) $upload->file_size,
            'created_at' => optional($upload->created_at)->toIso8601String(),
        ];
    }
}
