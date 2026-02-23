<?php

namespace App\Events;

use App\Http\Resources\HelpRequestResource;
use App\Models\HelpRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HelpRequestAssigned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public HelpRequest $helpRequest)
    {
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('user.'.$this->helpRequest->requester_id),
        ];

        if ($this->helpRequest->volunteer_id) {
            $channels[] = new PrivateChannel('volunteer.'.$this->helpRequest->volunteer_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'help-request.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'help_request' => (new HelpRequestResource($this->helpRequest))->resolve(),
        ];
    }
}
