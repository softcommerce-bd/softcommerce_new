<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\UploadedFileCollection;
use App\Models\Upload;
use App\Services\Uploads\Base64ImageUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Storage;
class FileController extends Controller
{
    public function index(){
        $all_uploads = (auth()->user()->user_type == 'seller') ? Upload::where('user_id',auth()->user()->id) : Upload::query();
        

        $all_uploads = $all_uploads->paginate(20)->appends(request()->query());

       return new UploadedFileCollection($all_uploads);

    }

    // any  base 64 image through uploader
    public function imageUpload(Request $request)
    {

        $stored = app(Base64ImageUpload::class)->store($request->image, $request->filename, auth()->user()->id);
        if (is_string($stored)) {
            return response()->json([
                'result' => false,
                'message' => translate($stored),
                'path' => "",
                'upload_id' => 0
            ]);
        }

        return response()->json([
            'result' => true,
            'message' => translate("Image updated"),
            'path' => uploaded_asset($stored->id),
            'upload_id' => $stored->id
        ]);
    }
}
