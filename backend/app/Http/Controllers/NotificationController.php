<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Sininho: notificações de quem está logado. */
class NotificationController extends Controller
{
    /** As mais recentes, com quantas ainda não foram lidas. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        return NotificationResource::collection(
            $user->notifications()->limit(config('notifications.page_size'))->get(),
        )->additional(['meta' => ['unread' => $user->unreadNotifications()->count()]]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['count' => $request->user()->unreadNotifications()->count()]]);
    }

    /** Marca todas como lidas (ao abrir a lista). */
    public function readAll(Request $request): Response
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function read(Request $request, string $id): Response
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->noContent();
    }
}
