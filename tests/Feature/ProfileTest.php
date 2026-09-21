<?php

namespace Tests\Feature;

use App\Models\Bicycle;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_staff_without_technician_notes_can_delete_their_account(): void
    {
        $user = User::factory()->staff()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->staff()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    /**
     * Phase 10A finding: deleting a technician/staff account that had
     * authored a technician note threw an uncaught QueryException (FK
     * violation on technician_notes.user_id, which has no
     * nullOnDelete/cascadeOnDelete). Phase 10B blocks the deletion instead,
     * with a clear message, and preserves the note.
     */
    public function test_technician_with_a_technician_note_cannot_delete_their_account(): void
    {
        $technician = User::factory()->technician()->create();
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();
        $booking = Booking::factory()->for($customer)->for($bicycle)->create();
        $note = $booking->technicianNotes()->create([
            'note' => 'Ordered a replacement part.',
            'user_id' => $technician->id,
        ]);

        $response = $this
            ->actingAs($technician)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'account')
            ->assertRedirect('/profile');

        $this->assertNotNull($technician->fresh(), 'the account must not be deleted');
        $this->assertNotNull($note->fresh(), 'the technician note must be preserved, not silently destroyed');
        $this->assertDatabaseHas('technician_notes', ['id' => $note->id, 'user_id' => $technician->id]);
    }

    /**
     * Phase 10A finding: bicycles.user_id/bookings.user_id both
     * cascadeOnDelete, so a customer deleting their own account silently,
     * irreversibly destroyed the workshop's own repair records. Phase 10B
     * disables self-service deletion for customers entirely rather than
     * redesigning that cascade.
     */
    public function test_customer_cannot_delete_their_account(): void
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();
        $booking = Booking::factory()->for($customer)->for($bicycle)->create();

        $response = $this
            ->actingAs($customer)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'account')
            ->assertRedirect('/profile');

        $this->assertNotNull($customer->fresh(), 'the customer account must not be deleted');
        $this->assertDatabaseHas('bicycles', ['id' => $bicycle->id]);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_customer_cannot_delete_their_account_even_with_a_crafted_request(): void
    {
        // No password supplied at all — proves the block happens before
        // (and independently of) the password-confirmation validation, so
        // a crafted request can't route around it by e.g. omitting fields.
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->delete('/profile', []);

        $response->assertSessionHasErrorsIn('userDeletion', 'account');
        $this->assertNotNull($customer->fresh());
    }

    public function test_delete_account_button_is_not_shown_to_customers(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get('/profile')
            ->assertOk()
            ->assertSee("isn't available for customer accounts")
            ->assertDontSee('Are you sure you want to delete your account?');
    }

    public function test_delete_account_button_is_shown_to_staff(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Are you sure you want to delete your account?');
    }
}
