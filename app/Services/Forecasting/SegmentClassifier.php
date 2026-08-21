<?php

namespace App\Services\Forecasting;

use App\Enums\ClientSegment;
use App\Enums\ClientType;
use App\Models\User;

/**
 * Deterministic, offline client segmentation from free text.
 *
 * This runs first and handles the easy majority ("Школа №12", "Кафе Роҳат")
 * for free, leaving only genuinely ambiguous names for the AI pass. Keeping a
 * rules layer in front of Gemini matters for three reasons: it costs nothing,
 * it is reproducible in tests, and it means segmentation still works with no
 * API key configured — which is the app's default state.
 *
 * Keywords cover Russian, Tajik and English because client records in this
 * business are entered in all three, often mixed inside one name.
 *
 * RULE ORDER IS SIGNIFICANT: first match wins, and the list runs specific to
 * generic. "ООО Кафе Дилшод" contains both a company-form marker and a cafe
 * marker; Horeca is checked before Office so it lands correctly.
 */
class SegmentClassifier
{
    /**
     * @return array<string, string[]> segment value => needles (lowercase)
     */
    private const RULES = [
        ClientSegment::School->value => [
            'школ', 'лицей', 'гимназ', 'детсад', 'детский сад', 'ясли', 'интернат',
            'колледж', 'техникум', 'университет', 'институт', 'академи', 'дошкол',
            'мактаб', 'кудакистон', 'кӯдакистон', 'богча', 'боғча', 'донишгоҳ', 'донишгох',
            'донишкада', 'мадраса', 'school', 'kindergarten', 'lyceum', 'college', 'university',
        ],
        ClientSegment::Medical->value => [
            'больниц', 'поликлин', 'клиник', 'медцентр', 'мед центр', 'медицин', 'аптек',
            'стоматолог', 'дантист', 'лаборатор', 'диагностик', 'роддом', 'санатор', 'госпитал',
            'дорухона', 'беморхона', 'шифохона', 'табобат',
            'clinic', 'hospital', 'pharmacy', 'medical', 'dental',
        ],
        ClientSegment::Horeca->value => [
            'кафе', 'ресторан', 'чайхана', 'чойхона', 'столов', 'бар ', 'паб', 'пиццер',
            'суши', 'фастфуд', 'фаст фуд', 'кофейн', 'кулинар', 'кондитер', 'пекарн',
            'шашлык', 'шашлыч', 'тандыр', 'банкет', 'гостиниц', 'отель', 'хостел', 'ошхона',
            'cafe', 'café', 'restaurant', 'coffee', 'hotel', 'hostel', 'pizzeria', 'canteen',
            'bakery', 'bistro', 'grill', 'catering', 'teahouse', 'chaikhana', 'chaykhana', 'kebab',
        ],
        ClientSegment::Fitness->value => [
            'спортзал', 'спорт зал', 'фитнес', 'тренаж', 'бассейн', 'йога', 'бокс',
            'борьб', 'стадион', 'варзиш', 'fitness', 'gym', 'sport', 'pool', 'crossfit',
        ],
        ClientSegment::Government->value => [
            'министерств', 'ведомств', 'хукумат', 'ҳукумат', 'администрац', 'мэрия',
            'комитет', 'налогов', 'таможн', 'прокуратур', 'посольств', 'консульств',
            'вазорат', 'раёсат', 'шаҳрдор', 'ministry', 'municipal', 'embassy',
        ],
        ClientSegment::Industrial->value => [
            'завод', 'фабрик', 'комбинат', 'производств', 'строит', 'стройк', 'монтаж',
            'карьер', 'шахт', 'рудник', 'бетон', 'кирпич', 'асфальт', 'нефт', 'цемент',
            'корхона', 'сохтмон', 'factory', 'plant', 'construction', 'industrial', 'workshop',
        ],
        ClientSegment::Retail->value => [
            'магазин', 'маркет', 'супермаркет', 'минимаркет', 'торгов', 'бутик', 'лавка',
            'дукон', 'дӯкон', 'базар', 'market', 'shop', 'store', 'retail',
        ],
        ClientSegment::Office->value => [
            'офис', 'ооо', 'оао', 'зао', ' чп', 'чдмм', 'ҷдмм', 'компани', 'корпорац',
            'банк', 'страхов', 'агентств', 'агенств', 'консалтинг', 'бухгалт', 'юридич',
            'нотариус', 'риэлт', 'турагент', 'туристич', 'логистик', 'экспедит',
            'филиал', 'представительств', 'ширкат', 'редакци', 'телеканал', 'радио',
            'office', 'ltd', 'llc', 'inc', 'bank', 'agency', 'consult', 'logistics', 'trading', 'corp',
        ],
    ];

    /**
     * Classify one client. Returns [segment, confidence, matchedNeedle|null].
     *
     * Confidence is deliberately coarse — it exists to decide whether the AI
     * pass should take a second look, not to be displayed as a percentage.
     *
     * @return array{0: ClientSegment, 1: float, 2: ?string}
     */
    public function classify(User $user): array
    {
        $haystack = $this->haystack($user);

        if ($haystack !== '') {
            foreach (self::RULES as $segment => $needles) {
                foreach ($needles as $needle) {
                    if (str_contains($haystack, $needle)) {
                        return [ClientSegment::from($segment), 0.9, $needle];
                    }
                }
            }
        }

        // No keyword hit. An individual is a household by default — that is
        // what an individual account overwhelmingly is in this business. A
        // company with an uninformative name stays Unknown (flat seasonality)
        // rather than being guessed into a curve it may not follow.
        $type = $user->userProfile?->type;

        if ($type === null || $type->is(ClientType::Individual)) {
            return [ClientSegment::Household, 0.6, null];
        }

        return [ClientSegment::Unknown, 0.0, null];
    }

    /**
     * Every piece of free text we hold about a client, lowercased into one
     * searchable string. Order/delivery addresses are included because a
     * client's name is often just a person while the address says "Школа №7".
     */
    public function haystack(User $user): string
    {
        $profile = $user->userProfile;

        $parts = [
            $user->name,
            $profile?->company_name,
            $profile?->notes,
            $profile?->address,
            $profile?->region,
        ];

        if ($user->relationLoaded('addresses')) {
            foreach ($user->addresses as $address) {
                $parts[] = $address->label;
                $parts[] = $address->address_line;
                $parts[] = $address->city;
            }
        }

        return mb_strtolower(trim(implode(' ', array_filter($parts))));
    }

    /**
     * Whether a rules verdict is weak enough to be worth an AI second opinion.
     */
    public function shouldAskAi(ClientSegment $segment, float $confidence): bool
    {
        return $segment === ClientSegment::Unknown || $confidence < 0.7;
    }
}
