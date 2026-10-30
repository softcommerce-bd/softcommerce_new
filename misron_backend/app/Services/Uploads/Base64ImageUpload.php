<?php

namespace App\Services\Uploads;

use App\Models\Upload;
use Illuminate\Support\Facades\Storage;

/**
 * Stores an image sent by the apps and the storefront as base64 text
 * (profile picture and the general image uploader of the v2 API).
 *
 * The file name sent with the image is used for the display name only, never
 * as a path: the stored name is generated and the format is read from the
 * image itself. Before, the given name was written to disk as it came, so a
 * name like "../../.env" overwrote (and then deleted) files outside the
 * uploads folder, and any content was accepted under an image extension.
 * SVG is not accepted here because it can carry scripts.
 */
class Base64ImageUpload
{
    private const EXTENSIONS = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];

    private const MAX_BYTES = 10 * 1024 * 1024;

    /**
     * @param mixed $base64 the image as base64 text
     * @param mixed $filename the name the client gave the file (display only)
     * @return Upload|string the saved upload, or the reason it was refused
     */
    public function store($base64, $filename, int $userId)
    {
        $bytes = is_string($base64) ? base64_decode($base64, true) : false;
        if ($bytes === false || $bytes === '') {
            return 'Only image can be uploaded';
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            return 'The image is too large';
        }

        $info = @getimagesizefromstring($bytes);
        $extension = $info ? (self::EXTENSIONS[$info[2]] ?? null) : null;
        if ($extension === null) {
            return 'Only image can be uploaded';
        }

        $path = 'uploads/all/' . bin2hex(random_bytes(8)) . date('YmdHis') . '.' . $extension;
        if (file_put_contents(public_path($path), $bytes) === false) {
            return 'Uploading error';
        }

        if (env('FILESYSTEM_DRIVER') == 's3') {
            Storage::disk('s3')->put($path, $bytes, ['visibility' => 'public']);
            unlink(public_path($path));
        }

        $name = pathinfo(basename(str_replace('\\', '/', (string) $filename)), PATHINFO_FILENAME);

        $upload = new Upload;
        $upload->file_original_name = $name !== '' ? mb_substr($name, 0, 190) : null;
        $upload->extension = $extension;
        $upload->file_name = $path;
        $upload->user_id = $userId;
        $upload->type = 'image';
        $upload->file_size = strlen($bytes);
        $upload->save();

        return $upload;
    }
}
