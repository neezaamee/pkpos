<?php

namespace App\Http\Controllers;

use App\Models\FbrCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FbrCredentialsController extends Controller
{
    /**
     * Display the FBR credentials configuration form.
     */
    public function index(Request $request): Response
    {
        abort_if($request->user()->role !== 'owner', 403, 'Unauthorized. Only tenant owners can configure FBR credentials.');

        $credential = FbrCredential::where('tenant_id', $request->user()->tenant_id)->first();

        return Inertia::render('FbrCredentials', [
            'credentials' => $credential ? [
                'pos_id' => $credential->pos_id,
                'ntn' => $credential->ntn,
                'strn' => $credential->strn,
                'token_expires_at' => $credential->token_expires_at?->format('Y-m-d'),
            ] : null,
            'hasExistingToken' => ! empty($credential?->bearer_token),
        ]);
    }

    /**
     * Store or update the FBR credentials.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user()->role !== 'owner', 403, 'Unauthorized. Only tenant owners can configure FBR credentials.');

        $validated = $request->validate([
            'pos_id' => ['required', 'string', 'max:50'],
            'ntn' => ['required', 'string', 'max:50'],
            'strn' => ['nullable', 'string', 'max:50'],
            'bearer_token' => ['nullable', 'string', 'min:8'],
            'token_expires_at' => ['nullable', 'date'],
        ]);

        $credential = FbrCredential::firstOrNew([
            'tenant_id' => $request->user()->tenant_id,
        ]);

        $credential->pos_id = $validated['pos_id'];
        $credential->ntn = $validated['ntn'];
        $credential->strn = $validated['strn'] ?? null;
        $credential->token_expires_at = $validated['token_expires_at'] ?? null;

        if (! empty($validated['bearer_token'])) {
            $credential->bearer_token = $validated['bearer_token'];
        }

        $credential->save();

        return redirect()->back()->with('success', 'FBR credentials successfully saved.');
    }
}
