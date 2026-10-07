<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\UploadBusinessCoverRequest;
use App\Http\Requests\Business\UploadBusinessLogoRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class BusinessMediaController extends Controller
{
    public function uploadLogo(UploadBusinessLogoRequest $request, Business $business): JsonResponse
    {
        $this->authorize('uploadMedia', $business);

        return $this->replace($request, $business, 'logo', 'logo', 'logo');
    }

    public function deleteLogo(Request $request, Business $business): Response
    {
        return $this->remove($request, $business, 'logo_path', 'logo');
    }

    public function uploadCover(UploadBusinessCoverRequest $request, Business $business): JsonResponse
    {
        $this->authorize('uploadMedia', $business);

        return $this->replace($request, $business, 'cover_image', 'cover_image', 'cover');
    }

    public function deleteCover(Request $request, Business $business): Response
    {
        return $this->remove($request, $business, 'cover_image_path', 'cover');
    }

    private function replace(Request $request, Business $business, string $field, string $column, string $kind): JsonResponse
    {
        $disk = 'public';
        $oldPath = $business->{$column.'_path'};
        $path = $request->file($field)->store("businesses/{$business->id}/{$kind}", ['disk' => $disk]);
        $business->update([$column.'_path' => $path]);
        $this->deleteManagedPath($oldPath, $business->id, $kind, $disk);

        return BusinessResource::make($business->refresh())->response()->setStatusCode(200);
    }

    private function remove(Request $request, Business $business, string $column, string $kind): Response
    {
        $this->authorize('uploadMedia', $business);
        $oldPath = $business->{$column};
        $business->update([$column => null]);
        $this->deleteManagedPath($oldPath, $business->id, $kind, 'public');

        return response()->noContent();
    }

    private function deleteManagedPath(?string $path, int $businessId, string $kind, string $disk): void
    {
        $prefix = "businesses/{$businessId}/{$kind}/";
        $storage = Storage::disk($disk);
        if ($path !== null && str_starts_with($path, $prefix) && $storage->exists($path)) {
            $storage->delete($path);
        }
    }
}
