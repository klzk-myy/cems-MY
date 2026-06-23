<?php

namespace Tests\Feature;

use App\Enums\RiskRating;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerRiskRatingDisplayTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function index_shows_customer_risk_rating(): void
    {
        $user = User::factory()->create(['role' => UserRole::Manager]);
        Customer::factory()->create(['risk_rating' => RiskRating::High]);

        $response = $this->actingAs($user)->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertSee('High');
    }

    #[Test]
    public function edit_form_has_correct_fields(): void
    {
        $customer = Customer::factory()->create(['risk_rating' => RiskRating::High]);

        $view = $this->view('customers.edit', [
            'customer' => $customer,
            'idTypes' => [
                'MyKad' => 'MyKad (Malaysian IC)',
                'Passport' => 'Passport',
                'Others' => 'Other ID',
            ],
            'riskRatings' => ['Low', 'Medium', 'High'],
            'nationalities' => [
                'Malaysian', 'Singaporean', 'Indonesian', 'Thai',
                'Filipino', 'Vietnamese', 'Chinese', 'Indian',
                'Bangladeshi', 'Pakistani', 'Other',
            ],
            'decryptedIdNumber' => '123456789012',
        ]);

        $view->assertSee('name="risk_rating"', false);
        $view->assertSee('value="High"', false); // selected value
        $view->assertSee('MyKad (Malaysian IC)', false);
        $view->assertSee('Passport', false);
        $view->assertSee('Other ID', false);
    }

    #[Test]
    public function create_form_does_not_have_risk_level_and_has_id_types(): void
    {
        $view = $this->view('customers.create');

        $view->assertDontSee('name="risk_level"', false);
        $view->assertSee('MyKad (Malaysian IC)', false);
        $view->assertSee('Passport', false);
        $view->assertSee('Other ID', false);
    }
}
