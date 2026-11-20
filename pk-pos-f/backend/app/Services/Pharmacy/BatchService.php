<?php

namespace App\Services\Pharmacy;

use App\Models\Location;
use App\Models\NarcoticRegisterEntry;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BatchService
{
    /**
     * Create or update a pharmaceutical batch record.
     *
     * @param Tenant $tenant
     * @param array<string, mixed> $data
     * @return ProductBatch
     */
    public function recordBatch(Tenant $tenant, array $data): ProductBatch
    {
        return DB::transaction(function () use ($tenant, $data) {
            $data['tenant_id'] = $tenant->id;

            if (empty($data['product_id']) || empty($data['batch_number']) || empty($data['expiry_date'])) {
                throw new InvalidArgumentException('Product, batch number, and expiry date are required.');
            }

            // Expiry date validation: active batches should have a valid date
            $expiry = \Carbon\Carbon::parse($data['expiry_date']);
            $isExpired = $expiry->isPast();

            $batch = ProductBatch::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->where('product_id', $data['product_id'])
                ->where('location_id', $data['location_id'] ?? null)
                ->where('batch_number', $data['batch_number'])
                ->first();

            if ($batch) {
                $batch->update([
                    'manufacturing_date' => $data['manufacturing_date'] ?? $batch->manufacturing_date,
                    'expiry_date' => $data['expiry_date'],
                    'quantity_on_hand' => $data['quantity_on_hand'] ?? $batch->quantity_on_hand,
                    'mrp_minor' => $data['mrp_minor'] ?? $batch->mrp_minor,
                    'cost_minor' => $data['cost_minor'] ?? $batch->cost_minor,
                    'supplier_id' => $data['supplier_id'] ?? $batch->supplier_id,
                    'status' => $isExpired ? 'expired' : ($data['status'] ?? $batch->status),
                ]);
                return $batch;
            }

            $data['status'] = $isExpired ? 'expired' : ($data['status'] ?? 'active');

            return ProductBatch::create($data);
        });
    }

    /**
     * Resolve First-Expired, First-Out (FEFO) allocations for a requested quantity of medicine.
     *
     * @param Product $product
     * @param Location|string $location
     * @param float $requestedBaseQty
     * @return array<int, array{batch: ProductBatch, quantity: float}>
     */
    public function getFefoAllocations(Product $product, Location|string $location, float $requestedBaseQty): array
    {
        $locationId = $location instanceof Location ? $location->id : $location;

        // Fetch active, non-expired batches with positive stock, sorted by earliest expiry date (FEFO)
        $batches = ProductBatch::where('tenant_id', $product->tenant_id)
            ->where('product_id', $product->id)
            ->where('location_id', $locationId)
            ->where('status', 'active')
            ->where('quantity_on_hand', '>', 0)
            ->where('expiry_date', '>=', now()->toDateString())
            ->orderBy('expiry_date', 'asc')
            ->get();

        $allocations = [];
        $remainingToAllocate = $requestedBaseQty;

        foreach ($batches as $batch) {
            if ($remainingToAllocate <= 0) {
                break;
            }

            $availableInBatch = (float) $batch->quantity_on_hand;
            $take = min($availableInBatch, $remainingToAllocate);

            $allocations[] = [
                'batch' => $batch,
                'quantity' => $take,
            ];

            $remainingToAllocate -= $take;
        }

        return $allocations;
    }

    /**
     * Deduct stock from a specific batch (e.g. upon sale completion).
     */
    public function deductBatchStock(ProductBatch $batch, float $qty): void
    {
        DB::transaction(function () use ($batch, $qty) {
            $batch->refresh();
            $newQty = max(0, $batch->quantity_on_hand - $qty);
            $batch->quantity_on_hand = $newQty;
            $batch->save();
        });
    }

    /**
     * Restock a batch (e.g. upon Goods Received Note or customer return).
     */
    public function restockBatchStock(ProductBatch $batch, float $qty): void
    {
        DB::transaction(function () use ($batch, $qty) {
            $batch->refresh();
            $batch->quantity_on_hand += $qty;
            if ($batch->status === 'expired' && !$batch->isExpired()) {
                $batch->status = 'active';
            }
            $batch->save();
        });
    }

    /**
     * Record a statutory doctor prescription and log controlled substance narcotics register entries.
     *
     * @param Tenant $tenant
     * @param Sale $sale
     * @param array<string, mixed> $prescriptionData
     * @param User|string|null $dispensedBy
     * @return Prescription
     */
    public function recordPrescriptionAndNarcotics(
        Tenant $tenant,
        Sale $sale,
        array $prescriptionData,
        User|string|null $dispensedBy = null
    ): Prescription {
        return DB::transaction(function () use ($tenant, $sale, $prescriptionData, $dispensedBy) {
            $userId = $dispensedBy instanceof User ? $dispensedBy->id : ($dispensedBy ?? $sale->user_id);

            $prescription = Prescription::create([
                'tenant_id' => $tenant->id,
                'sale_id' => $sale->id,
                'patient_name' => $prescriptionData['patient_name'],
                'patient_phone' => $prescriptionData['patient_phone'] ?? null,
                'patient_cnic' => $prescriptionData['patient_cnic'] ?? null,
                'doctor_name' => $prescriptionData['doctor_name'],
                'doctor_pmdc_number' => $prescriptionData['doctor_pmdc_number'],
                'hospital_clinic_name' => $prescriptionData['hospital_clinic_name'] ?? null,
                'prescription_date' => $prescriptionData['prescription_date'] ?? now()->toDateString(),
                'diagnosis_notes' => $prescriptionData['diagnosis_notes'] ?? null,
                'prescription_image_path' => $prescriptionData['prescription_image_path'] ?? null,
                'dispensed_by_user_id' => $userId,
            ]);

            // Check if any sale item belongs to a narcotic/controlled drug
            $sale->loadMissing('items.product');

            foreach ($sale->items as $item) {
                $product = $item->product;
                if ($product && $product->is_narcotic) {
                    NarcoticRegisterEntry::create([
                        'tenant_id' => $tenant->id,
                        'prescription_id' => $prescription->id,
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'batch_id' => $item->batch_id,
                        'batch_number' => $item->batch_number ?: 'N/A',
                        'quantity_dispensed' => (float) $item->quantity,
                        'unit_code' => $item->unit_code ?: 'tab',
                        'patient_name' => $prescription->patient_name,
                        'patient_cnic' => $prescription->patient_cnic,
                        'doctor_name' => $prescription->doctor_name,
                        'doctor_pmdc_number' => $prescription->doctor_pmdc_number,
                        'dispensed_by_user_id' => $userId,
                        'dispensed_at' => now(),
                        'notes' => $prescriptionData['diagnosis_notes'] ?? null,
                    ]);
                }
            }

            return $prescription;
        });
    }

    /**
     * Get batches nearing expiration within threshold (default 90 days) for expiry audit / distributor return.
     *
     * @param Tenant $tenant
     * @param int $days
     * @return Collection<int, ProductBatch>
     */
    public function getExpiringBatches(Tenant $tenant, int $days = 90): Collection
    {
        $today = now()->toDateString();
        $threshold = now()->addDays($days)->toDateString();

        return ProductBatch::with(['product', 'location', 'supplier'])
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('quantity_on_hand', '>', 0)
            ->whereBetween('expiry_date', [$today, $threshold])
            ->orderBy('expiry_date', 'asc')
            ->get();
    }

    /**
     * Automatically mark past-dated batches as 'expired'.
     */
    public function quarantineExpiredBatches(Tenant $tenant): int
    {
        return ProductBatch::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('expiry_date', '<', now()->toDateString())
            ->update(['status' => 'quarantined']);
    }
}
