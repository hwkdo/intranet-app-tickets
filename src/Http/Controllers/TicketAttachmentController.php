<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Http\Controllers;

use Hwkdo\IntranetAppTickets\Services\ZammadTicketService;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class TicketAttachmentController extends Controller
{
    public function __invoke(
        int $ticketId,
        int $articleId,
        int $attachmentId,
        ZammadTicketService $ticketService,
    ): Response {
        $user = Auth::user();

        abort_unless($user !== null, 403);

        $meta = $ticketService->getAttachmentMeta($user, $ticketId, $articleId, $attachmentId);
        $content = $ticketService->getAttachmentContent($user, $ticketId, $articleId, $attachmentId);

        abort_if($meta === null || $content === null, 404);

        $disposition = str_starts_with($meta['content_type'], 'image/')
            ? 'inline'
            : 'attachment';

        return response($content, 200, [
            'Content-Type' => $meta['content_type'],
            'Content-Disposition' => $disposition.'; filename="'.$meta['filename'].'"',
        ]);
    }
}
