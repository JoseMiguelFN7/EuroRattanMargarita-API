<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportGenerated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $fileUrl;
    public $reportName;

    /**
     * Create a new event instance.
     */
    public function __construct($userId, $fileUrl, $reportName = 'Reporte')
    {
        $this->userId = $userId;
        $this->fileUrl = $fileUrl;
        $this->reportName = $reportName;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->userId),
        ];
    }

    public function broadcastAs()
    {
        return 'report.ready';
    }

    public function broadcastWith(): array
    {
        return [
            'url' => $this->fileUrl,
            'message' => "Tu {$this->reportName} está listo para descargar.",
        ];
    }
}
