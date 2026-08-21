<?php

namespace App\Services\Forecasting;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\UserAddress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds a day's delivery plan from committed orders plus confident predictions.
 *
 * Forecasting demand and planning delivery are usually treated as separate
 * problems, and that separation is where the margin goes. Knowing on Monday
 * that Wednesday will need 340 bottles across the eastern districts is what
 * lets a business send two full vans instead of three half-empty ones, and the
 * cost of a delivery run is almost entirely fixed per run rather than per
 * bottle. This class is the join between the two.
 *
 * The routing itself is a capacitated nearest-neighbour heuristic, not an
 * optimal VRP solve. That is a deliberate trade: optimal routing is NP-hard,
 * the inputs here (a probabilistic forecast, addresses geocoded to a building
 * rather than a door, traffic nobody modelled) carry far more error than the
 * gap between a good heuristic and a perfect solve, and a plan that renders in
 * under a second is one dispatchers will actually use every morning.
 *
 * Seeding each route from the stop FURTHEST from the depot is what stops the
 * classic failure of greedy routing: seed from the nearest instead and the
 * last route of the day becomes a scattering of remote leftovers.
 *
 * Stops without coordinates are never silently dropped. They are returned in
 * their own bucket, because a plan that quietly omits a third of the day's
 * deliveries is worse than no plan.
 */
