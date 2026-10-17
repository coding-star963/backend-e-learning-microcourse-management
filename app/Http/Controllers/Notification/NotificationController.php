<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * The signed-in user's personal notices, newest first. Shaped like an
     * announcement (type "enrollment") so the mobile app lists both together.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->get('per_page', 50), 1), 100);

        $notices = $request->user()->notifications()
            ->latest()
            ->take($perPage)
            ->get()
            ->map(fn (DatabaseNotification $notice) => [
                'id' => 'notice-' . $notice->id,
                'type' => 'enrollment',
                'action' => $notice->data['action'] ?? null,
                'title' => $notice->data['title'] ?? 'Notice',
                'content' => $notice->data['content'] ?? '',
                'course_id' => $notice->data['course_id'] ?? null,
                'course' => isset($notice->data['course_id'])
                    ? ['id' => $notice->data['course_id'], 'title' => $notice->data['course_title'] ?? null]
                    : null,
                'enrollment_id' => $notice->data['enrollment_id'] ?? null,
                'user' => ['name' => $notice->data['actor_name'] ?? null],
                'is_read' => $notice->read_at !== null,
                'published_at' => $notice->created_at,
            ]);

        return response()->json(['data' => $notices]);
    }
}
