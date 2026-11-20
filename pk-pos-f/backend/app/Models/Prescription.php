<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'sale_id',
        'patient_name',
        'patient_phone',
        'patient_cnic',
        'doctor_name',
        'doctor_pmdc_number',
        'hospital_clinic_name',
        'prescription_date',
        'diagnosis_notes',
        'prescription_image_path',
        'dispensed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'prescription_date' => 'date',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function dispensedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by_user_id');
    }

    public function narcoticEntries(): HasMany
    {
        return $this->hasMany(NarcoticRegisterEntry::class, 'prescription_id');
    }
}
