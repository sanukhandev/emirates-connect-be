<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoryResource;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
    public function index(Request $request)
    {
        return StoryResource::collection(Story::query()->where('expires_at', '>', now())->with(['user.profile'])->latest('created_at')->limit(30)->get());
    }

    public function store(Request $request): StoryResource
    {
        $data = $request->validate(['body' => ['nullable', 'string', 'max:500'], 'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192']]);
        abort_if(blank($data['body'] ?? null) && ! $request->hasFile('media'), 422, 'A story needs text or an image.');
        $story = Story::create(['user_id' => $request->user()->id, 'body' => $data['body'] ?? null, 'expires_at' => now()->addDay()]);
        if ($request->hasFile('media')) {
            $path = $request->file('media')->store('stories/'.$story->id, ['disk' => 'public']);
            $story->update(['media_path' => $path, 'media_type' => $request->file('media')->getMimeType()]);
        }

        return StoryResource::make($story->load('user.profile'));
    }

    public function destroy(Request $request, Story $story): void
    {
        abort_unless($story->user_id === $request->user()->id, 404);
        if ($story->media_path) {
            Storage::disk('public')->delete($story->media_path);
        }
        $story->delete();
    }
}
