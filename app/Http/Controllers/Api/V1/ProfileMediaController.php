<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UploadAvatarRequest;
use App\Http\Requests\Profile\UploadCoverImageRequest;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ProfileMediaController extends Controller
{
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        return $this->replace($request, 'avatar', $request->file('avatar'));
    }

    public function deleteAvatar(Request $request): Response
    {
        return $this->remove($request, 'avatar_path', 'avatar');
    }

    public function uploadCover(UploadCoverImageRequest $request): JsonResponse
    {
        return $this->replace($request, 'cover_image', $request->file('cover_image'));
    }

    public function deleteCover(Request $request): Response
    {
        return $this->remove($request, 'cover_image_path', 'cover');
    }

    private function replace(Request $request, string $field, $file): JsonResponse
    {
        $profile = $this->profile($request);
        $disk = config('filesystems.default');
        $kind = $field === 'avatar' ? 'avatar' : 'cover';
        $oldPath = $profile->{$kind === 'avatar' ? 'avatar_path' : 'cover_image_path'};
        $path = $file->store("profiles/{$profile->user_id}/{$kind}", ['disk' => $disk]);
        $column = $kind === 'avatar' ? 'avatar_path' : 'cover_image_path';
        $profile->update([$column => $path]);
        $this->deleteManagedPath($oldPath, $profile->user_id, $kind, $disk);

        return ProfileResource::make($profile->refresh())->response()->setStatusCode(200);
    }

    private function remove(Request $request, string $column, string $kind): Response
    {
        $profile = $this->profile($request);
        $oldPath = $profile->{$column};
        $profile->update([$column => null]);
        $this->deleteManagedPath($oldPath, $profile->user_id, $kind, config('filesystems.default'));

        return response()->noContent();
    }

    private function profile(Request $request): Profile
    {
        $user = $request->user();

        return $user->profile()->firstOrCreate(['user_id' => $user->id], ['display_name' => $user->name]);
    }

    private function deleteManagedPath(?string $path, int $userId, string $kind, string $disk): void
    {
        $prefix = "profiles/{$userId}/{$kind}/";
        $storage = Storage::disk($disk);

        if ($path !== null && str_starts_with($path, $prefix) && $storage->exists($path)) {
            $storage->delete($path);
        }
    }
}
