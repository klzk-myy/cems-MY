<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Repositories\CustomerRepository;
use App\Support\LikeEscaper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerRepositorySearchTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function escape_like_keeps_plain_text_unchanged(): void
    {
        $this->assertSame('John Doe', LikeEscaper::escape('John Doe'));
        $this->assertSame('', LikeEscaper::escape(''));
    }

    #[Test]
    public function search_active_treats_percent_as_literal_character(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'John 100% Senior', 'is_active' => true]);
        Customer::factory()->create(['full_name' => 'John 100X Senior', 'is_active' => true]);

        $results = app(CustomerRepository::class)->searchActive('John 100% Senior');

        $this->assertCount(1, $results);
        $this->assertTrue($results->contains('id', $customer->id));
    }

    #[Test]
    public function search_active_treats_underscore_as_literal_character(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'A_B', 'is_active' => true]);
        Customer::factory()->create(['full_name' => 'AXB', 'is_active' => true]);

        $results = app(CustomerRepository::class)->searchActive('A_B');

        $this->assertCount(1, $results);
        $this->assertTrue($results->contains('id', $customer->id));
    }
}
