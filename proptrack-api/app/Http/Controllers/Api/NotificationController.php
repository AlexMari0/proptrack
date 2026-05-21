<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/v1/notifications
     * Retrieve the authenticated user's database notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->get('per_page', 15);
        $notifications = $user->notifications()->latest()->paginate($perPage);
        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'unread_count' => $unreadCount,
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'per_page'     => $notifications->perPage(),
                'total'        => $notifications->total(),
            ],
            'message' => 'Success',
        ]);
    }

    /**
     * PUT /api/v1/notifications/{id}/read
     * Mark a single notification as read.
     */
    public function read(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'message' => 'Success',
        ]);
    }

    /**
     * PUT /api/v1/notifications/read-all
     * Mark all unread notifications of the user as read.
     */
    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'Success',
        ]);
    }
}
