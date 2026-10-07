<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $events = Event::query()->where('starts_at', '>=', now())->orderBy('starts_at')->withCount('attendees')->limit(10)->get();
        $userId = $request->user()?->id;
        if ($userId) {
            $events->each(fn (Event $event) => $event->setAttribute('is_rsvped', $event->attendees()->whereKey($userId)->exists()));
        }

        return EventResource::collection($events);
    }

    public function rsvp(Request $request, Event $event): EventResource
    {
        abort_if($event->starts_at->isPast(), 422, 'This event has already started.');
        $event->attendees()->syncWithoutDetaching([$request->user()->id]);

        return EventResource::make($event->loadCount('attendees')->setAttribute('is_rsvped', true));
    }

    public function cancelRsvp(Request $request, Event $event): EventResource
    {
        $event->attendees()->detach($request->user()->id);

        return EventResource::make($event->loadCount('attendees')->setAttribute('is_rsvped', false));
    }

    public function store(Request $request): EventResource
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'venue' => ['nullable', 'string', 'max:160'],
            'emirate' => ['nullable', 'string', Rule::in(['dubai', 'abu_dhabi', 'sharjah', 'ajman', 'umm_al_quwain', 'ras_al_khaimah', 'fujairah'])],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
        $event = Event::create([...$data, 'created_by' => $request->user()->id]);

        return EventResource::make($event->loadCount('attendees'));
    }
}
