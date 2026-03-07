<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Obtener las notificaciones del usuario logueado.
     * GET /api/notifications
     */
    public function index(Request $request)
    {
        // Llamamos directamente a Sanctum
        $user = auth('sanctum')->user();
        
        $perPage = (int) $request->input('per_page', 15);
        if ($perPage <= 0) $perPage = 15;

        $unreadOnly = $request->boolean('unread_only', false);

        $totalUnread = $user->unreadNotifications()->count();

        $query = $user->notifications();

        if ($unreadOnly) {
            $query = $user->unreadNotifications();
        }

        $notifications = $query->paginate($perPage);

        $notifications->getCollection()->transform(function ($notification) {
            return [
                'id'          => $notification->id,
                'title'       => $notification->data['title'] ?? 'Notificación',
                'message'     => $notification->data['message'] ?? '',
                'target_type' => $notification->data['target_type'] ?? null,
                'target_id'   => $notification->data['target_id'] ?? null,
                'type'        => $notification->data['type'] ?? 'info',
                'is_read'     => $notification->read_at !== null,
                'created_at'  => $notification->created_at->format('d/m/Y h:i A'),
                'time_ago'    => $notification->created_at->diffForHumans(),
            ];
        });

        return response()->json(array_merge($notifications->toArray(), [
            'unread_count' => $totalUnread
        ]));
    }

    /**
     * Marcar una notificación específica como leída.
     * POST /api/notifications/{id}/read
     */
    public function markAsRead($id)
    {
        $notification = auth('sanctum')->user()->notifications()->find($id);

        if (!$notification) {
            return response()->json(['message' => 'Notificación no encontrada.'], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notificación marcada como leída.',
            'id' => $notification->id
        ], 200);
    }

    /**
     * Marcar todas las notificaciones del usuario como leídas.
     * POST /api/notifications/read-all
     */
    public function markAllAsRead()
    {
        auth('sanctum')->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'Todas las notificaciones han sido marcadas como leídas.'
        ], 200);
    }

    /**
     * Eliminar una notificación del historial.
     * POST /api/notifications/{id}/delete
     */
    public function destroy($id)
    {
        $notification = auth('sanctum')->user()->notifications()->find($id);

        if (!$notification) {
            return response()->json(['message' => 'Notificación no encontrada.'], 404);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notificación eliminada correctamente.'
        ], 200);
    }

    public function destroyAll()
    {
        $user = auth('sanctum')->user();

        $user->notifications()->delete();

        return response()->json([
            'message' => 'Todas las notificaciones han sido eliminadas del historial.'
        ], 200);
    }
}