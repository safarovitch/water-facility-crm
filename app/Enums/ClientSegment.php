<?php

namespace App\Enums;

/**
 * Demand segment of a client.
 *
 * `ClientType` (individual/company) is a billing/legal distinction and says
 * nothing about *when* a client orders — a school and a downtown office are
 * both "company" yet behave in opposite ways across a year. This enum is the
 * demand-side classification, and it exists because seasonality is only
 * meaningful per segment.
 *
 * Each case carries a 12-month prior curve of relative demand (index 0 =
 * January). The numbers below are raw weights, not indices: `priorIndices()`
 * normalises them so the year averages exactly 1.0, which keeps a segment's
 * seasonality orthogonal to its volume. That normalisation is what lets the
 * forecaster multiply a client's de-seasonalised base rate by a month index
 * without changing their annual total.
 *
 * The curves encode Tajikistan's calendar specifically:
 *  - summers are 35-40C, so household/horeca/retail demand peaks June-August;
 *  - the school year runs ~1 September to late May, so schools fall off a
 *    cliff in June and are effectively dark in July;
 *  - offices dip in August on staff leave even though the weather is hot.
 *
 * These are *priors*: the starting belief used while there is too little
 * history to measure the real curve. `SeasonalityService` blends them toward
 * observed data as history accumulates, and `Unknown` stays deliberately flat
 * so an unclassified client is never given an invented season.
 */
enum ClientSegment: string
{
    case Household  = 'household';
    case Office     = 'office';
    case School     = 'school';
    case Horeca     = 'horeca';
    case Retail     = 'retail';
    case Fitness    = 'fitness';
    case Medical    = 'medical';
    case Government = 'government';
    case Industrial = 'industrial';
    case Unknown    = 'unknown';

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Household  => 'Household',
            self::Office     => 'Office',
            self::School     => 'School / kindergarten',
            self::Horeca     => 'Cafe / restaurant',
            self::Retail     => 'Shop / retail',
            self::Fitness    => 'Gym / fitness',
            self::Medical    => 'Clinic / pharmacy',
            self::Government => 'Government office',
            self::Industrial => 'Industrial / construction',
            self::Unknown    => 'Unclassified',
        };
    }

    /**
     * Raw monthly demand weights, January first. Relative within a segment
     * only — magnitude is irrelevant because priorIndices() normalises.
     *
     * @return float[] 12 values
     */
    public function priorWeights(): array
    {
        return match ($this) {
            // Hot-weather driven: drinking water tracks temperature.
            self::Household  => [0.80, 0.80, 0.90, 1.00, 1.15, 1.35, 1.45, 1.40, 1.15, 0.95, 0.85, 0.80],

            // Heat raises consumption, but August leave empties the office.
            self::Office     => [0.95, 1.00, 1.00, 1.00, 1.05, 1.10, 1.05, 0.90, 1.05, 1.00, 0.95, 0.95],

            // Term time only. June winds down, July is dark, late August
            // restocks for 1 September, which is the year's biggest month.
            self::School     => [1.15, 1.20, 1.20, 1.20, 1.10, 0.15, 0.05, 0.25, 1.45, 1.30, 1.25, 1.10],

            // Strongest summer swing of all: terrace and chaikhana trade.
            self::Horeca     => [0.70, 0.75, 0.85, 1.00, 1.20, 1.45, 1.55, 1.50, 1.20, 0.95, 0.75, 0.75],

            self::Retail     => [0.80, 0.80, 0.90, 1.00, 1.15, 1.30, 1.40, 1.35, 1.10, 0.95, 0.85, 0.80],

            // Inverted: New Year sign-ups peak, memberships lapse over summer.
            self::Fitness    => [1.25, 1.15, 1.10, 1.05, 1.00, 0.85, 0.75, 0.75, 1.05, 1.10, 1.05, 0.90],

            // Near flat; staffed year-round, mild summer lift.
            self::Medical    => [0.95, 0.95, 1.00, 1.00, 1.00, 1.10, 1.10, 1.05, 1.00, 0.95, 0.95, 0.95],

            // Office-like with a deeper summer leave trough.
            self::Government => [1.00, 1.05, 1.05, 1.05, 1.05, 1.00, 0.90, 0.85, 1.05, 1.05, 1.00, 0.95],

            // Outdoor labour: follows the building season, not the thermometer.
            self::Industrial => [0.65, 0.70, 0.90, 1.10, 1.25, 1.40, 1.45, 1.40, 1.25, 1.05, 0.80, 0.65],

            // No assumption. An unclassified client gets no invented season.
            self::Unknown    => [1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00],
        };
    }

    /**
     * Prior curve normalised to a mean of exactly 1.0, January first.
     *
     * @return float[] 12 values
     */
    public function priorIndices(): array
    {
        $weights = $this->priorWeights();
        $mean    = array_sum($weights) / 12;

        if ($mean <= 0) {
            return array_fill(0, 12, 1.0);
        }

        return array_map(static fn (float $w): float => round($w / $mean, 6), $weights);
    }

    /**
     * Prior index for a calendar month (1-12).
     */
    public function priorIndexFor(int $month): float
    {
        return $this->priorIndices()[max(1, min(12, $month)) - 1];
    }

    /**
     * How aggressively a segment's history should be trusted over its prior.
     *
     * Segments with a sharp, calendar-locked pattern (schools) keep their
     * prior longer, because a handful of observations from a single term is
     * far more likely to mislead than to inform. Flat segments have little
     * prior worth defending, so data wins sooner. Used as the shrinkage
     * constant k in SeasonalityService::blend().
     */
    public function priorStrength(): float
    {
        return match ($this) {
            self::School     => 24.0,
            self::Horeca,
            self::Industrial => 16.0,
            self::Household,
            self::Retail,
            self::Fitness    => 12.0,
            self::Office,
            self::Government,
            self::Medical    => 8.0,
            self::Unknown    => 4.0,
        };
    }
}
