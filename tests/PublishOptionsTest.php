<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests;

use Rasuvaeff\PropertyTesting\Classify;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Yii3Centrifugo\PublishOptions;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(PublishOptions::class)]
final class PublishOptionsTest
{
    public function defaultsProduceAnEmptyPayload(): void
    {
        Assert::same((new PublishOptions())->toPayload(), []);
    }

    public function everyFieldIsSentUnderItsApiName(): void
    {
        $payload = new PublishOptions(
            idempotencyKey: 'order-42',
            skipHistory: true,
            tags: ['kind' => 'order'],
            delta: true,
            version: 7,
            versionEpoch: 'e1',
        );

        Assert::same(
            json_encode($payload->toPayload(), JSON_THROW_ON_ERROR),
            '{"idempotency_key":"order-42","skip_history":true,"tags":{"kind":"order"},"delta":true,"version":7,"version_epoch":"e1"}',
        );
    }

    public function numericTagNamesStillEncodeAsAJsonObject(): void
    {
        $options = new PublishOptions(tags: ['0' => 'a', '1' => 'b']);

        Assert::same(json_encode($options->toPayload(), JSON_THROW_ON_ERROR), '{"tags":{"0":"a","1":"b"}}');
    }

    /**
     * @return iterable<string, array{\Closure(): PublishOptions, string}>
     */
    public static function invalidOptions(): iterable
    {
        yield 'empty idempotency key' => [static fn(): PublishOptions => new PublishOptions(idempotencyKey: ''), 'idempotencyKey must not be empty'];
        yield 'zero version' => [static fn(): PublishOptions => new PublishOptions(version: 0), 'version must be positive'];
        yield 'negative version' => [static fn(): PublishOptions => new PublishOptions(version: -1), 'version must be positive'];
        yield 'empty version epoch' => [static fn(): PublishOptions => new PublishOptions(versionEpoch: ''), 'versionEpoch must not be empty'];
        /** @psalm-var array<string, string> $tags */
        $tags = ['n' => 1];
        yield 'non-string tag' => [static fn(): PublishOptions => new PublishOptions(tags: $tags), 'Tag "n" must be a string, int given'];
    }

    /**
     * @param \Closure(): PublishOptions $build
     */
    #[DataProvider('invalidOptions')]
    public function invalidOptionsAreRejected(\Closure $build, string $message): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessage($message);

        $build();
    }

    public function versionOneIsAccepted(): void
    {
        Assert::same((new PublishOptions(version: 1))->toPayload(), ['version' => 1]);
    }

    /**
     * Whatever combination is set, the encoded request carries exactly the
     * non-default fields, each under its v6 API name, and tags stay a JSON
     * object.
     *
     * @param array<string, string> $tags
     */
    #[Property(runs: 300)]
    public function payloadRoundTripsThroughJsonWithOnlyNonDefaultFields(
        ?string $idempotencyKey,
        bool $skipHistory,
        array $tags,
        bool $delta,
        ?int $version,
        ?string $versionEpoch,
    ): void {
        $options = new PublishOptions(
            idempotencyKey: $idempotencyKey,
            skipHistory: $skipHistory,
            tags: $tags,
            delta: $delta,
            version: $version,
            versionEpoch: $versionEpoch,
        );

        $expected = array_filter(
            [
                'idempotency_key' => $idempotencyKey,
                'skip_history' => $skipHistory ?: null,
                'tags' => $tags === [] ? null : $tags,
                'delta' => $delta ?: null,
                'version' => $version,
                'version_epoch' => $versionEpoch,
            ],
            static fn(mixed $value): bool => $value !== null,
        );

        Classify::cover($tags !== [], 'with tags', 20.0);

        $json = json_encode($options->toPayload(), JSON_THROW_ON_ERROR);

        Assert::same(json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR), $expected);
        Assert::same(str_contains($json, '"tags":['), expected: false);
    }

    /**
     * @return array<string, \Rasuvaeff\PropertyTesting\ArbitraryInterface>
     */
    public static function payloadRoundTripsThroughJsonWithOnlyNonDefaultFieldsGenerators(): array
    {
        $nonEmpty = Gen::stringFrom('abcXYZ019-_:', minLength: 1, maxLength: 20);

        return [
            'idempotencyKey' => Gen::nullable($nonEmpty),
            'skipHistory' => Gen::bool(),
            'tags' => Gen::dictOf(Gen::stringFrom('abc019', minLength: 1, maxLength: 5), Gen::stringFrom('abcXYZ ', minLength: 0, maxLength: 10), maxSize: 4),
            'delta' => Gen::bool(),
            'version' => Gen::nullable(Gen::intPositive()),
            'versionEpoch' => Gen::nullable($nonEmpty),
        ];
    }
}
