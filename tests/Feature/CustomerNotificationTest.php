<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Bicycle;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingStatusUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function bookingFor(User $customer): Booking
    {
        $bicycle = Bicycle::factory()->for($customer)->create();

        return Booking::factory()->for($customer)->for($bicycle)->create();
    }

    /**
     * @return array<int, array{0: BookingStatus, 1: string}>
     */
    public static function notifiableStatuses(): array
    {
        return [
            'accepted' => [BookingStatus::Accepted, 'Your repair booking has been accepted.'],
            'bike received' => [BookingStatus::BikeReceived, "We've received your bicycle."],
            'awaiting customer approval' => [BookingStatus::AwaitingCustomerApproval, 'Your bicycle inspection is complete. Please review and approve the proposed repair work.'],
            'repair in progress' => [BookingStatus::RepairInProgress, 'Repair work on your bicycle has started.'],
            'repair completed' => [BookingStatus::RepairCompleted, 'Repair work on your bicycle has been completed and is awaiting quality inspection.'],
            'ready for pickup' => [BookingStatus::ReadyForPickup, 'Your bicycle is ready for pickup.'],
            'ready for delivery' => [BookingStatus::ReadyForDelivery, 'Your bicycle is ready for delivery.'],
            'completed' => [BookingStatus::Completed, 'Your bicycle repair has been completed.'],
        ];
    }

    #[DataProvider('notifiableStatuses')]
    public function test_transitioning_to_a_meaningful_status_notifies_the_customer(BookingStatus $status, string $expectedMessage): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);

        $booking->transitionTo($status);

        $this->assertSame(1, $customer->notifications()->count());

        $notification = $customer->notifications()->first();
        $this->assertSame($booking->id, $notification->data['booking_id']);
        $this->assertSame($booking->reference_number, $notification->data['booking_reference_number']);
        $this->assertSame($status->value, $notification->data['status']);
        $this->assertSame($expectedMessage, $notification->data['message']);
        $this->assertTrue($notification->unread());
    }

    /**
     * @return array<int, array{0: BookingStatus}>
     */
    public static function nonNotifiableStatuses(): array
    {
        return [
            'inspection' => [BookingStatus::Inspection],
            'quality check' => [BookingStatus::QualityCheck],
            'cancelled' => [BookingStatus::Cancelled],
            'scheduled' => [BookingStatus::Scheduled],
        ];
    }

    #[DataProvider('nonNotifiableStatuses')]
    public function test_transitioning_to_a_purely_internal_status_does_not_notify_the_customer(BookingStatus $status): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);

        $booking->transitionTo($status);

        $this->assertSame(0, $customer->notifications()->count());
    }

    public function test_no_notification_is_generated_merely_by_creating_a_booking(): void
    {
        $customer = User::factory()->create();
        $this->bookingFor($customer);

        $this->assertSame(0, $customer->notifications()->count());
    }

    public function test_quality_check_rework_loop_produces_a_second_repair_in_progress_notification(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);

        $booking->transitionTo(BookingStatus::Accepted);
        $booking->transitionTo(BookingStatus::BikeReceived);
        $booking->transitionTo(BookingStatus::Inspection);
        $booking->transitionTo(BookingStatus::AwaitingCustomerApproval);
        $booking->transitionTo(BookingStatus::RepairInProgress);
        $booking->transitionTo(BookingStatus::RepairCompleted);
        $booking->transitionTo(BookingStatus::QualityCheck);
        // QC fails, sent back to repair.
        $booking->transitionTo(BookingStatus::RepairInProgress);
        $booking->transitionTo(BookingStatus::RepairCompleted);
        $booking->transitionTo(BookingStatus::QualityCheck);

        $repairInProgressCount = $customer->notifications()
            ->where('data->status', BookingStatus::RepairInProgress->value)
            ->count();
        $repairCompletedCount = $customer->notifications()
            ->where('data->status', BookingStatus::RepairCompleted->value)
            ->count();

        $this->assertSame(2, $repairInProgressCount);
        $this->assertSame(2, $repairCompletedCount);
    }

    public function test_failed_transition_does_not_leave_a_stray_notification(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);

        // Attempting an invalid/guarded transition via the controller layer
        // (rather than calling transitionTo() directly) never even reaches
        // transitionTo() when the guard fails, so no notification exists.
        $this->actingAs($customer)
            ->post(route('customer.repairs.approve', $booking))
            ->assertSessionHas('error');

        $this->assertSame(0, $customer->notifications()->count());
    }

    public function test_customer_sees_their_own_notifications_newest_first(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);

        $booking->transitionTo(BookingStatus::Accepted);
        $this->travel(1)->minutes();
        $booking->transitionTo(BookingStatus::BikeReceived);

        $response = $this->actingAs($customer)->get(route('customer.notifications.index'));

        $response->assertOk()->assertSeeInOrder([
            "We've received your bicycle.",
            'Your repair booking has been accepted.',
        ]);
    }

    public function test_unread_and_read_notifications_render_distinctly(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);
        $booking->transitionTo(BookingStatus::Accepted);
        $booking->transitionTo(BookingStatus::BikeReceived);

        $customer->notifications()->where('data->status', BookingStatus::Accepted->value)->first()->markAsRead();

        $html = $this->actingAs($customer)
            ->get(route('customer.notifications.index'))
            ->assertOk()
            ->getContent();

        // The unread item carries the visual unread marker; the read one
        // does not — assert both states are actually distinguishable.
        $this->assertStringContainsString('bg-indigo-50', $html);
        $this->assertStringContainsString('bg-white border-gray-200', $html);
    }

    public function test_customer_cannot_view_another_customers_notification_in_their_own_list(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $otherBooking = $this->bookingFor($otherCustomer);
        $otherBooking->transitionTo(BookingStatus::Accepted);

        $response = $this->actingAs($customer)->get(route('customer.notifications.index'));

        $response->assertOk()->assertDontSee('Your repair booking has been accepted.');
    }

    public function test_customer_cannot_mark_another_customers_notification_as_read(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $otherBooking = $this->bookingFor($otherCustomer);
        $otherBooking->transitionTo(BookingStatus::Accepted);
        $notification = $otherCustomer->notifications()->first();

        $this->actingAs($customer)
            ->post(route('customer.notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertTrue($notification->fresh()->unread());
    }

    public function test_opening_a_notification_marks_it_read_and_redirects_to_the_correct_repair(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);
        $booking->transitionTo(BookingStatus::Accepted);
        $notification = $customer->notifications()->first();

        $this->assertTrue($notification->unread());

        $response = $this->actingAs($customer)
            ->post(route('customer.notifications.read', $notification->id));

        $response->assertRedirect(route('customer.repairs.show', $booking));
        $this->assertTrue($notification->fresh()->read());
    }

    public function test_notification_redirect_cannot_bypass_booking_authorization(): void
    {
        // Even if a notification's booking_id somehow pointed at a booking
        // the notifiable customer doesn't own, the destination route still
        // enforces BookingPolicy::view independently.
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $otherBooking = $this->bookingFor($otherCustomer);

        $customer->notify(new BookingStatusUpdated(
            $otherBooking->id,
            $otherBooking->reference_number,
            BookingStatus::Accepted,
            'Your repair booking has been accepted.',
        ));
        $notification = $customer->notifications()->first();

        $this->actingAs($customer)
            ->followingRedirects()
            ->post(route('customer.notifications.read', $notification->id))
            ->assertForbidden();
    }

    public function test_mark_all_as_read_only_affects_the_authenticated_customers_notifications(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);
        $booking->transitionTo(BookingStatus::Accepted);
        $booking->transitionTo(BookingStatus::BikeReceived);

        $otherCustomer = User::factory()->create();
        $otherBooking = $this->bookingFor($otherCustomer);
        $otherBooking->transitionTo(BookingStatus::Accepted);

        $this->actingAs($customer)->post(route('customer.notifications.read-all'));

        $this->assertSame(0, $customer->notifications()->where('read_at', null)->count());
        $this->assertSame(1, $otherCustomer->notifications()->where('read_at', null)->count());
    }

    public function test_unread_count_reflects_only_the_authenticated_customers_notifications(): void
    {
        $customer = User::factory()->create();
        $booking = $this->bookingFor($customer);
        $booking->transitionTo(BookingStatus::Accepted);
        $booking->transitionTo(BookingStatus::BikeReceived);
        $booking->transitionTo(BookingStatus::Inspection); // not notifiable

        $otherCustomer = User::factory()->create();
        $otherBooking = $this->bookingFor($otherCustomer);
        $otherBooking->transitionTo(BookingStatus::Accepted);
        $otherBooking->transitionTo(BookingStatus::BikeReceived);
        $otherBooking->transitionTo(BookingStatus::AwaitingCustomerApproval);

        $html = $this->actingAs($customer)
            ->get(route('customer.home'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/bg-red-600[^>]*>\s*2\s*</', $html);
    }
}
