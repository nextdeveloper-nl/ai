<?php

namespace NextDeveloper\AI\Services\Llm;

/**
 * Turns speed measurements into the 1 to 5 "speed" rating shown on the models.
 *
 * One measurement is the output tokens per second of a short test answer: output tokens divided by the time spent
 * generating them (the total time minus the wait for the first token). The rating comes from the median of the last
 * measurements (one per day), so a single slow or fast run does not move it.
 */
class ModelSpeed
{
    /** Measurements kept per model (one per day). */
    public const KEEP_SAMPLES = 7;

    /**
     * Tokens per second needed for a rating above the previous one: below the first limit is 1, the first limit
     * up to the second is 2, and so on; the last limit and above is 5.
     *
     * @return array<int, float|int>
     */
    public static function thresholds(): array
    {
        try {
            $configured = config('ai.llm.speed_thresholds');
        } catch (\Throwable) {
            $configured = null; // outside the application: the defaults
        }

        return is_array($configured) && count($configured) === 4 ? array_values($configured) : [20, 50, 100, 200];
    }

    /**
     * @return float|null Null when the answer was too short or too quick to measure.
     */
    public static function tokensPerSecond(int $outputTokens, int $totalMs, ?int $firstTokenMs = null): ?float
    {
        $generationMs = $totalMs - ($firstTokenMs ?? 0);

        if ($outputTokens < 10 || $generationMs < 50) {
            return null;
        }

        return round($outputTokens / ($generationMs / 1000), 1);
    }

    /**
     * @param array<int, float|int> $values
     */
    public static function median(array $values): ?float
    {
        if (!$values) {
            return null;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 ? (float) $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    public static function rating(float $tokensPerSecond): int
    {
        $rating = 1;

        foreach (self::thresholds() as $limit) {
            if ($tokensPerSecond >= $limit) {
                $rating++;
            }
        }

        return $rating;
    }

    /**
     * Adds a measurement to the capabilities of a model and recomputes its rating. Other keys stay untouched.
     *
     * @param array<string, mixed> $capabilities
     * @return array<string, mixed>
     */
    public static function record(array $capabilities, float $tokensPerSecond, string $day): array
    {
        $samples = array_values(array_filter(
            (array) ($capabilities['speed_samples'] ?? []),
            fn ($sample) => is_array($sample) && ($sample['day'] ?? null) !== $day
        ));
        $samples[] = ['day' => $day, 'tps' => $tokensPerSecond];
        $samples = array_slice($samples, -self::KEEP_SAMPLES);

        $median = self::median(array_column($samples, 'tps'));

        return array_merge($capabilities, [
            'speed_samples' => $samples,
            'speed_tps'     => round($median, 1),
            'speed'         => self::rating($median),
            'speed_measured_at' => $day,
        ]);
    }
}
