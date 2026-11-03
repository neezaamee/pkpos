<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\TenantApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicOnboardingController extends Controller
{
    /**
     * Show public merchant self-registration application form.
     */
    public function create(): Response
    {
        $plans = Plan::where('is_active', true)
            ->select('id', 'name', 'code', 'price_minor', 'features', 'max_locations', 'max_terminals')
            ->get();

        return Inertia::render('Public/Apply', [
            'plans' => $plans,
        ]);
    }

    /**
     * Store new merchant self-registration application.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'sector' => ['required', 'string', 'in:supermarket,pharmacy,apparel,restaurant,electronics,general_retail'],
            'province' => ['required', 'string', 'in:punjab,sindh,kpk,balochistan,islamabad'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_name' => ['required', 'string', 'max:100'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'ntn' => ['nullable', 'string', 'max:30'],
            'strn' => ['nullable', 'string', 'max:30'],
            'pos_terminal_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'requested_plan_id' => ['nullable', 'exists:plans,id'],
            'document_url' => ['nullable', 'string', 'max:500'],
        ]);

        $appNumber = TenantApplication::generateApplicationNumber();

        $application = TenantApplication::create([
            'application_number' => $appNumber,
            'business_name' => $validated['business_name'],
            'trade_name' => $validated['trade_name'] ?? null,
            'business_type' => 'retail',
            'sector' => $validated['sector'],
            'province' => $validated['province'],
            'city' => $validated['city'],
            'address' => $validated['address'] ?? null,
            'contact_name' => $validated['contact_name'],
            'contact_email' => $validated['contact_email'],
            'contact_phone' => $validated['contact_phone'],
            'ntn' => $validated['ntn'] ?? null,
            'strn' => $validated['strn'] ?? null,
            'pos_terminal_count' => $validated['pos_terminal_count'] ?? 1,
            'requested_plan_id' => $validated['requested_plan_id'] ?? null,
            'document_url' => $validated['document_url'] ?? null,
            'status' => 'pending_review',
        ]);

        // Auto verify ATL status
        $application->verifyFbrAtlStatus();

        return redirect()->route('apply')->with('success', "Your application #{$appNumber} has been received! Our onboarding team will verify your FBR ATL tax status and contact you shortly.");
    }
}

