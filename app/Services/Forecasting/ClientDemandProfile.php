<?php

namespace App\Services\Forecasting;

use App\Enums\ClientSegment;
use Illuminate\Support\Carbon;

/**
 * One client's learned ordering behaviour, ready to be projected onto dates.
 *
 * The model is a seasonally-modulated Poisson process. `baseRate` is the
 * client's de-seasonalised probability mass per day — what their ordering
 * frequency would be in a hypothetical month of average demand — and a
 * calendar date is turned into a real rate by multiplying by that month's
 * segment index. Expressing it this way is what makes the school case work:
 * the client's own habits and the calendar's effect are stored separately, so
 * a school that ordered weekly through the spring is not assumed to keep
 * ordering weekly through July.
 *
 * A Poisson rate also gives the probability of ordering on a given day for
 * free (1 - e^-lambda), which is what the route planner needs to decide
 * whether a predicted stop is worth driving to.
 */
class ClientDemandProfile
{
    /**
     * @param  array<int, array{product_id: int, name: mixed, qty: float, unit_price: float}>  $basket
     */
    public function __construct(
        public readonly int $clientId,
        public readonly string $clientName,
        public readonly ClientSegment $segment,
        public readonly float $baseRate,
        public readonly float $unitsPerOrder,
        public readonly float $unitsStdDev,
        public readonly array $basket,
        public readonly float $orderValue,
        public readonly ?Carbon $lastOrderAt,
        public readonly int $orderCount,
        public readonly string $confidence,
        public readonly string $trend,
        public readonly bool $churned,
        public readonly float $dataWeight,
    ) {}

    /**
     * Probability this client places an order on the given day.
     *
     * @param  float|null  $exposureSinceLastOrder  seasonal exposure accumulated
     *   since their last order, if the caller is walking dates in order. Pass it
     *   whenever it is available: without it the process is memoryless and will
     *   happily predict that a client who ordered yesterday orders again today.
     */
    public function probabilityOn(Carbon $date, SeasonalityService $seasonality, ?float $exposureSinceLastOrder = null): float
    {
        if ($this->churned || $this->baseRate <= 0) {
            return 0.0;
        }

        // A client cannot order before their first appearance; guards
        // backfilled horizons from inventing history.
        if ($this->lastOrderAt && $date->lt($this->lastOrderAt->copy()->startOfDay())) {
            return 0.0;
        }

        $lambda      = $this->baseRate * $seasonality->frequencyIndex($this->segment, $date);
        $probability = 1.0 - exp(-$lambda);

        $probability *= $this->renewalFactor($exposureSinceLastOrder);

        return min((float) config('forecasting.daily_probability_ceiling'), $probability);
    }

    /**
     * Suppression applied just after an order, ramping back to full.
     *
     * Pure Poisson is memoryless, which is wrong for restocking behaviour: a
     * client who took their fortnight's water yesterday is not equally likely
     * to order again today. `$exposure * baseRate` is how many orders the model
     * expected in the silence so far, so the factor is near zero immediately
     * after an order and effectively 1 by the time one order was due.
     *
     * This biases each client's total slightly low over a short window, which
     * is deliberate and left uncorrected here — ForecastAccuracyService
     * measures the resulting aggregate bias against real outcomes and removes
     * it, which is a better correction than a constant guessed in advance.
     */
    private function renewalFactor(?float $exposureSinceLastOrder): float
    {
        if ($exposureSinceLastOrder === null) {
            return 1.0;
        }

        $expected = max(0.0, $exposureSinceLastOrder * $this->baseRate);

        return 1.0 - exp(-2.0 * $expected);
    }

    /**
     * Expected units on the given day: probability of ordering times the size
     * of the order they would place, itself seasonally adjusted.
     */
    public function expectedUnitsOn(Carbon $date, SeasonalityService $seasonality, ?float $exposureSinceLastOrder = null): float
    {
        return $this->probabilityOn($date, $seasonality, $exposureSinceLastOrder)
            * $this->orderSizeOn($date, $seasonality);
    }

    public function expectedRevenueOn(Carbon $date, SeasonalityService $seasonality, ?float $exposureSinceLastOrder = null): float
    {
        return $this->probabilityOn($date, $seasonality, $exposureSinceLastOrder)
            * $this->orderSizeOn($date, $seasonality)
            * $this->pricePerUnit();
    }

    /**
     * Units in the order this client would place on the given day.
     */
    public function orderSizeOn(Carbon $date, SeasonalityService $seasonality): float
    {
        return $this->unitsPerOrder * $seasonality->sizeIndex($this->segment, $date);
    }

    public function pricePerUnit(): float
    {
        return $this->unitsPerOrder > 0 ? $this->orderValue / $this->unitsPerOrder : 0.0;
    }

    /**
     * Expected units of one product on the given day.
     */
    public function expectedProductUnitsOn(int $productId, Carbon $date, SeasonalityService $seasonality, ?float $exposureSinceLastOrder = null): float
    {
        $qty = 0.0;
        foreach ($this->basket as $line) {
            if ((int) $line['product_id'] === $productId) {
                $qty = (float) $line['qty'];
                break;
            }
        }

        if ($qty <= 0) {
            return 0.0;
        }

        return $this->probabilityOn($date, $seasonality, $exposureSinceLastOrder)
            * $qty
            * $seasonality->sizeIndex($this->segment, $date);
    }

    /**
     * Variance of units contributed by this client on one day.
     *
     * Two sources compound: whether they order at all (Bernoulli) and how much
     * they take when they do. Summing this across clients is what produces an
     * honest P10/P90 band instead of a point estimate dressed up as certainty.
     */
    public function unitVarianceOn(Carbon $date, SeasonalityService $seasonality, ?float $exposureSinceLastOrder = null): float
    {
        $p = $this->probabilityOn($date, $seasonality, $exposureSinceLastOrder);

        if ($p <= 0) {
            return 0.0;
        }

        $size = $this->orderSizeOn($date, $seasonality);

        return ($p * (1 - $p) * $size ** 2) + ($p * $this->unitsStdDev ** 2);
    }

    /**
     * The client's typical gap between orders, in days, at this date's season.
     * Presentation only — the forecast itself uses the rate, not the gap.
     */
    public function cadenceDaysOn(Carbon $date, SeasonalityService $seasonality): ?int
    {
        $lambda = $this->baseRate * $seasonality->frequencyIndex($this->segment, $date);

        return $lambda > 0 ? max(1, (int) round(1 / $lambda)) : null;
    }
}
