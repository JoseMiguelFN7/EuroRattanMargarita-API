<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Notifications\AdminAlertNotification;

class NotificationService
{
    public static function notifyByPermission(
        string $permissionSlug, 
        string $title, 
        string $message, 
        ?string $targetType = null, 
        ?string $targetId = null, 
        string $type = 'info'
    ): void {
        $targetUsers = User::withPermission($permissionSlug)->get();

        \Illuminate\Support\Facades\Log::info("Intentando enviar notificación. Usuarios encontrados con permiso {$permissionSlug}: " . $targetUsers->count());

        if ($targetUsers->isNotEmpty()) {
            Notification::send(
                $targetUsers, 
                new AdminAlertNotification($title, $message, $targetType, $targetId, $type)
            );
        }
    }
}