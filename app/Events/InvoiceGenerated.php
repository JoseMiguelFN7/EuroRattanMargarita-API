<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceGenerated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct($orderCode, $invoiceUrl, $userId)
    {
        $this->orderCode = $orderCode;
        $this->invoiceUrl = $invoiceUrl;
        $this->userId = $userId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Emitimos en un canal privado exclusivo para administradores viendo esta orden
        return [
            new PrivateChannel('admin.order.' . $this->orderCode),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'order_code'  => $this->orderCode,
            'invoice_url' => asset('storage/' . $this->invoiceUrl),
        ];
    }

    public function broadcastAs(): string
    {
        return 'InvoiceGenerated';
    }
}