class RoutePlanner
{
    public function __construct(
        private readonly DemandForecastService $forecast,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function plan(Carbon $date, array $options = []): array
    {
        $date = $date->copy()->startOfDay();

        $capacity  = (int) ($options['capacity'] ?? config('forecasting.delivery.vehicle_capacity_units'));
        $maxStops  = (int) ($options['max_stops'] ?? config('forecasting.delivery.max_stops_per_route'));
        $threshold = (float) ($options['min_probability'] ?? config('forecasting.delivery.min_probability_for_routing'));

        $stops = $this->committedStops($date)->concat($this->predictedStops($date, $threshold));

        [$located, $unlocated] = $stops->partition(fn (array $s) => $s['lat'] !== null && $s['lng'] !== null);

        $routes = $this->buildRoutes($located->values(), $capacity, $maxStops);

        $totalUnits = $stops->sum('units');

        return [
            'date'     => $date->toDateString(),
            'routes'   => $routes,
            'unlocated' => $unlocated->sortBy('client_name')->values()->all(),
            'summary'  => [
                'stops'            => $stops->count(),
                'committed_stops'  => $stops->where('type', 'committed')->count(),
                'predicted_stops'  => $stops->where('type', 'predicted')->count(),
                'unlocated_stops'  => $unlocated->count(),
                'units'            => round($totalUnits, 1),
                'routes'           => count($routes),
                'vehicle_capacity' => $capacity,
                // The number that decides cost per delivery. Two runs at 55%
                // is the same fuel and the same driver-day as one at 110%.
                'avg_load_pct'     => count($routes)
                    ? round(collect($routes)->avg(fn ($r) => $r['load_pct']), 1)
                    : 0.0,
                'total_distance_km' => round(collect($routes)->sum('distance_km'), 1),
                'geocoded_pct'      => $stops->count()
                    ? round(($located->count() / $stops->count()) * 100, 1)
                    : 0.0,
            ],
            'settings' => [
                'capacity'        => $capacity,
                'max_stops'       => $maxStops,
                'min_probability' => $threshold,
            ],
        ];
    }

    /**
     * Orders already placed and due on this date.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function committedStops(Carbon $date): Collection
    {
        $orders = Order::query()
            ->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled])
            ->whereDate('scheduled_delivery_at', $date->toDateString())
            ->with(['items:id,order_id,quantity,is_gift', 'client:id,name'])
            ->get();

        $fallback = $this->defaultAddresses($orders->pluck('user_id')->unique()->all());

        return $orders->map(function (Order $order) use ($fallback) {
            $units = $order->items->reject(fn ($i) => $i->is_gift)->sum('quantity');

            // An order carries its own pin once a courier or client has set
            // one; otherwise fall back to the client's default address.
            $lat = $order->lat ?? ($fallback[$order->user_id]->lat ?? null);
            $lng = $order->lng ?? ($fallback[$order->user_id]->lng ?? null);

            return [
                'type'        => 'committed',
                'order_id'    => $order->id,
                'client_id'   => $order->user_id,
                'client_name' => $order->client?->name ?? '—',
                'address'     => $order->delivery_address ?? ($fallback[$order->user_id]->address_line ?? null),
                'units'       => (float) $units,
                'probability' => 1.0,
                'lat'         => $lat !== null ? (float) $lat : null,
                'lng'         => $lng !== null ? (float) $lng : null,
            ];
        });
    }

    /**
     * Predicted orders confident enough to plan a vehicle around.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function predictedStops(Carbon $date, float $threshold): Collection
    {
        $result = $this->forecast->forecast($date, $date, ['include_clients' => true]);

        $predictions = collect($result['clients'])
            ->where('date', $date->toDateString())
            ->where('probability', '>=', $threshold);

        if ($predictions->isEmpty()) {
            return collect();
        }

        $addresses = $this->defaultAddresses($predictions->pluck('client_id')->unique()->all());

        return $predictions->map(fn (array $p) => [
            'type'        => 'predicted',
            'order_id'    => null,
            'client_id'   => $p['client_id'],
            'client_name' => $p['client_name'],
            'address'     => $addresses[$p['client_id']]->address_line ?? null,
            'units'       => (float) $p['units'],
            'probability' => (float) $p['probability'],
            'lat'         => isset($addresses[$p['client_id']]) ? $addresses[$p['client_id']]->lat : null,
            'lng'         => isset($addresses[$p['client_id']]) ? $addresses[$p['client_id']]->lng : null,
        ])->values();
    }

    /**
     * @param  int[]  $userIds
     * @return array<int, UserAddress>
     */
    private function defaultAddresses(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return UserAddress::query()
            ->whereIn('user_id', $userIds)
            ->orderByDesc('is_default')
            ->get()
            ->keyBy('user_id')
            ->all();
    }

    /**
     * Capacitated nearest-neighbour clustering.
     *
     * @param  Collection<int, array<string, mixed>>  $stops
     * @return array<int, array<string, mixed>>
     */
    private function buildRoutes(Collection $stops, int $capacity, int $maxStops): array
    {
        $depotLat = (float) config('forecasting.delivery.depot_lat');
        $depotLng = (float) config('forecasting.delivery.depot_lng');

        $remaining = $stops->all();
        $routes    = [];
        $guard     = 0;

        while (! empty($remaining) && $guard++ < 200) {
            // Seed with the furthest outstanding stop, so remote deliveries
            // anchor a route instead of being stranded on the last one.
            $seedKey = null;
            $seedDistance = -1.0;

            foreach ($remaining as $key => $stop) {
                $d = $this->distance($depotLat, $depotLng, $stop['lat'], $stop['lng']);
                if ($d > $seedDistance) {
                    $seedDistance = $d;
                    $seedKey      = $key;
                }
            }

            $route = [$remaining[$seedKey]];
            $load  = $remaining[$seedKey]['units'];
            unset($remaining[$seedKey]);

            // Grow the route by whichever remaining stop is closest to the one
            // just added, until the vehicle or the working day is full.
            while (! empty($remaining) && count($route) < $maxStops) {
                $last = end($route);

                $bestKey = null;
                $bestDistance = INF;

                foreach ($remaining as $key => $stop) {
                    if ($load + $stop['units'] > $capacity) {
                        continue;
                    }

                    $d = $this->distance($last['lat'], $last['lng'], $stop['lat'], $stop['lng']);

                    if ($d < $bestDistance) {
                        $bestDistance = $d;
                        $bestKey      = $key;
                    }
                }

                if ($bestKey === null) {
                    break;
                }

                $load += $remaining[$bestKey]['units'];
                $route[] = $remaining[$bestKey];
                unset($remaining[$bestKey]);
            }

            $routes[] = $this->describeRoute($route, $load, $capacity, count($routes) + 1);
        }

        return $routes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $stops
     * @return array<string, mixed>
     */
    private function describeRoute(array $stops, float $load, int $capacity, int $number): array
    {
        $depotLat = (float) config('forecasting.delivery.depot_lat');
        $depotLng = (float) config('forecasting.delivery.depot_lng');

        $distance = 0.0;
        $previousLat = $depotLat;
        $previousLng = $depotLng;

        foreach ($stops as $i => $stop) {
            $distance += $this->distance($previousLat, $previousLng, $stop['lat'], $stop['lng']);
            $stops[$i]['sequence'] = $i + 1;
            $previousLat = $stop['lat'];
            $previousLng = $stop['lng'];
        }

        // Back to the depot to reload; a one-way figure understates both fuel
        // and the driver's day.
        $distance += $this->distance($previousLat, $previousLng, $depotLat, $depotLng);

        $speed   = max(1.0, (float) config('forecasting.delivery.average_speed_kmh'));
        $service = (float) config('forecasting.delivery.service_minutes_per_stop');
        $minutes = ($distance / $speed) * 60 + count($stops) * $service;

        return [
            'number'      => $number,
            'stops'       => array_values($stops),
            'stop_count'  => count($stops),
            'units'       => round($load, 1),
            'load_pct'    => $capacity > 0 ? round(($load / $capacity) * 100, 1) : 0.0,
            'distance_km' => round($distance, 1),
            'duration_min' => (int) round($minutes),
            'predicted_stops' => count(array_filter($stops, fn ($s) => $s['type'] === 'predicted')),
        ];
    }

    /**
     * Great-circle distance in km, scaled to approximate road distance.
     */
    private function distance(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): float
    {
        if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
            return 0.0;
        }

        $earthRadius = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a))
            * (float) config('forecasting.delivery.road_factor');
    }
}
