<?php

use App\Enums\ClientSegment;

/**
 * Tuning knobs for the demand forecaster.
 *
 * Everything here is a modelling choice rather than a secret, so it lives in
 * config instead of .env. The defaults are set for a business with only a few
 * months of history: priors dominate, the learned-seasonality gate is closed,
 * and it opens on its own once `min_months_for_learning` months of data exist.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Seasonality
    |--------------------------------------------------------------------------
    */

    // A full seasonal cycle plus one month, so a month is never compared only
    // against itself. Below this, learned indices are not computed at all —
    // measuring "July is low" from a single July is not measurement.
    'min_months_for_learning' => 13,

    // Minimum orders observed in a (segment, month) cell before its observed
    // index is allowed to move the blended index at all.
    'min_orders_per_cell' => 4,

    // Hard clamp on any seasonal index, learned or manual. Stops one freak
    // month (a single bulk order) from turning into a permanent 6x multiplier.
    'index_floor'   => 0.05,
    'index_ceiling' => 3.00,

    /*
    |--------------------------------------------------------------------------
    | Per-client cadence model
    |--------------------------------------------------------------------------
    */

    // Half-life in days for exponential weighting of a client's history. A
    // 90-day half-life means orders from three months ago count half as much
    // as today's, so the model follows a client whose habits change.
    'recency_half_life_days' => 90,

    // Minimum non-cancelled orders before a client is modelled individually.
    // Below this the client falls back to their segment's average behaviour.
    'min_orders_for_client_model' => 3,

    // A client silent for this many multiples of their own cadence is treated
    // as churned and contributes no expected demand. Measured in expected
    // orders missed rather than elapsed days, so it stays lenient during a
    // segment's off-season: a school silent all summer has missed almost no
    // expected orders, while a household silent through July has missed many.
    'churn_cadence_multiple' => 4.0,

    // Probability floor/ceiling for a single client ordering on a single day.
    'daily_probability_ceiling' => 0.95,

    // How a seasonal index is split between "orders more often" and "orders
    // more per visit". The measured index is total units per client, i.e.
    // frequency x size, so the two exponents must sum to 1 or the season gets
    // counted twice. 0.7 says most of a summer swing shows up as extra visits,
    // which is what drives route planning; the remainder as bigger baskets.
    'seasonal_frequency_share' => 0.7,

    // Weight of the segment's average behaviour when a client has too little
    // history of their own. Occasional orderers stay in the forecast instead
    // of being dropped; they are simply pulled toward their segment's norm.
    'sparse_client_prior_orders' => 3.0,

    /*
    |--------------------------------------------------------------------------
    | Uncertainty band
    |--------------------------------------------------------------------------
    */

    // z-score for the reported interval. 1.2816 = P10/P90 (80% central).
    'band_z' => 1.2816,

    /*
    |--------------------------------------------------------------------------
    | Bias correction
    |--------------------------------------------------------------------------
    */

    // Apply the measured bias from forecast_snapshots back into new forecasts.
    'bias_correction_enabled' => true,

    // Minimum reconciled days before a measured bias is trusted enough to act on.
    'bias_min_observations' => 14,

    // Never let self-correction move a forecast by more than this factor, in
    // either direction. A runaway feedback loop is worse than a stale bias.
    'bias_max_adjustment' => 0.25,

    /*
    |--------------------------------------------------------------------------
    | Delivery planning
    |--------------------------------------------------------------------------
    */

    'delivery' => [
        // Bottles a single vehicle carries per run. Drives how many routes a
        // day needs and is the main lever on delivery cost per bottle.
        'vehicle_capacity_units' => 120,

        // Stops one courier can realistically service in a working day.
        'max_stops_per_route' => 25,

        // Minutes budgeted per stop, plus average travel speed, for ETAs.
        'service_minutes_per_stop' => 8,
        'average_speed_kmh'        => 22,

        // Predicted (not yet placed) orders only enter a route plan once the
        // model is at least this confident, so couriers are not sent to guesses.
        'min_probability_for_routing' => 0.55,

        // Depot the routes start and end at. Defaults to central Dushanbe;
        // set these once for the real warehouse or every distance estimate
        // will be measured from the wrong place.
        'depot_lat' => env('DEPOT_LAT', 38.5598),
        'depot_lng' => env('DEPOT_LNG', 68.7870),

        // Roads are not straight lines. Haversine distance is multiplied by
        // this to approximate driving distance in a city grid.
        'road_factor' => 1.35,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default horizon for the demand dashboard, in days.
    |--------------------------------------------------------------------------
    */
    'default_horizon_days' => 30,
    'max_horizon_days'     => 180,

    /*
    |--------------------------------------------------------------------------
    | AI assistance (Gemini)
    |--------------------------------------------------------------------------
    |
    | The AI never produces a number. It classifies clients into segments from
    | free text and writes the manager-facing narrative; the arithmetic stays
    | in PHP where it can be tested and backtested.
    */
    'ai' => [
        'enabled'         => env('FORECAST_AI_ENABLED', false),
        'classify_batch'  => 40,
        'fallback_segment' => ClientSegment::Unknown->value,
    ],
];
