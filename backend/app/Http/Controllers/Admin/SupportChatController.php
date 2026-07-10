<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Services\SupportChatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class SupportChatController extends Controller
{
    public function __construct(
        private readonly SupportChatService $chatService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Support/Index', [
            'conversations' => $this->chatService->adminInbox()->values()->all(),
            'totalUnread' => $this->chatService->totalAdminUnreadCount(),
        ]);
    }

    public function show(SupportConversation $conversation): Response
    {
        $thread = $this->chatService->adminThreadPayload($conversation);
        $thread['messages'] = collect($thread['messages'])
            ->map(function (array $message): array {
                $message['is_mine'] = $message['sender_role'] === 'admin';

                return $message;
            })
            ->all();

        return Inertia::render('Admin/Support/Show', [
            'conversation' => $thread['conversation'],
            'messages' => $thread['messages'],
            'conversations' => $this->chatService->adminInbox()->values()->all(),
            'totalUnread' => $this->chatService->totalAdminUnreadCount(),
        ]);
    }

    public function store(Request $request, SupportConversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->chatService->sendAdminMessage(
                $conversation,
                $request->user(),
                $validated['body'],
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['body' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.support.show', $conversation)
            ->with('success', 'Сообщение отправлено.');
    }
}
