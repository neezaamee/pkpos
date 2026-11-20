<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ReceiptTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptTemplateApiController extends Controller
{
    /**
     * Get the active receipt template for the authenticated POS tenant.
     */
    public function show(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $template = ReceiptTemplate::forTenant($tenant);

        return response()->json([
            'template' => $template,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'ntn' => $tenant->ntn,
                'strn' => $tenant->strn,
                'province' => $tenant->province,
                'fbr_pos_id' => $tenant->fbr_pos_id ?? 'POS-78601',
            ],
        ]);
    }
}
