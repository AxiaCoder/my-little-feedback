<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\CreateFeedbackRequest;
use App\Dto\FeedbackResponse;
use App\Entity\Feedback;
use App\Entity\SubmitterContext;
use App\Http\ApiError;
use App\Repository\FeedbackTypeRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use LogicException;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * `POST /api/feedback` — creates a feedback item (spec 01 §3).
 *
 * Called by `ingest` in the finished architecture, by `curl` today. It is
 * unauthenticated and bound to localhost through Compose, which is acceptable
 * for milestone 1 and blocks any deployment (§7).
 */
final class CreateFeedbackController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly ProductRepository $products,
        private readonly FeedbackTypeRepository $types,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/feedback', name: 'api_feedback_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $body = json_decode($request->getContent(), false, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ApiError::malformedJson();
        }

        // A JSON array, string or number parses but is not a payload. Decoding
        // to objects rather than associative arrays is what makes this
        // distinguishable at all: with `associative: true`, `{}` and `[]` both
        // come back as an empty PHP array.
        if (!$body instanceof stdClass) {
            return ApiError::malformedJson();
        }

        $payload = CreateFeedbackRequest::fromBody($body);

        $violations = $payload->bindingViolations;
        foreach ($this->validator->validate($payload) as $violation) {
            $violations[] = [
                'field' => $violation->getPropertyPath(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        if ([] !== $violations) {
            return ApiError::validationFailed($violations);
        }

        $productSlug = $payload->product;
        $typeSlug = $payload->type;
        $message = $payload->message;

        if (!is_string($productSlug) || !is_string($typeSlug) || !is_string($message)) {
            // Unreachable: the validator refuses anything else. Stated rather
            // than assumed, because the payload's properties are `mixed` by
            // design and the type system cannot state it here.
            throw new LogicException('A validated payload carried a non-string required field.');
        }

        $product = $this->products->findOneBy(['slug' => $productSlug]);
        $type = $this->types->findOneBy(['slug' => $typeSlug, 'isActive' => true]);

        if (null === $product || null === $type) {
            // Both existed a query ago. Reaching this means one was deleted or
            // deactivated in between, and answering with the same envelope keeps
            // the caller on one error shape instead of inventing a 500 for a
            // race they can do nothing about.
            return ApiError::validationFailed(array_values(array_filter([
                null === $product ? ['field' => 'product', 'message' => 'No product matches this identifier.'] : null,
                null === $type ? ['field' => 'type', 'message' => 'No active feedback type matches this identifier.'] : null,
            ])));
        }

        $feedback = new Feedback(
            $product,
            $type,
            $message,
            self::text($payload->title),
            new SubmitterContext(
                self::text($payload->submitter?->name),
                self::text($payload->submitter?->email),
                self::text($payload->context?->sourceUrl),
                self::text($payload->context?->locale),
                // The header, never the body (§3.2). `ingest` will forward the
                // original one rather than inventing a body field.
                $request->headers->get('User-Agent'),
            ),
        );

        $this->entityManager->persist($feedback);
        $this->entityManager->flush();

        return new JsonResponse(
            FeedbackResponse::fromEntity($feedback),
            Response::HTTP_CREATED,
            // Built by hand because `GET /api/feedback/{id}` does not exist yet:
            // §4 specifies a collection and no item route, so there is nothing to
            // generate this from. The header is what §3.3 asks for, and the URL
            // it names answers 404 until that route lands.
            ['Location' => $request->getSchemeAndHttpHost().'/api/feedback/'.$feedback->getId()],
        );
    }

    /**
     * Narrows a validated optional field. Anything that is not a string was
     * already refused, so this only ever turns `null` into `null`.
     */
    private static function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
