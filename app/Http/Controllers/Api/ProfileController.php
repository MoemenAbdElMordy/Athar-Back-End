<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadProfilePhotoRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\Flag;
use App\Models\HelpRequest;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    use ApiResponse;

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        return $this->successResponse(new UserResource($user));
    }

    public function uploadPhoto(UploadProfilePhotoRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->profile_photo_path) {
            $existingPath = storage_path('app/public/'.$user->profile_photo_path);

            if (is_file($existingPath)) {
                unlink($existingPath);
            }
        }

        $user->profile_photo_path = $this->storeUploadedFile($request->file('photo'), 'profile-photos');
        $user->save();

        return $this->successResponse(new UserResource($user));
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->successResponse([
            'ratings_count' => Review::query()->where('user_id', $user->id)->count(),
            'reports_count' => Flag::query()->where('flagger_id', $user->id)->count(),
            'helpful_count' => HelpRequest::query()
                ->where('volunteer_id', $user->id)
                ->where('status', 'completed')
                ->count(),
        ]);
    }

    private function storeUploadedFile(UploadedFile $file, string $directory): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $filename = (string) Str::uuid();

        if ($extension !== '') {
            $filename .= '.'.$extension;
        }

        $targetDirectory = storage_path('app/public/'.trim($directory, '/'));

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        $file->move($targetDirectory, $filename);

        return trim($directory, '/').'/'.$filename;
    }
}
