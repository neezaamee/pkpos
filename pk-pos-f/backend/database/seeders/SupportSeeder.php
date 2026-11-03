<?php

namespace Database\Seeders;

use App\Models\KnowledgeBaseArticle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SupportSeeder extends Seeder
{
    /**
     * Run the knowledge base seeder.
     */
    public function run(): void
    {
        $articles = [
            [
                'category' => 'fbr_fiscal',
                'title' => 'Resolving FBR IRIS 401 Unauthorized & Invalid Token Errors',
                'slug' => 'resolving-fbr-iris-401-unauthorized',
                'content_markdown' => "### Root Cause\nFBR IRIS API tokens expire periodically or when a merchant re-registers POS devices on e.fbr.gov.pk.\n\n### Resolution Steps\n1. Log into your **FBR IRIS Portal** (e.fbr.gov.pk).\n2. Navigate to **POS Integration Settings** > **Active POS Terminals**.\n3. Generate a new Bearer Token for your assigned POS ID.\n4. In PK-POS Admin, open **FBR Credentials** and paste the new token.\n5. Click **Test FBR Gateway Ping** from the Super Admin compliance center.",
                'is_published' => true,
            ],
            [
                'category' => 'hardware_printers',
                'title' => 'ESC/POS 80mm Thermal Receipt Printer Setup & USB Drivers',
                'slug' => 'esc-pos-80mm-thermal-printer-setup',
                'content_markdown' => "### Recommended Configuration\n- **Interface**: USB or Virtual Serial Port.\n- **Baud Rate**: 9600 bps or 115200 bps (depending on Xprinter / Epson model).\n- **Paper Cut Command**: `0x1D, 0x56, 0x41, 0x00`.\n- **QR Code Density**: 4 dots per module for FBR fiscal QR compliance.",
                'is_published' => true,
            ],
            [
                'category' => 'fbr_fiscal',
                'title' => 'Handling 24-Hour Provisional Offline Receipt Synchronizations',
                'slug' => 'handling-24-hour-provisional-receipts',
                'content_markdown' => "Under Pakistan Sales Tax rules, provisional receipts issued during internet outages must be synchronized with the FBR fiscal server within 24 hours.\n\nIf offline sales remain pending:\n1. Verify internet connectivity on the counter terminal.\n2. In desktop client settings, trigger **Force Outbox Sync**.\n3. If FBR gateway is degraded, view the **Provisional Tracker** in Super Admin to monitor statutory deadlines.",
                'is_published' => true,
            ],
        ];

        foreach ($articles as $art) {
            KnowledgeBaseArticle::firstOrCreate(['slug' => $art['slug']], $art);
        }
    }
}

