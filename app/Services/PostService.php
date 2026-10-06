<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Business;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PostService
{
    /** @param array<int, UploadedFile> $files */
    public function create(User $actor, array $data, array $files = []): Post
    {
        $author = $this->resolveAuthor($actor, $data);
        $stored = [];

        try {
            $post = DB::transaction(function () use ($actor, $author, $data, $files, &$stored): Post {
                $status = PostStatus::from($data['status'] ?? PostStatus::PUBLISHED->value);
                $post = new Post([
                    'body' => $data['body'] ?? null,
                    'status' => $status,
                    'published_at' => $status === PostStatus::PUBLISHED ? now() : null,
                    'created_by' => $actor->id,
                ]);
                $post->author()->associate($author);
                $post->save();
                $this->storeMedia($post, $files, $stored);

                return $post;
            });
        } catch (Throwable $exception) {
            $this->deleteStored($stored);
            throw $exception;
        }

        return $this->loadPost($post);
    }

    public function update(Post $post, array $data): Post
    {
        $body = array_key_exists('body', $data) ? $data['body'] : $post->body;
        if (blank($body) && ! $post->media()->exists()) {
            throw ValidationException::withMessages(['body' => 'A post needs text or at least one image.']);
        }

        $attributes = [];
        if (array_key_exists('body', $data)) {
            $attributes['body'] = $body;
        }
        if (array_key_exists('status', $data)) {
            $status = PostStatus::from($data['status']);
            $attributes['status'] = $status;
            $attributes['published_at'] = $status === PostStatus::PUBLISHED
                ? ($post->published_at ?? now())
                : null;
        }
        $post->update($attributes);

        return $this->loadPost($post->refresh());
    }

    public function addMedia(Post $post, UploadedFile $file): Post
    {
        if ($post->media()->count() >= (int) config('posts.max_media')) {
            throw ValidationException::withMessages(['media' => 'A post may contain no more than four images.']);
        }

        $stored = [];
        try {
            DB::transaction(function () use ($post, $file, &$stored): void {
                $this->storeMedia($post, [$file], $stored);
            });
        } catch (Throwable $exception) {
            $this->deleteStored($stored);
            throw $exception;
        }

        return $this->loadPost($post->refresh());
    }

    public function deleteMedia(Post $post, int $mediaId): Post
    {
        $media = $post->media()->findOrFail($mediaId);
        $path = $media->path;
        $media->delete();
        Storage::disk(config('posts.media_disk'))->delete($path);

        return $this->loadPost($post->refresh());
    }

    private function resolveAuthor(User $actor, array $data): User|Business
    {
        if (($data['author_type'] ?? null) === 'user') {
            return $actor;
        }

        $business = Business::findOrFail((int) $data['business_id']);
        Gate::forUser($actor)->authorize('publish', $business);

        return $business;
    }

    /** @param array<int, UploadedFile> $files @param array<int, string> $stored */
    private function storeMedia(Post $post, array $files, array &$stored): void
    {
        $offset = $post->media()->count();
        foreach ($files as $index => $file) {
            $path = $file->store(config('posts.media_directory').'/'.$post->id.'/media', ['disk' => config('posts.media_disk')]);
            if ($path === false) {
                throw new RuntimeException('Post media could not be stored.');
            }
            $stored[] = $path;
            $dimensions = @getimagesize($file->getRealPath()) ?: [null, null];
            $post->media()->create([
                'type' => 'image',
                'path' => $path,
                'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                'size' => $file->getSize(),
                'width' => $dimensions[0],
                'height' => $dimensions[1],
                'sort_order' => $offset + $index,
            ]);
        }
    }

    /** @param array<int, string> $stored */
    private function deleteStored(array $stored): void
    {
        foreach ($stored as $path) {
            Storage::disk(config('posts.media_disk'))->delete($path);
        }
    }

    private function loadPost(Post $post): Post
    {
        return $post->load(['author', 'media'])->loadMorph('author', [User::class => ['profile']]);
    }
}
