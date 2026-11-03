<?php

use App\Models\AuditLog;
use App\Models\KnowledgeBaseArticle;
use App\Models\Location;
use App\Models\PosTerminal;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create([
        'email' => 'superadmin@example.com',
    ]);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Metro Superstore',
        'ntn' => '7654321-0',
        'province' => 'punjab',
        'fbr_pos_id' => 'POS-7788',
    ]);

    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
        'super_admin' => false,
    ]);

    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Model Town Branch',
        'province' => 'punjab',
    ]);

    $this->terminal = PosTerminal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'location_id' => $this->location->id,
        'terminal_code' => 'POS-REG-01',
        'name' => 'Counter 1',
        'hardware_uuid' => 'HW-TEST-UUID-1234',
        'hostname' => 'COUNTER-PC',
        'platform_os' => 'windows',
        'app_version' => '0.2.0',
        'pairing_status' => 'paired',
        'is_active' => true,
    ]);
});

test('non-super admin cannot access superadmin support tickets queue', function () {
    $this->actingAs($this->owner)
        ->get(route('superadmin.support.tickets.index'))
        ->assertForbidden();
});

test('super admin can view support tickets stream with metrics', function () {
    SupportTicket::withoutGlobalScopes()->create([
        'ticket_number' => 'TCK-001',
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'subject' => 'Thermal printer USB communication stopped',
        'category' => 'hardware_printer',
        'priority' => 'critical_counter_down',
        'status' => 'open',
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.support.tickets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Support/Index')
            ->has('tickets.data', 1)
            ->where('metrics.open_count', 1)
            ->where('metrics.critical_count', 1)
        );
});

test('super admin can view ticket details with machine diagnostic snapshot', function () {
    $snapshot = SupportTicket::buildDiagnosticSnapshot(
        $this->tenant,
        $this->terminal,
        '401 Unauthorized: Bearer Token Expired',
        5
    );

    $ticket = SupportTicket::withoutGlobalScopes()->create([
        'ticket_number' => 'TCK-002',
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'subject' => 'FBR Invoices Failing with 401',
        'category' => 'fbr_sync_failure',
        'priority' => 'high',
        'status' => 'open',
        'diagnostic_snapshot' => $snapshot,
    ]);

    SupportMessage::create([
        'ticket_id' => $ticket->id,
        'user_id' => $this->owner->id,
        'message' => 'Sales from counter 1 are stuck in outbox.',
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.support.tickets.show', $ticket->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Support/Show')
            ->where('ticket.ticket_number', 'TCK-002')
            ->where('ticket.diagnostic_snapshot.tenant.fbr_pos_id', 'POS-7788')
            ->where('ticket.diagnostic_snapshot.telemetry.pending_outbox_count', 5)
            ->has('messages', 1)
        );
});

test('super admin can post public reply which transitions status to in_progress', function () {
    $ticket = SupportTicket::withoutGlobalScopes()->create([
        'ticket_number' => 'TCK-003',
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'subject' => 'Need help with tax template',
        'category' => 'tax_calculation',
        'priority' => 'medium',
        'status' => 'open',
    ]);

    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.support.tickets.reply', $ticket->id), [
            'message' => 'We have synchronized the updated PRA 5% restaurant tax template to your tenant.',
            'is_internal_note' => false,
        ])
        ->assertRedirect();

    expect($ticket->fresh()->status)->toBe('in_progress');

    $this->assertDatabaseHas('support_messages', [
        'ticket_id' => $ticket->id,
        'message' => 'We have synchronized the updated PRA 5% restaurant tax template to your tenant.',
        'is_internal_note' => false,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'support_reply_sent',
        'auditable_type' => SupportTicket::class,
    ]);
});

test('super admin can add internal staff note', function () {
    $ticket = SupportTicket::withoutGlobalScopes()->create([
        'ticket_number' => 'TCK-004',
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'subject' => 'Billing inquiry',
        'category' => 'billing',
        'priority' => 'low',
        'status' => 'open',
    ]);

    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.support.tickets.reply', $ticket->id), [
            'message' => 'Verified merchant bank transfer reference #PK998822.',
            'is_internal_note' => true,
        ])
        ->assertRedirect();

    // Internal note should NOT transition status
    expect($ticket->fresh()->status)->toBe('open');

    $this->assertDatabaseHas('support_messages', [
        'ticket_id' => $ticket->id,
        'is_internal_note' => true,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'support_internal_note_added',
        'auditable_type' => SupportTicket::class,
    ]);
});

test('super admin can update ticket status and assign staff member', function () {
    $ticket = SupportTicket::withoutGlobalScopes()->create([
        'ticket_number' => 'TCK-005',
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'subject' => 'General inquiry',
        'status' => 'open',
    ]);

    // Assign staff
    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.support.tickets.assign', $ticket->id), [
            'assigned_to_user_id' => $this->superAdmin->id,
        ])
        ->assertRedirect();

    expect($ticket->fresh()->assigned_to_user_id)->toBe($this->superAdmin->id);

    // Resolve ticket
    $this->actingAs($this->superAdmin)
        ->post(route('superadmin.support.tickets.status', $ticket->id), [
            'status' => 'resolved',
        ])
        ->assertRedirect();

    $fresh = $ticket->fresh();
    expect($fresh->status)->toBe('resolved')
        ->and($fresh->resolved_at)->not->toBeNull();
});

test('super admin can view knowledge base articles', function () {
    KnowledgeBaseArticle::create([
        'category' => 'fbr_fiscal',
        'title' => 'FBR IRIS 401 Error Resolution',
        'slug' => 'fbr-iris-401-error',
        'content_markdown' => 'Follow these instructions to renew token.',
        'is_published' => true,
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('superadmin.support.knowledge-base'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Support/KnowledgeBase')
            ->has('articles', 1)
            ->where('articles.0.title', 'FBR IRIS 401 Error Resolution')
        );
});

test('client API: merchant user can submit a ticket which auto-captures diagnostic snapshot', function () {
    $token = $this->owner->createToken('test-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/support/tickets', [
            'subject' => 'POS counter printer failing to cut receipts',
            'category' => 'hardware_printer',
            'priority' => 'high',
            'message' => 'The cutter command seems ignored after updating Windows.',
            'terminal_code' => 'POS-REG-01',
            'fbr_error' => 'None',
            'pending_outbox_count' => 0,
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'ticket_id',
            'ticket_number',
            'status',
            'subject',
        ]);

    $ticket = SupportTicket::withoutGlobalScopes()->where('subject', 'POS counter printer failing to cut receipts')->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->diagnostic_snapshot)->not->toBeNull();
    expect($ticket->diagnostic_snapshot['terminal']['terminal_code'])->toBe('POS-REG-01');
    expect($ticket->diagnostic_snapshot['tenant']['ntn'])->toBe('7654321-0');

    // Initial message exists
    $msg = SupportMessage::where('ticket_id', $ticket->id)->first();
    expect($msg)->not->toBeNull();
    expect($msg->message)->toBe('The cutter command seems ignored after updating Windows.');
});

test('client API: merchant user can list their own tickets', function () {
    $token = $this->owner->createToken('test-token')->plainTextToken;

    SupportTicket::withoutGlobalScopes()->create([
        'ticket_number' => 'TCK-LIST-01',
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'subject' => 'My open ticket',
        'status' => 'open',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/support/tickets');

    $response->assertOk()
        ->assertJsonFragment(['ticket_number' => 'TCK-LIST-01']);
});

