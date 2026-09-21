<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Bicycle;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 10A finding: after a customer declined proposed repairs, the
 * booking landed back in Inspection with no way for the workshop to
 * conclude the job if the customer simply wanted the bicycle back without
 * any work done. Phase 10B adds Staff\BookingController::closeDeclinedRepair(),
 * which reuses the existing Cancelled status (rather than a new one) for
 * that outcome — see Booking::wasJustDeclinedByCustomer()/
 * wasCancelledAfterCustomerDecline() and the controller's own docblock for
 * why that reuse is semantically correct.
 */
class DeclinedRepairWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Walks a fresh booking through inspection, a proposed repair item,
     * awaiting approval, and a customer decline — landing it in Inspection
     * with wasJustDeclinedByCustomer() true, exactly like the real flow.
     */
    private function declinedBooking(): Booking
    {
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();
        $booking = Booking::factory()->for($customer)->for($bicycle)->create();

        $booking->transitionTo(BookingStatus::Accepted);
        $booking->transitionTo(BookingStatus::BikeReceived);
        $booking->transitionTo(BookingStatus::Inspection);
        $booking->inspection()->create([
            'findings' => 'Cracked frame near the head tube.',
            'recommended_repairs' => 'Frame replacement.',
        ]);
        $booking->repairItems()->create(['description' => 'Replace frame']);
        $booking->transitionTo(BookingStatus::AwaitingCustomerApproval);

        $this->actingAs($customer)
            ->post(route('customer.repairs.decline', $booking))
            ->assertRedirect();

        return $booking->refresh();
    }

    public function test_close_declined_repair_rejects_a_booking_that_was_not_declined(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();
        $booking = Booking::factory()->for($customer)->for($bicycle)->create();
        $booking->forceFill(['status' => BookingStatus::Inspection])->save();

        $this->actingAs($staff)
            ->post(route('staff.bookings.close-declined', $booking))
            ->assertRedirect();

        $this->assertSame(BookingStatus::Inspection, $booking->refresh()->status);
    }

    public function test_staff_can_close_a_declined_repair_without_work_done(): void
    {
        $staff = User::factory()->staff()->create();
        $booking = $this->declinedBooking();

        $this->assertTrue($booking->wasJustDeclinedByCustomer());

        $this->actingAs($staff)
            ->post(route('staff.bookings.close-declined', $booking))
            ->assertRedirect();

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertTrue($booking->wasCancelledAfterCustomerDecline());
    }

    public function test_status_history_is_accurate_for_the_declined_and_closed_path(): void
    {
        $staff = User::factory()->staff()->create();
        $booking = $this->declinedBooking();

        $this->actingAs($staff)->post(route('staff.bookings.close-declined', $booking));

        $transitions = $booking->refresh()->statusHistories->sortBy('id')->values()
            ->map(fn ($h) => [$h->old_status->value, $h->new_status->value])
            ->all();

        $this->assertSame([
            ['pending', 'accepted'],
            ['accepted', 'bike_received'],
            ['bike_received', 'inspection'],
            ['inspection', 'awaiting_customer_approval'],
            ['awaiting_customer_approval', 'inspection'],
            ['inspection', 'cancelled'],
        ], $transitions);
    }

    public function test_staff_sees_clear_messaging_for_a_declined_and_closed_repair(): void
    {
        $staff = User::factory()->staff()->create();
        $booking = $this->declinedBooking();
        $this->actingAs($staff)->post(route('staff.bookings.close-declined', $booking));

        $this->actingAs($staff)
            ->get(route('staff.bookings.show', $booking))
            ->assertOk()
            ->assertSee('The customer declined the proposed repairs. The bicycle was returned without repair.')
            // Read-only historical context (findings/repair item) must still
            // be visible, not hidden along with the edit forms.
            ->assertSee('Cracked frame near the head tube.')
            ->assertSee('Replace frame');
    }

    public function test_customer_sees_clear_messaging_for_a_declined_and_closed_repair(): void
    {
        $staff = User::factory()->staff()->create();
        $booking = $this->declinedBooking();
        $this->actingAs($staff)->post(route('staff.bookings.close-declined', $booking));
        $booking->refresh();

        $this->actingAs($booking->user)
            ->get(route('customer.repairs.show', $booking))
            ->assertOk()
            ->assertSee('You declined the proposed repairs')
            ->assertSee('Your bicycle was returned without repair.')
            ->assertSee('Replace frame');
    }

    public function test_declined_and_closed_repair_is_not_counted_as_completed_service(): void
    {
        $staff = User::factory()->staff()->create();
        $booking = $this->declinedBooking();
        $this->actingAs($staff)->post(route('staff.bookings.close-declined', $booking));
        $booking->refresh();

        $bicycle = $booking->bicycle;
        $this->assertCount(0, $bicycle->completedBookings()->get());
        $this->assertCount(0, $bicycle->activeBookings()->get());
    }

    public function test_declining_and_closing_a_repair_produces_no_notification(): void
    {
        $staff = User::factory()->staff()->create();
        $booking = $this->declinedBooking();
        $customer = $booking->user;

        // accepted, bike_received, awaiting_customer_approval each notify —
        // decline (-> inspection) and close (-> cancelled) must not add more.
        $countAfterDecline = $customer->notifications()->count();
        $this->assertSame(3, $countAfterDecline);

        $this->actingAs($staff)->post(route('staff.bookings.close-declined', $booking));

        $this->assertSame($countAfterDecline, $customer->notifications()->count());
    }

    public function test_declined_and_closed_repair_is_excluded_from_the_staff_jobs_list(): void
    {
        $staff = User::factory()->staff()->create();
        $booking = $this->declinedBooking();
        $this->actingAs($staff)->post(route('staff.bookings.close-declined', $booking));

        $this->actingAs($staff)
            ->get(route('staff.jobs.index'))
            ->assertOk()
            ->assertDontSee($booking->refresh()->reference_number);
    }

    public function test_an_ordinary_early_cancellation_is_not_mistaken_for_a_decline(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->create();
        $bicycle = Bicycle::factory()->for($customer)->create();
        $booking = Booking::factory()->for($customer)->for($bicycle)->create();

        $this->actingAs($staff)->post(route('staff.bookings.cancel', $booking));

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertFalse($booking->wasCancelledAfterCustomerDecline());

        $this->actingAs($customer)
            ->get(route('customer.repairs.show', $booking))
            ->assertOk()
            ->assertSee('This booking was cancelled.')
            ->assertDontSee('You declined the proposed repairs');
    }
}
