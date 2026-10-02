<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationIndexRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(NotificationIndexRequest $request): mixed
    {
        $query = Notification::query()
            ->where('recipient_user_id', $request->user()->id)
            ->with([
                'actor' => fn (MorphTo $morphTo) => $morphTo->morphWith([User::class => ['profile']]),
                'subject',
            ])
            ->latest('created_at')->latest('id');
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        return NotificationResource::collection($query->cursorPaginate($request->integer('per_page', 20)));
    }

    public function unreadCount(Request $request): array
    {
        return ['count' => Notification::query()->where('recipient_user_id', $request->user()->id)->whereNull('read_at')->count()];
    }

    public function markRead(Request $request, Notification $notification): NotificationResource
    {
        $notification = Notification::query()->where('recipient_user_id', $request->user()->id)->findOrFail($notification->id);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return NotificationResource::make($notification->load(['actor' => fn (MorphTo $morphTo) => $morphTo->morphWith([User::class => ['profile']]), 'subject']));
    }

    public function markAllRead(Request $request): array
    {
        $updated = Notification::query()->where('recipient_user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return ['updated' => $updated];
    }
}
