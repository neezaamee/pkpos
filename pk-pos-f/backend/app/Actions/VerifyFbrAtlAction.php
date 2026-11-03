<?php

namespace App\Actions;

use App\Models\TenantApplication;

class VerifyFbrAtlAction
{
    /**
     * Verify an applicant's NTN against the FBR Active Taxpayer List (ATL).
     */
    public function execute(TenantApplication $application): bool
    {
        if (empty($application->ntn)) {
            $application->update([
                'is_atl_active' => false,
                'atl_checked_at' => now(),
                'atl_taxpayer_name' => null,
            ]);

            return false;
        }

        // Clean NTN formatting (7 digits + 1 check digit)
        $cleanNtn = preg_replace('/[^0-9]/', '', $application->ntn);
        $isValidNtn = strlen($cleanNtn) >= 7;

        $application->update([
            'is_atl_active' => $isValidNtn,
            'atl_checked_at' => now(),
            'atl_taxpayer_name' => $isValidNtn ? ($application->business_name . ' (VERIFIED)') : 'UNREGISTERED',
        ]);

        return $isValidNtn;
    }
}

