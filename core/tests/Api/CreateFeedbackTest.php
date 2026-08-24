<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Feedback;
use App\Entity\FeedbackStatus;
use App\Entity\FeedbackType;
use App\Entity\Product;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * `POST /api/feedback`, spec 01 §3.
 *
 * The suite's schema comes from the migrations and arrives empty (§2.7), so
 * each test builds the product and the types it needs.
 */
final class CreateFeedbackTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $manager = $this->manager();
        (new ORMPurger($manager))->purge();

        $manager->persist(new Product('my-little-library', 'My Little Library'));
        $manager->persist(new FeedbackType('bug', 'Bug', 0));
        $manager->persist(new FeedbackType('typo', 'Typo', 9, isActive: false));
        $manager->flush();
        $manager->clear();
    }

    public function testItCreatesAFeedbackItem(): void
    {
        $this->post(<<<'JSON'
            {
              "product": "my-little-library",
              "type": "bug",
              "title": "Search returns nothing",
              "message": "Searching for an author I know is in the library returns an empty list.",
              "submitter": { "name": "Ada", "email": "ada@example.com" },
              "context": { "sourceUrl": "https://example.com/search?q=lovelace", "locale": "fr-FR" }
            }
            JSON);

        self::assertResponseStatusCodeSame(201);

        $body = $this->json();

        // The whole §5 shape, key by key. Asserting field by field would let a
        // key appear — `submitter.userAgent`, say — without a test noticing.
        self::assertSame(
            ['id', 'product', 'type', 'status', 'title', 'message', 'submitter', 'context', 'createdAt'],
            array_keys($body),
        );
        self::assertSame(['slug' => 'my-little-library', 'name' => 'My Little Library'], $body['product']);
        self::assertSame(['slug' => 'bug', 'label' => 'Bug'], $body['type']);
        self::assertSame('new', $body['status']);
        self::assertSame('Search returns nothing', $body['title']);
        self::assertSame(['name' => 'Ada', 'email' => 'ada@example.com'], $body['submitter']);
        self::assertSame(
            ['sourceUrl' => 'https://example.com/search?q=lovelace', 'locale' => 'fr-FR'],
            $body['context'],
        );

        self::assertResponseHasHeader('Location');
        self::assertStringEndsWith(
            '/api/feedback/'.$body['id'],
            (string) $this->client->getResponse()->headers->get('Location'),
        );
    }

    /**
     * Stored for spam triage, never displayed, and read from the header rather
     * than the body (§3.2 and §5).
     */
    public function testTheUserAgentIsTakenFromTheHeaderAndKeptOutOfTheResponse(): void
    {
        $this->post($this->minimalPayload(), ['HTTP_USER_AGENT' => 'mlf-test/1.0']);

        self::assertResponseStatusCodeSame(201);
        self::assertArrayNotHasKey('userAgent', $this->json()['submitter']);

        $feedback = $this->manager()->getRepository(Feedback::class)->findOneBy([]);

        self::assertNotNull($feedback);
        self::assertSame('mlf-test/1.0', $feedback->getSubmitter()->getUserAgent());
        self::assertSame(FeedbackStatus::New, $feedback->getStatus());
    }

    public function testAbsentOptionalValuesAreSerializedAsNullRatherThanOmitted(): void
    {
        $this->post($this->minimalPayload());

        self::assertResponseStatusCodeSame(201);

        $body = $this->json();

        self::assertNull($body['title']);
        self::assertSame(['name' => null, 'email' => null], $body['submitter']);
        self::assertSame(['sourceUrl' => null, 'locale' => null], $body['context']);
    }

    public function testAMalformedBodyIsRejectedBeforeTheValidator(): void
    {
        $this->post('{not json');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(
            ['error' => 'malformed_json', 'message' => 'Request body is not valid JSON.'],
            $this->json(),
        );
    }

    /**
     * `[]` parses. It is still not a payload, and telling the caller their JSON
     * is fine while refusing every field would be worse than one clear 400.
     */
    public function testAJsonArrayIsNotAPayload(): void
    {
        $this->post('[]');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('malformed_json', $this->json()['error']);
    }

    public function testEveryViolationIsReportedNotJustTheFirst(): void
    {
        $this->post(<<<'JSON'
            {
              "product": "does-not-exist",
              "type": "does-not-exist",
              "message": "short",
              "context": { "sourceUrl": "not-a-url" }
            }
            JSON);

        self::assertResponseStatusCodeSame(422);

        $body = $this->json();

        self::assertSame('validation_failed', $body['error']);
        self::assertSame(
            ['context.sourceUrl', 'message', 'product', 'type'],
            array_column($body['violations'], 'field'),
        );
        self::assertSame('No product matches this identifier.', $body['violations'][2]['message']);
    }

    /**
     * Server-owned, so a caller who sends it is told, never silently ignored
     * (§3.1). Quietly dropping a field the caller believed in is how
     * integrations rot.
     */
    public function testAStatusInThePayloadIsRefused(): void
    {
        $this->post(<<<'JSON'
            {
              "product": "my-little-library",
              "type": "bug",
              "message": "This message is long enough to pass validation.",
              "status": "done"
            }
            JSON);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            [['field' => 'status', 'message' => 'This field is set by the server and cannot be sent.']],
            $this->json()['violations'],
        );
        self::assertCount(0, $this->manager()->getRepository(Feedback::class)->findAll());
    }

    public function testAUserAgentInThePayloadIsRefusedToo(): void
    {
        $this->post(<<<'JSON'
            {
              "product": "my-little-library",
              "type": "bug",
              "message": "This message is long enough to pass validation.",
              "submitter": { "userAgent": "forged/1.0" }
            }
            JSON);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('submitter.userAgent', $this->json()['violations'][0]['field']);
    }

    /**
     * The route exists; the value inside it is what is wrong. Uniformity beats
     * protocol purism here — one error shape to parse (§3.3).
     */
    public function testAnUnknownProductIsAViolationAndNotA404(): void
    {
        $this->post(<<<'JSON'
            {
              "product": "no-such-product",
              "type": "bug",
              "message": "This message is long enough to pass validation."
            }
            JSON);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            [['field' => 'product', 'message' => 'No product matches this identifier.']],
            $this->json()['violations'],
        );
    }

    /**
     * Creation takes active types only, unlike the listing filter (§4.1):
     * retiring a type stops new feedback without hiding the old.
     */
    public function testAnInactiveTypeIsRefused(): void
    {
        $this->post(<<<'JSON'
            {
              "product": "my-little-library",
              "type": "typo",
              "message": "This message is long enough to pass validation."
            }
            JSON);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            [['field' => 'type', 'message' => 'No active feedback type matches this identifier.']],
            $this->json()['violations'],
        );
    }

    /**
     * 10–5000 characters **after trimming** (§3.1), so padding is not a way in.
     */
    public function testTheMessageLengthIsCheckedAfterTrimming(): void
    {
        $this->post(<<<'JSON'
            {
              "product": "my-little-library",
              "type": "bug",
              "message": "            short            "
            }
            JSON);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('message', $this->json()['violations'][0]['field']);
    }

    private function minimalPayload(): string
    {
        return <<<'JSON'
            {
              "product": "my-little-library",
              "type": "bug",
              "message": "This message is long enough to pass validation."
            }
            JSON;
    }

    /**
     * @param array<string, string> $server
     */
    private function post(string $json, array $server = []): void
    {
        $this->client->request(
            'POST',
            '/api/feedback',
            server: ['CONTENT_TYPE' => 'application/json', ...$server],
            content: $json,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($body);

        return $body;
    }

    private function manager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
