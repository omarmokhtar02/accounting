<?php

use Modules\Shared\Application\RecordAudit;
use Modules\Shared\Domain\Models\AuditLog;
use Modules\Tenancy\Infrastructure\TenantContext;

covers(RecordAudit::class);

beforeEach(function () {
    ['user' => $this->user, 'company' => $this->company] = bootstrapTenant();
    AuditLog::query()->delete();
});

it('records actor, tenant, company, entity, action and time', function () {
    $log = app(RecordAudit::class)->handle('journal_entry', 42, 'posted', ['s' => 'draft'], ['s' => 'posted'], 'because');

    expect($log->actor_id)->toBe($this->user->id)
        ->and($log->tenant_id)->toBe($this->company->tenant_id)
        ->and($log->company_id)->toBe($this->company->id)
        ->and($log->entity_type)->toBe('journal_entry')->and($log->entity_id)->toBe(42)->and($log->action)->toBe('posted')
        ->and($log->old_values)->toBe(['s' => 'draft'])->and($log->new_values)->toBe(['s' => 'posted'])
        ->and($log->reason)->toBe('because')->and($log->occurred_at)->not->toBeNull();
});

it('allows empty old and new values', function () {
    $log = app(RecordAudit::class)->handle('account', 1, 'created');

    expect($log->old_values)->toBeNull()->and($log->new_values)->toBeNull()->and($log->reason)->toBeNull();
});

it('records no actor for system work without a logged in user', function () {
    auth()->logout();

    expect(app(RecordAudit::class)->handle('x', 1, 'system')->actor_id)->toBeNull();
});

it('keeps audit rows inside their own tenant', function () {
    app(RecordAudit::class)->handle('x', 1, 'a');
    bootstrapTenant('other@example.com', 'Other');

    expect(AuditLog::where('entity_type', 'x')->count())->toBe(0);

    app(TenantContext::class)->setTenant($this->user->tenant);
    expect(AuditLog::where('entity_type', 'x')->count())->toBe(1);
});
