<?php

namespace App\Services;

use App\Enums\ReelStatus;
use App\Models\Reel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ReelProcessingService
{
    public function process(Reel $reel): Reel
    {
        if ($reel->status === ReelStatus::PUBLISHED) {
            return $reel;
        }

        try {
            $source = Storage::disk(config('reels.source_disk'))->readStream($reel->source_path);
            if ($source === false) {
                throw new \RuntimeException('Source video is unavailable.');
            }

            $playbackPath = config('reels.playback_directory').'/'.$reel->id.'/'.Str::uuid().'.mp4';
            $stored = Storage::disk(config('reels.playback_disk'))->put($playbackPath, $source);
            if (is_resource($source)) {
                fclose($source);
            }
            if (! $stored) {
                throw new \RuntimeException('Playback video could not be stored.');
            }

            $reel->update([
                'status' => ReelStatus::PUBLISHED,
                'playback_disk' => config('reels.playback_disk'),
                'playback_path' => $playbackPath,
                'processing_error' => null,
                'published_at' => now(),
            ]);
        } catch (Throwable) {
            $reel->update(['status' => ReelStatus::FAILED, 'processing_error' => 'Video processing failed.']);
        }

        return $reel->refresh();
    }
}
