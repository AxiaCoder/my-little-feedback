<?php

declare(strict_types=1);

namespace App\Dto;

use App\Validator\ActiveFeedbackTypeSlug;
use App\Validator\ExistingProductSlug;
use stdClass;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The `POST /api/feedback` payload (spec 01 §3.1).
 *
 * Properties are `mixed` rather than `?string` on purpose. A caller who sends
 * `"message": 42` deserves a violation, not a TypeError, and typing the
 * properties would move that failure out of the validator and into binding,
 * where there is no envelope to report it in. Every rule is therefore wrapped in
 * `Sequentially`, so a length check never runs on an integer.
 */
final class CreateFeedbackRequest
{
    /**
     * Refused rather than ignored, because the server sets them itself (§3.2).
     * Quietly dropping a field the caller believed in is how integrations rot.
     *
     * Only the fields §3.2 actually names are listed. Any other unknown key is
     * still ignored, which is a looser contract than this one and deliberately
     * left as the spec wrote it.
     */
    private const SERVER_OWNED = ['status', 'createdAt'];

    private const SERVER_OWNED_MESSAGE = 'This field is set by the server and cannot be sent.';

    #[Assert\Sequentially([
        new Assert\NotNull(message: 'This field is required.'),
        new Assert\Type('string'),
        new Assert\NotBlank(),
        new Assert\Length(max: 60),
        new ExistingProductSlug(),
    ])]
    public mixed $product = null;

    #[Assert\Sequentially([
        new Assert\NotNull(message: 'This field is required.'),
        new Assert\Type('string'),
        new Assert\NotBlank(),
        new Assert\Length(max: 30),
        new ActiveFeedbackTypeSlug(),
    ])]
    public mixed $type = null;

    #[Assert\Sequentially([
        new Assert\Type('string'),
        new Assert\Length(max: 160),
    ])]
    public mixed $title = null;

    #[Assert\Sequentially([
        new Assert\NotNull(message: 'This field is required.'),
        new Assert\Type('string'),
        new Assert\Length(min: 10, max: 5000),
    ])]
    public mixed $message = null;

    #[Assert\Valid]
    public ?SubmitterPayload $submitter = null;

    #[Assert\Valid]
    public ?ContextPayload $context = null;

    /**
     * Violations found while binding, before the validator ever runs: a
     * server-owned field in the payload, or a nested value that is not an
     * object. They share the envelope with the validator's own (§3.3), because
     * a caller should not have to fix one class of error to discover the next.
     *
     * @var list<array{field: string, message: string}>
     */
    public array $bindingViolations = [];

    /**
     * Binds a decoded JSON object. Never throws: everything wrong with the
     * payload comes back as a violation, so the caller gets the whole list.
     */
    public static function fromBody(stdClass $body): self
    {
        $request = new self();
        $payload = (array) $body;

        foreach (self::SERVER_OWNED as $field) {
            if (array_key_exists($field, $payload)) {
                $request->bindingViolations[] = ['field' => $field, 'message' => self::SERVER_OWNED_MESSAGE];
            }
        }

        $request->product = self::text($payload, 'product', collapseEmpty: false);
        $request->type = self::text($payload, 'type', collapseEmpty: false);
        $request->title = self::text($payload, 'title');
        $request->message = self::text($payload, 'message', collapseEmpty: false);

        $submitter = $request->nested($payload, 'submitter');
        if (null !== $submitter) {
            $fields = (array) $submitter;

            // Same rule as `status`, one level down: the user agent is read from
            // the request headers and never from the body (§3.2).
            if (array_key_exists('userAgent', $fields)) {
                $request->bindingViolations[] = [
                    'field' => 'submitter.userAgent',
                    'message' => self::SERVER_OWNED_MESSAGE,
                ];
            }

            $request->submitter = new SubmitterPayload();
            $request->submitter->name = self::text($fields, 'name');
            $request->submitter->email = self::text($fields, 'email');
        }

        $context = $request->nested($payload, 'context');
        if (null !== $context) {
            $fields = (array) $context;

            $request->context = new ContextPayload();
            $request->context->sourceUrl = self::text($fields, 'sourceUrl');
            $request->context->locale = self::text($fields, 'locale');
        }

        return $request;
    }

    /**
     * Trims strings and leaves everything else alone for the validator to
     * reject — the 10–5000 check on `message` counts characters after trimming
     * (spec 01 §3.1), and the same courtesy costs nothing on the other fields.
     *
     * On an optional field an empty string collapses to `null`, so `"title": ""`
     * and an absent title reach the database identically rather than one of them
     * arriving as an empty string. Required fields keep theirs: a caller who
     * sent `"product": ""` should be told the value is blank, not that they
     * forgot the field.
     *
     * @param array<string, mixed> $payload
     */
    private static function text(array $payload, string $field, bool $collapseEmpty = true): mixed
    {
        $value = $payload[$field] ?? null;

        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return '' === $value && $collapseEmpty ? null : $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function nested(array $payload, string $field): ?stdClass
    {
        $value = $payload[$field] ?? null;

        if (null === $value) {
            return null;
        }

        if (!$value instanceof stdClass) {
            $this->bindingViolations[] = ['field' => $field, 'message' => 'This value should be an object.'];

            return null;
        }

        return $value;
    }
}
