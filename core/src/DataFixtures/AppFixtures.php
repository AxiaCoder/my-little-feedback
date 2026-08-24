<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Feedback;
use App\Entity\FeedbackStatus;
use App\Entity\FeedbackType;
use App\Entity\Product;
use App\Entity\SubmitterContext;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use RuntimeException;

/**
 * Development and test data only — never part of an installation.
 *
 * The default feedback types are deliberately absent: they are reference data,
 * seeded by the migration that creates their table (spec 01 §2.7). This fixture
 * looks them up, and fails loudly if they are gone — see {@see self::type()}.
 */
final class AppFixtures extends Fixture
{
    /** @var list<array{string, string}> slug, name */
    private const PRODUCTS = [
        ['my-little-library', 'My Little Library'],
        ['my-little-trivia', 'My Little Trivia'],
    ];

    /** @var array<string, FeedbackType> */
    private array $types = [];

    public function load(ObjectManager $manager): void
    {
        $products = [];

        foreach (self::PRODUCTS as [$slug, $name]) {
            $products[$slug] = new Product($slug, $name);
            $manager->persist($products[$slug]);
        }

        foreach ($this->samples() as [$productSlug, $typeSlug, $title, $message, $status, $submitter]) {
            $feedback = new Feedback(
                $this->product($products, $productSlug),
                $this->type($manager, $typeSlug),
                $message,
                $title,
                $submitter,
            );

            // The constructor owns the initial status, so anything else is a
            // transition — which is also what gives these rows an `updatedAt`.
            if (FeedbackStatus::New !== $status) {
                $feedback->setStatus($status);
            }

            $manager->persist($feedback);
        }

        $manager->flush();
    }

    /**
     * Sample feedback, spread over both products, all three types and five of
     * the six statuses, so the back-office listing has something to filter.
     *
     * One item carries no title on purpose: the widget does not require one, and
     * the listing has to render that case (spec 01 §2.3).
     *
     * @return list<array{string, string, string|null, string, FeedbackStatus, SubmitterContext|null}>
     */
    private function samples(): array
    {
        return [
            [
                'my-little-library', 'bug',
                'The cover image is stretched on a portrait phone',
                'On a 390px viewport the cover keeps its width and loses its aspect ratio. '
                    .'It looks fine as soon as the window is wider than about 500px.',
                FeedbackStatus::Triaged,
                new SubmitterContext('Ada', 'ada@example.com', 'https://example.com/library/shelves', 'en-GB'),
            ],
            [
                'my-little-library', 'idea',
                'Let me sort a shelf by the date I finished a book',
                'Sorting by title is the only option today. Finishing date is what I actually '
                    .'look for when I want to remember what I read last winter.',
                FeedbackStatus::Planned,
                new SubmitterContext(null, null, 'https://example.com/library/shelves/1', 'fr-FR'),
            ],
            [
                'my-little-library', 'question',
                null,
                'Is there a way to export my library as CSV? I could not find it in the settings.',
                FeedbackStatus::New,
                new SubmitterContext('Grace', null, null, 'en-US'),
            ],
            [
                'my-little-trivia', 'bug',
                'A question can be answered twice if I double-tap',
                'Tapping an answer twice quickly counts the second tap against the next question, '
                    .'which then shows as answered before it is displayed.',
                FeedbackStatus::InProgress,
                new SubmitterContext(null, 'player@example.com', 'https://example.com/trivia/play', 'fr-FR'),
            ],
            [
                'my-little-trivia', 'idea',
                'Daily challenge with a shared score',
                'One set of questions per day, the same for everyone, so a score is worth comparing.',
                FeedbackStatus::Done,
                new SubmitterContext('Kenji', 'kenji@example.com', 'https://example.com/trivia', 'ja-JP'),
            ],
            [
                'my-little-trivia', 'idea',
                'Add a multiplayer mode over the network',
                'Real-time rooms, matchmaking, a lobby, voice chat.',
                FeedbackStatus::Declined,
                null,
            ],
        ];
    }

    /**
     * A typo in the sample table should say so, rather than raise a warning on
     * an undefined key and persist a feedback item with no product.
     *
     * @param array<string, Product> $products
     */
    private function product(array $products, string $slug): Product
    {
        if (!isset($products[$slug])) {
            throw new RuntimeException(sprintf('Unknown product slug "%s" in the sample feedback.', $slug));
        }

        return $products[$slug];
    }

    /**
     * Reference data is not this fixture's to create, only to reference — so a
     * missing type means the database is in a state no installation produces.
     *
     * The likely cause is a purge: `doctrine:fixtures:load` empties every table
     * by default, `feedback_type` included, and re-running the migrations will
     * not put the rows back because the migration is already recorded as
     * executed. `composer fixtures` exists to keep that table out of the purge.
     */
    private function type(ObjectManager $manager, string $slug): FeedbackType
    {
        $type = $this->types[$slug] ?? $manager->getRepository(FeedbackType::class)->findOneBy(['slug' => $slug]);

        if (null === $type) {
            $message = sprintf(
                'The default feedback type "%s" is missing from the database. It is seeded by the migration, '
                .'not by this fixture. Load fixtures with `composer fixtures`, which excludes feedback_type '
                .'from the purge; if the rows are already gone, re-create the database and migrate again.',
                $slug,
            );

            throw new RuntimeException($message);
        }

        return $this->types[$slug] = $type;
    }
}
