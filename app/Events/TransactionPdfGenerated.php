<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionPdfGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $userId,
        public string $filename,
        public string $path,
        public string $channel
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel($this->channel),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'userId' => $this->userId,
            'filename' => $this->filename,
            'path' => $this->path,
            'channel' => $this->channel,
        ];
    }
}
