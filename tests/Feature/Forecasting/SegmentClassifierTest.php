<?php

use App\Enums\ClientSegment;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Forecasting\SegmentClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Build a client whose only distinguishing feature is free text. */
function clientNamed(string $name, string $type = 'company', ?string $companyName = null, ?string $notes = null): User
{
    $user = User::factory()->create(['name' => $name]);

    UserProfile::create([
        'user_id'      => $user->id,
        'type'         => $type,
        'company_name' => $companyName,
        'notes'        => $notes,
    ]);

    return $user->fresh(['userProfile']);
}

it('classifies clients from Russian, Tajik and English names', function (string $name, ClientSegment $expected) {
    [$segment] = app(SegmentClassifier::class)->classify(clientNamed($name));

    expect($segment)->toBe($expected);
})->with([
    ['Школа №12', ClientSegment::School],
    ['Гимназия имени Рудаки', ClientSegment::School],
    ['Детский сад "Ромашка"', ClientSegment::School],
    ['Мактаби 45', ClientSegment::School],
    ['Донишгоҳи Миллӣ', ClientSegment::School],
    ['Kindergarten Sunshine', ClientSegment::School],
    ['Кафе Роҳат', ClientSegment::Horeca],
    ['Чойхонаи Саодат', ClientSegment::Horeca],
    ['Toshmatov Bakery', ClientSegment::Horeca],
    ['Ресторан Вароруд', ClientSegment::Horeca],
    ['Аптека №7', ClientSegment::Medical],
    ['Дорухонаи Шифо', ClientSegment::Medical],
    ['Поликлиника №3', ClientSegment::Medical],
    ['Фитнес клуб Олимп', ClientSegment::Fitness],
    ['Магазин Баракат', ClientSegment::Retail],
    ['Завод железобетонных изделий', ClientSegment::Industrial],
    ['Министерство образования', ClientSegment::Government],
    ['ООО Ориён Групп', ClientSegment::Office],
]);

it('prefers the specific keyword when a name carries two', function () {
    // "ООО" marks a company and "Кафе" marks a cafe. Rule order runs specific
    // before generic, so the cafe wins — otherwise every incorporated cafe,
    // school and clinic in the book would be forecast as an office.
    [$segment] = app(SegmentClassifier::class)->classify(clientNamed('ООО Кафе Дилшод'));

    expect($segment)->toBe(ClientSegment::Horeca);
});

it('reads the company name and notes, not just the client name', function () {
    [$school] = app(SegmentClassifier::class)->classify(
        clientNamed('Рахимов Ф.', companyName: 'Лицей №2'),
    );

    [$clinic] = app(SegmentClassifier::class)->classify(
        clientNamed('Саидов А.', notes: 'доставка в стоматологию на 2 этаже'),
    );

    expect($school)->toBe(ClientSegment::School)
        ->and($clinic)->toBe(ClientSegment::Medical);
});

it('treats an individual with an uninformative name as a household', function () {
    [$segment, $confidence] = app(SegmentClassifier::class)->classify(clientNamed('Иван Петров', type: 'individual'));

    expect($segment)->toBe(ClientSegment::Household)
        ->and($confidence)->toBeLessThan(0.7);
});

it('leaves an uninformative company unclassified rather than guessing', function () {
    // Unknown carries a flat seasonal curve, so this client gets no invented
    // summer or winter. A wrong segment is worse than no segment.
    [$segment, $confidence] = app(SegmentClassifier::class)->classify(clientNamed('Сомон-2010'));

    expect($segment)->toBe(ClientSegment::Unknown)
        ->and($confidence)->toBe(0.0)
        ->and(app(SegmentClassifier::class)->shouldAskAi($segment, $confidence))->toBeTrue();
});
