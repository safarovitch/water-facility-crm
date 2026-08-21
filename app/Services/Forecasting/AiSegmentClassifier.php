<?php

namespace App\Services\Forecasting;

use App\Enums\ClientSegment;
use App\Models\User;
use App\Services\Ai\GeminiClient;
use Illuminate\Support\Collection;

/**
 * Second-opinion segmentation for clients the keyword rules could not place.
 *
 * Gemini is used here and nowhere near the arithmetic. Reading "Хочагии
 * Дехконии Сомон" or "Бахористон 12/3, каб. 204" and inferring what kind of
 * organisation that is, is exactly the kind of fuzzy-text judgement a language
 * model is better at than a keyword list — and the output is a label from a
 * closed set, which is verifiable. A demand number, by contrast, would be
 * unverifiable and non-reproducible, so it stays in SeasonalityService.
 *
 * Clients are sent in batches in a single prompt to keep the call count (and
 * cost) proportional to the client base rather than to the number of clients.
 * Any unparseable or out-of-vocabulary answer degrades to Unknown, never to a
 * guess, and the caller keeps the rules verdict when this returns nothing.
 */
class AiSegmentClassifier
{
    public function __construct(
        private readonly GeminiClient $gemini,
        private readonly SegmentClassifier $rules,
    ) {}

    public function available(): bool
    {
        return $this->gemini->enabled();
    }

    /**
     * Classify a batch of users.
     *
     * @param  Collection<int, User>  $users
     * @return array<int, array{segment: ClientSegment, confidence: float}> keyed by user id
     */
    public function classifyBatch(Collection $users): array
    {
        if (! $this->available() || $users->isEmpty()) {
            return [];
        }

        $rows = $users->map(fn (User $user) => [
            'id'   => $user->id,
            'text' => mb_substr($this->rules->haystack($user), 0, 300),
        ])->values();

        $decoded = $this->gemini->generateJson(
            $this->prompt($rows->all()),
            $this->systemInstruction(),
        );

        if ($decoded === null) {
            return [];
        }

        // Accept either a bare array or {"results": [...]}, since models vary
        // on whether they wrap a list in an object.
        $results = $decoded['results'] ?? $decoded;

        if (! is_array($results)) {
            return [];
        }

        $allowed = $users->pluck('id')->all();
        $out     = [];

        foreach ($results as $row) {
            if (! is_array($row) || ! isset($row['id'], $row['segment'])) {
                continue;
            }

            $id = (int) $row['id'];

            // Guard against the model inventing ids that were never sent.
            if (! in_array($id, $allowed, true)) {
                continue;
            }

            $segment = ClientSegment::tryFrom((string) $row['segment']);

            if ($segment === null) {
                continue;
            }

            $confidence = isset($row['confidence']) ? (float) $row['confidence'] : 0.7;

            $out[$id] = [
                'segment'    => $segment,
                'confidence' => max(0.0, min(1.0, $confidence)),
            ];
        }

        return $out;
    }

    private function systemInstruction(): string
    {
        return 'You classify customers of a bottled-water delivery company in Tajikistan into demand segments. '
            . 'Records are written in Russian, Tajik or English, often mixed and abbreviated. '
            . 'Reply with JSON only. Never invent an id that was not given to you. '
            . 'If a record is a personal name, an unlabelled street address, or otherwise gives no clue about '
            . 'the type of organisation, answer "unknown" rather than guessing — a wrong segment is worse than none.';
    }

    /**
     * @param  array<int, array{id: int, text: string}>  $rows
     */
    private function prompt(array $rows): string
    {
        $vocabulary = collect(ClientSegment::cases())
            ->map(fn (ClientSegment $s) => "- {$s->value}: {$s->label()}")
            ->implode("\n");

        $records = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
        Classify each customer record into exactly one segment.

        Allowed segments:
        {$vocabulary}

        Guidance:
        - "household" is a private home / individual person.
        - "office" is any commercial organisation whose staff drink the water at desks.
        - "school" covers schools, kindergartens, lyceums, colleges and universities.
        - "horeca" covers cafes, restaurants, chaikhanas, canteens, bakeries and hotels.
        - "industrial" covers factories, workshops and construction sites.
        - Use "unknown" when the text does not identify the kind of organisation.

        Records:
        {$records}

        Return JSON of this exact shape, one entry per input id:
        {"results":[{"id":123,"segment":"school","confidence":0.9}]}
        PROMPT;
    }
}
