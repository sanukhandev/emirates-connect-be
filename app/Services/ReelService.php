<?php

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Enums\ReelStatus;
use App\Models\Business;
use App\Models\Reel;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ReelService
{
    public function create(User $actor, array $data): Reel
    {
        $author = $this->resolveAuthor($actor, $data);
        $reel = new Reel([
            'caption' => $data['caption'] ?? null,
            'status' => ReelStatus::UPLOADING,
            'created_by_user_id' => $actor->id,
        ]);
        $reel->author()->associate($author);
        $reel->save();

        return $this->load($reel);
    }

    public function upload(User $actor, Reel $reel, UploadedFile $video, ReelProcessingService $processor): Reel
    {
        Gate::forUser($actor)->authorize('update', $reel);
        if ($reel->status === ReelStatus::PUBLISHED) {
            throw new RuntimeException('A published reel cannot be uploaded again.');
        }

        $path = $video->store(config('reels.source_directory').'/'.$reel->id, config('reels.source_disk'));
        if ($path === false) {
            throw new RuntimeException('The reel video could not be stored.');
        }

        try {
            $reel->update([
                'source_disk' => config('reels.source_disk'),
                'source_path' => $path,
                'status' => ReelStatus::PROCESSING,
                'processing_error' => null,
            ]);

            return $this->load($processor->process($reel));
        } catch (Throwable $exception) {
            Storage::disk(config('reels.source_disk'))->delete($path);
            throw $exception;
        }
    }

    public function update(Reel $reel, array $data): Reel
    {
        $reel->update(['caption' => $data['caption'] ?? $reel->caption]);

        return $this->load($reel->refresh());
    }

    public function delete(Reel $reel): void
    {
        foreach ([['source_disk', 'source_path'], ['playback_disk', 'playback_path'], ['thumbnail_disk', 'thumbnail_path']] as [$disk, $path]) {
            if ($reel->{$disk} && $reel->{$path}) {
                Storage::disk($reel->{$disk})->delete($reel->{$path});
            }
        }
        $reel->delete();
    }

    private function resolveAuthor(User $actor, array $data): User|Business
    {
        if ($data['author_type'] === 'user') {
            return $actor;
        }

        $business = Business::findOrFail((int) $data['business_id']);
        abort_unless($business->status === BusinessStatus::ACTIVE, 403);
        Gate::forUser($actor)->authorize('publish', $business);

        return $business;
    }

    private function load(Reel $reel): Reel
    {
        return $reel->loadMorph('author', [User::class => ['profile'], Business::class => []]);
    }
}
