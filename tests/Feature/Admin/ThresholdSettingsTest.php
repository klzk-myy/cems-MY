<?php

namespace Tests\Feature\Admin;

use App\Models\ThresholdAudit;
use App\Models\User;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature coverage for the admin threshold settings page: authorization,
 * editing (audited DB overrides), ordering constraints, and resets.
 */
class ThresholdSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    #[Test]
    public function admin_can_view_thresholds_page(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.thresholds.index'))
            ->assertOk()
            ->assertSee('approval.auto_approve')
            ->assertSee('cdd.large_transaction')
            ->assertSee('position_limits.usd');
    }

    #[Test]
    public function teller_is_forbidden(): void
    {
        $teller = User::factory()->teller()->create();

        $this->actingAs($teller)
            ->get(route('admin.thresholds.index'))
            ->assertForbidden();
    }

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.thresholds.index'))
            ->assertRedirect('/login');
    }

    #[Test]
    public function update_persists_audited_override(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('admin.thresholds.update'), [
                'reason' => 'BNM circular update',
                'values' => ['approval' => ['auto_approve' => '12000']],
            ])
            ->assertRedirect(route('admin.thresholds.index'));

        $audit = ThresholdAudit::where('category', 'approval')
            ->where('key', 'auto_approve')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('10000', $audit->old_value);
        $this->assertEquals('12000', $audit->new_value);
        $this->assertEquals('BNM circular update', $audit->change_reason);
        $this->assertEquals($this->admin->id, $audit->changed_by);

        $this->assertEquals('12000', app(ThresholdService::class)->get('approval', 'auto_approve'));
    }

    #[Test]
    public function update_with_unchanged_value_writes_no_audit_row(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('admin.thresholds.update'), [
                'reason' => 'no-op save',
                'values' => ['approval' => ['auto_approve' => '10000']],
            ])
            ->assertRedirect(route('admin.thresholds.index'));

        $this->assertEquals(0, ThresholdAudit::where('change_reason', 'no-op save')->count());
    }

    #[Test]
    public function update_requires_a_reason(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('admin.thresholds.update'), [
                'values' => ['approval' => ['auto_approve' => '12000']],
            ])
            ->assertSessionHasErrors('reason');
    }

    #[Test]
    public function update_rejects_non_numeric_values(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('admin.thresholds.update'), [
                'reason' => 'test',
                'values' => ['approval' => ['auto_approve' => 'abc']],
            ])
            ->assertSessionHasErrors('values.approval.auto_approve');
    }

    #[Test]
    public function update_rejects_unknown_keys(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('admin.thresholds.update'), [
                'reason' => 'test',
                'values' => ['approval' => ['not_a_key' => '12000']],
            ])
            ->assertSessionHasErrors('values.approval.not_a_key');

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('admin.thresholds.update'), [
                'reason' => 'test',
                'values' => ['not_a_category' => ['x' => '1']],
            ])
            ->assertSessionHasErrors('values.not_a_category');
    }

    #[Test]
    public function update_enforces_ordering_constraints(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        // cdd.specific must be <= cdd.standard (10000)
        $this->withSession($this->passwordConfirmedSession())
            ->post(route('admin.thresholds.update'), [
                'reason' => 'test',
                'values' => ['cdd' => ['specific' => '20000']],
            ])
            ->assertSessionHasErrors('values.cdd.standard');

        // rates spread must stay within [min_spread, max_spread]
        $this->withSession($this->passwordConfirmedSession())
            ->post(route('admin.thresholds.update'), [
                'reason' => 'test',
                'values' => ['rates' => ['spread' => '0.5']],
            ])
            ->assertSessionHasErrors('values.rates.max_spread');
    }

    #[Test]
    public function page_shows_override_badge_after_update(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        app(ThresholdService::class)->set('variance', 'yellow', '250', 'test override');

        $this->get(route('admin.thresholds.index'))
            ->assertOk()
            ->assertSee('database overrides')
            ->assertSee('override');
    }

    #[Test]
    public function reset_returns_key_to_config_default(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $service = app(ThresholdService::class);
        $service->set('variance', 'yellow', '250', 'temporary');
        $this->assertEquals('250', $service->get('variance', 'yellow'));

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('admin.thresholds.reset'), [
                'category' => 'variance',
                'key' => 'yellow',
            ])
            ->assertRedirect(route('admin.thresholds.index'));

        // Fresh instance: the earlier get() cached '250' on $service.
        $this->assertEquals('100.00', (new ThresholdService)->get('variance', 'yellow'));
        $this->assertEquals(2, ThresholdAudit::where('category', 'variance')->where('key', 'yellow')->count());
    }

    #[Test]
    public function reset_rejects_unknown_threshold(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('admin.thresholds.reset'), [
                'category' => 'approval',
                'key' => 'bogus',
            ])
            ->assertSessionHasErrors('key');
    }

    #[Test]
    public function mutations_require_password_confirmation(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->post(route('admin.thresholds.update'), [
            'reason' => 'test',
            'values' => ['approval' => ['auto_approve' => '12000']],
        ])->assertRedirect(route('password.confirm'));

        $this->post(route('admin.thresholds.reset'), [
            'category' => 'variance',
            'key' => 'yellow',
        ])->assertRedirect(route('password.confirm'));
    }
}
