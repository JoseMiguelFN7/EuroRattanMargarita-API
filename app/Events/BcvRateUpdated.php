<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BcvRateUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $rate;
    public $message;
    public $isError;

    /**
     * Create a new event instance.
     */
    public function __construct($userId, $rate, $message = 'Tasa BCV sincronizada', $isError = false)
    {
        $this->userId = $userId;
        $this->rate = $rate;
        $this->message = $message;
        $this->isError = $isError;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Canal exclusivo para este proceso
        return [
            new PrivateChannel('bcv-rate-updates.' . $this->userId)
        ];
    }

    /**
     * Alias del evento para el Frontend.
     * Así evitas escuchar 'App\Events\BcvRateUpdated'
     */
    public function broadcastAs(): string
    {
        return 'RateUpdated';
    }
}