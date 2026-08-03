<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Pincode;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\TripWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransportRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_taxi_request_is_offline_server_estimated_and_idempotent(): void
    {
        [$business, $vehicle] = $this->transportBusiness('taxi');
        $payload = $this->payload($vehicle, 'trip-taxi-1', [
            'distance_km' => 10,
        ]);

        $response = $this->postJson("/api/businesses/{$business->slug}/trips", $payload)
            ->assertCreated()
            ->assertJsonPath('payment_mode', 'offline')
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('trip.fare_status', 'estimated')
            ->assertJsonPath('trip.payment_method', 'cash');

        $this->assertSame('200.00', Trip::findOrFail($response->json('trip.id'))->fare);

        $this->postJson("/api/businesses/{$business->slug}/trips", $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);
        $this->assertDatabaseCount('trips', 1);
    }

    public function test_exclusive_vehicle_rejects_overlapping_request_and_excess_passengers(): void
    {
        [$business, $vehicle] = $this->transportBusiness('taxi', ['seats' => 4]);
        $scheduled = now()->addDay()->startOfHour();

        $this->postJson("/api/businesses/{$business->slug}/trips", $this->payload($vehicle, 'exclusive-1', [
            'scheduled_at' => $scheduled->toIso8601String(),
        ]))->assertCreated();

        $this->postJson("/api/businesses/{$business->slug}/trips", $this->payload($vehicle, 'exclusive-2', [
            'scheduled_at' => $scheduled->copy()->addMinutes(30)->toIso8601String(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');

        $this->postJson("/api/businesses/{$business->slug}/trips", $this->payload($vehicle, 'exclusive-3', [
            'scheduled_at' => $scheduled->copy()->addHours(4)->toIso8601String(),
            'seats_required' => 5,
        ]))->assertUnprocessable()->assertJsonValidationErrors('seats_required');
    }

    public function test_shared_departure_enforces_remaining_seat_capacity(): void
    {
        [$business, $vehicle] = $this->transportBusiness('shared', ['seats' => 3]);
        $scheduled = now()->addDays(2)->startOfHour();

        $this->postJson("/api/businesses/{$business->slug}/trips", $this->payload($vehicle, 'shared-1', [
            'scheduled_at' => $scheduled->toIso8601String(),
            'seats_required' => 2,
        ]))->assertCreated();

        $this->postJson("/api/businesses/{$business->slug}/trips", $this->payload($vehicle, 'shared-2', [
            'scheduled_at' => $scheduled->toIso8601String(),
            'seats_required' => 2,
        ]))->assertUnprocessable()->assertJsonValidationErrors('seats_required');
    }

    public function test_goods_request_requires_description_checks_capacity_and_requests_quote(): void
    {
        [$business, $vehicle] = $this->transportBusiness('goods', [
            'capacity_value' => 1000,
            'capacity_unit' => 'kg',
        ]);
        $scheduled = now()->addDay()->toIso8601String();

        $this->postJson("/api/businesses/{$business->slug}/trips", $this->payload($vehicle, 'goods-1', [
            'scheduled_at' => $scheduled,
            'load_weight' => 500,
        ]))->assertUnprocessable()->assertJsonValidationErrors('load_description');

        $this->postJson("/api/businesses/{$business->slug}/trips", $this->payload($vehicle, 'goods-2', [
            'scheduled_at' => $scheduled,
            'load_description' => 'Furniture',
            'load_weight' => 1200,
        ]))->assertUnprocessable()->assertJsonValidationErrors('load_weight');

        $this->postJson("/api/businesses/{$business->slug}/trips", $this->payload($vehicle, 'goods-3', [
            'scheduled_at' => $scheduled,
            'load_description' => 'Furniture',
            'load_weight' => 800,
        ]))->assertCreated()->assertJsonPath('trip.fare_status', 'quote_required');
    }

    public function test_rental_requires_return_and_workflow_guards_status_quote_and_cash(): void
    {
        [$business, $vehicle] = $this->transportBusiness('rental');
        $scheduled = now()->addDay()->startOfHour();

        $this->postJson("/api/businesses/{$business->slug}/trips", $this->payload($vehicle, 'rental-1', [
            'scheduled_at' => $scheduled->toIso8601String(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('return_at');

        $response = $this->postJson("/api/businesses/{$business->slug}/trips", $this->payload($vehicle, 'rental-2', [
            'scheduled_at' => $scheduled->toIso8601String(),
            'return_at' => $scheduled->copy()->addHours(8)->toIso8601String(),
        ]))->assertCreated()->assertJsonPath('trip.fare_status', 'quote_required');

        $workflow = app(TripWorkflowService::class);
        $trip = Trip::findOrFail($response->json('trip.id'));
        try {
            $workflow->transition($trip, 'completed');
            $this->fail('Pending transport requests cannot skip confirmation.');
        } catch (ValidationException) {
            $this->assertSame('pending', $trip->fresh()->status);
        }

        $trip = $workflow->quote($trip, 2500, 'Includes driver');
        $this->assertSame('quoted', $trip->fare_status);
        $trip = $workflow->transition($trip, 'confirmed');
        $trip = $workflow->transition($trip, 'started');
        $this->assertSame('busy', $vehicle->fresh()->availability_status);
        $trip = $workflow->transition($trip, 'completed');
        $this->assertSame('available', $vehicle->fresh()->availability_status);
        $this->assertSame('paid', $workflow->markCashCollected($trip)->payment_status);
    }

    private function transportBusiness(string $mode, array $vehicleOverrides = []): array
    {
        Pincode::updateOrCreate(['pincode' => '795128'], [
            'locality' => 'Test Locality',
            'district' => 'Churachandpur',
            'state' => 'Manipur',
            'serviceable' => true,
        ]);
        $category = Category::where('slug', 'transport')->firstOrFail();
        $suffix = str()->lower(str()->random(8));
        $business = Business::create([
            'category_id' => $category->id,
            'name' => "Transport Business {$suffix}",
            'slug' => "transport-business-{$suffix}",
            'address' => 'Test address',
            'district' => 'Churachandpur',
            'state' => 'Manipur',
            'pincode' => '795128',
            'phone' => '9876543210',
            'is_active' => true,
            'enabled_modules' => [
                'catalog' => false,
                'orders' => false,
                'bookings' => false,
                'inventory' => false,
                'transport' => true,
                'turf' => false,
            ],
        ]);
        $vehicle = Vehicle::create(array_merge([
            'business_id' => $business->id,
            'name' => "Test Vehicle {$suffix}",
            'type' => $mode === 'goods' ? 'truck' : 'car',
            'service_mode' => $mode,
            'seats' => 4,
            'base_fare' => 100,
            'fare_per_km' => 10,
            'min_km' => 1,
            'availability_status' => 'available',
            'is_active' => true,
        ], $vehicleOverrides));

        return [$business, $vehicle];
    }

    private function payload(Vehicle $vehicle, string $reference, array $overrides = []): array
    {
        return array_merge([
            'vehicle_id' => $vehicle->id,
            'customer_name' => 'Transport Customer',
            'customer_phone' => '9000000000',
            'pickup_location' => 'Pickup point',
            'drop_location' => 'Drop point',
            'seats_required' => 1,
            'client_reference' => $reference,
        ], $overrides);
    }
}
