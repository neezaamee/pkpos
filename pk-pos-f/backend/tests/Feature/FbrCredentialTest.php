<?php

use App\Models\FbrCredential;
use App\Models\Tenant;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

test('fbr_credentials.bearer_token is encrypted at rest', function () {
    $tenant = Tenant::factory()->create();
    $rawToken = 'fbr_live_bearer_secret_token_1234567890_abcdef';

    $credential = FbrCredential::create([
        'tenant_id' => $tenant->id,
        'pos_id' => 'POS-5544',
        'ntn' => '7654321-0',
        'strn' => '32-10-9876-543-21',
        'bearer_token' => $rawToken,
        'token_expires_at' => now()->addDays(30),
    ]);

    // 1. Eloquent automatically decrypts the bearer_token
    expect($credential->bearer_token)->toBe($rawToken);

    // 2. Direct raw database query asserts the stored value is encrypted and not plaintext
    $rawRow = DB::table('fbr_credentials')->where('id', $credential->id)->first();

    expect($rawRow->bearer_token)->not->toBe($rawToken)
        ->and($rawRow->bearer_token)->not->toContain($rawToken);

    // 3. Decrypting the raw database value with Laravel's Crypt decrypter recovers the plaintext
    $decryptedValue = Crypt::decryptString($rawRow->bearer_token);
    expect($decryptedValue)->toBe($rawToken);
});
