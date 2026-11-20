<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Extend products table with DRAP and pharmaceutical classification fields
        Schema::table('products', function (Blueprint $table) {
            $table->string('drap_registration_number', 50)->nullable();
            $table->string('generic_name', 150)->nullable();
            $table->string('dosage_form', 50)->nullable(); // Tablet, Capsule, Syrup, Injection, Ointment, etc.
            $table->string('strength', 50)->nullable(); // 500mg, 10mg/5ml, etc.
            $table->string('manufacturer', 100)->nullable(); // GSK, Getz, Abbott, etc.
            $table->boolean('is_prescription_required')->default(false);
            $table->boolean('is_narcotic')->default(false); // Schedule D Controlled Drug
            $table->boolean('cold_chain')->default(false); // 2-8°C Temperature Warning
            $table->string('rack_location', 50)->nullable(); // Shelf A-12

            $table->index(['tenant_id', 'generic_name']);
            $table->index(['tenant_id', 'drap_registration_number']);
            $table->index(['tenant_id', 'is_narcotic']);
            $table->index(['tenant_id', 'is_prescription_required']);
        });

        // 2. Product Batches (FEFO Tracking & Expiry Dates)
        Schema::create('product_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            $table->string('batch_number', 50);
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date'); // Used by FEFO algorithm (order by expiry_date ASC)
            $table->decimal('quantity_on_hand', 12, 3)->default(0);
            $table->bigInteger('mrp_minor')->nullable(); // DRAP Maximum Retail Price
            $table->bigInteger('cost_minor')->nullable(); // Purchase cost per base unit
            $table->string('status', 20)->default('active'); // 'active', 'quarantined', 'recalled', 'expired'

            $table->timestamps();

            $table->index(['tenant_id', 'product_id', 'expiry_date']);
            $table->index(['tenant_id', 'batch_number']);
            $table->index(['tenant_id', 'status']);
            $table->index('expiry_date');
        });

        // 3. Extend sale_items table to record batch & expiry on customer receipts
        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignUuid('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->string('batch_number', 50)->nullable();
            $table->date('expiry_date')->nullable();
        });

        // 4. Doctor Prescriptions (PMDC / PMC License & Patient Details)
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('sale_id')->nullable()->constrained('sales')->nullOnDelete();

            $table->string('patient_name', 100);
            $table->string('patient_phone', 50)->nullable();
            $table->string('patient_cnic', 20)->nullable();
            $table->string('doctor_name', 100);
            $table->string('doctor_pmdc_number', 50); // Mandatory PMDC / PMC registration ID
            $table->string('hospital_clinic_name', 150)->nullable();
            $table->date('prescription_date');
            $table->text('diagnosis_notes')->nullable();
            $table->string('prescription_image_path', 255)->nullable();
            $table->foreignUuid('dispensed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'doctor_pmdc_number']);
            $table->index(['tenant_id', 'patient_cnic']);
            $table->index(['tenant_id', 'prescription_date']);
        });

        // 5. Narcotics & Controlled Substances Dispensing Register (Schedule D / Form 7)
        Schema::create('narcotic_register_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->foreignUuid('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();

            $table->string('batch_number', 50);
            $table->decimal('quantity_dispensed', 10, 2);
            $table->string('unit_code', 20)->default('tab');

            // Denormalized snapshot for immutable statutory inspection audits
            $table->string('patient_name', 100);
            $table->string('patient_cnic', 20)->nullable();
            $table->string('doctor_name', 100);
            $table->string('doctor_pmdc_number', 50);
            $table->foreignUuid('dispensed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispensed_at');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'dispensed_at']);
            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'doctor_pmdc_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('narcotic_register_entries');
        Schema::dropIfExists('prescriptions');

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn(['batch_id', 'batch_number', 'expiry_date']);
        });

        Schema::dropIfExists('product_batches');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'drap_registration_number',
                'generic_name',
                'dosage_form',
                'strength',
                'manufacturer',
                'is_prescription_required',
                'is_narcotic',
                'cold_chain',
                'rack_location',
            ]);
        });
    }
};
