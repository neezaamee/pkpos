<?php

namespace App\Http\Controllers;

use App\Models\ReceiptTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptTemplateWebController extends Controller
{
    /**
     * Display the receipt template editor.
     */
    public function edit(Request $request): Response
    {
        $this->authorizeAccess($request);

        $tenant = $request->user()->tenant;
        $template = ReceiptTemplate::forTenant($tenant);

        return Inertia::render('Settings/ReceiptTemplate', [
            'template' => $template,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'ntn' => $tenant->ntn,
                'strn' => $tenant->strn,
                'pos_id' => $tenant->fbr_pos_id ?? 'POS-78601',
            ],
        ]);
    }

    /**
     * Update the receipt template.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $tenant = $request->user()->tenant;
        $template = ReceiptTemplate::forTenant($tenant);

        $validated = $request->validate([
            'business_name' => ['nullable', 'string', 'max:150'],
            'branch_name' => ['nullable', 'string', 'max:150'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
            'website' => ['nullable', 'string', 'max:100'],
            'ntn_label' => ['required', 'string', 'max:30'],
            'strn_label' => ['required', 'string', 'max:30'],
            'pos_id_label' => ['required', 'string', 'max:30'],
            'paper_width' => ['required', Rule::in(['80mm', '58mm'])],
            'show_tax_breakdown' => ['required', 'boolean'],
            'show_customer_info' => ['required', 'boolean'],
            'show_fbr_qr' => ['required', 'boolean'],
            'header_note' => ['nullable', 'string', 'max:500'],
            'footer_policy' => ['nullable', 'string', 'max:500'],
            'footer_greeting' => ['nullable', 'string', 'max:500'],
            'logo_url' => ['nullable', 'string', 'max:255'],
        ]);

        $template->update($validated);

        return redirect()->back()->with('success', 'Receipt template updated successfully.');
    }

    /**
     * Authorize that the authenticated user is an owner or manager.
     */
    protected function authorizeAccess(Request $request): void
    {
        abort_unless(
            in_array($request->user()->role, ['owner', 'manager']),
            403,
            'Unauthorized. Requires owner or manager role.'
        );
    }
}
