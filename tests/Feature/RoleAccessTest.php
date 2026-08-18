<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_login_redirects_to_customer_home(): void
    {
        $customer = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->post('/login', [
            'email' => $customer->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->get(route('dashboard'))->assertRedirect(route('customer.home'));
    }

    public function test_staff_login_redirects_to_staff_dashboard(): void
    {
        $staff = User::factory()->staff()->create(['password' => bcrypt('password')]);

        $this->post('/login', [
            'email' => $staff->email,
            'password' => 'password',
        ]);

        $this->get(route('dashboard'))->assertRedirect(route('staff.dashboard'));
    }

    public function test_technician_login_redirects_to_staff_dashboard(): void
    {
        $technician = User::factory()->technician()->create(['password' => bcrypt('password')]);

        $this->post('/login', [
            'email' => $technician->email,
            'password' => 'password',
        ]);

        $this->get(route('dashboard'))->assertRedirect(route('staff.dashboard'));
    }

    public function test_customer_cannot_access_staff_area(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('staff.dashboard'))->assertForbidden();
    }

    public function test_staff_cannot_access_customer_area(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('customer.home'))->assertForbidden();
    }

    public function test_technician_can_access_staff_area(): void
    {
        $technician = User::factory()->technician()->create();

        $this->actingAs($technician)->get(route('staff.dashboard'))->assertOk();
    }

    public function test_guest_is_redirected_to_login_for_protected_areas(): void
    {
        $this->get(route('customer.home'))->assertRedirect(route('login'));
        $this->get(route('staff.dashboard'))->assertRedirect(route('login'));
    }
}
