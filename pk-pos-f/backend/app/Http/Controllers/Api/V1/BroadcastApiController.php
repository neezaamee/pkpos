<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BroadcastAnnouncement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class BroadcastApiController extends Controller
{
    /**
     * Retrieve currently active broadcast alerts for POS terminals or merchant web portal.
     */
    public function active(Request $request): JsonResponse
    {
        $province = $request->query('province');
        $isDesktop = $request->boolean('is_desktop', false);

        $tenantId = null;
        if ($bearer = $request->bearerToken()) {
            $token = PersonalAccessToken::findToken($bearer);
            if ($token && $token->tokenable) {
                $tenantId = $token->tokenable->tenant_id ?? null;
                if (! $province && $token->tokenable->tenant) {
                    $province = $token->tokenable->tenant->province;
                }
            }
        }

        $broadcasts = BroadcastAnnouncement::active()
            ->forAudience($province, $tenantId, $isDesktop)
            ->latest()
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'title' => $b->title,
                'message' => $b->message,
                'level' => $b->level,
                'target_audience' => $b->target_audience,
                'target_province' => $b->target_province,
                'dismissible' => (bool) $b->dismissible,
                'starts_at' => $b->starts_at?->toIso8601String(),
                'expires_at' => $b->expires_at?->toIso8601String(),
                'created_at' => $b->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'count' => $broadcasts->count(),
            'broadcasts' => $broadcasts,
        ]);
    }
}

