<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Token;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\UnencryptedToken;
use Rasuvaeff\Yii3Centrifugo\Token\SubscriptionTokenIssuer;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(SubscriptionTokenIssuer::class)]
final class SubscriptionTokenIssuerTest
{
    private Configuration $jwtConfig;
    private SubscriptionTokenIssuer $issuer;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->jwtConfig = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText('test-secret-key-at-least-32-chars-long'),
        );
        $this->issuer = new SubscriptionTokenIssuer(jwtConfig: $this->jwtConfig, defaultTtl: 3600);
    }

    public function issueContainsSubAndChannelClaims(): void
    {
        $token = $this->parse($this->issuer->issue(userId: '42', channel: 'news'));

        Assert::same($token->claims()->get('sub'), '42');
        Assert::same($token->claims()->get('channel'), 'news');
    }

    public function issueContainsExpClaim(): void
    {
        $token = $this->parse($this->issuer->issue(userId: '1', channel: 'ch'));

        Assert::instanceOf($token->claims()->get('exp'), \DateTimeImmutable::class);
    }

    public function issueWithInfoAddsInfoClaim(): void
    {
        $token = $this->parse($this->issuer->issue(userId: '1', channel: 'ch', info: ['rank' => 'admin']));

        Assert::same($token->claims()->get('info'), ['rank' => 'admin']);
    }

    public function issueWithoutInfoOmitsInfoClaim(): void
    {
        $token = $this->parse($this->issuer->issue(userId: '1', channel: 'ch'));

        Assert::false($token->claims()->has('info'));
    }

    public function customTtlOverridesDefault(): void
    {
        $before = new \DateTimeImmutable();
        $issuer = new SubscriptionTokenIssuer(jwtConfig: $this->jwtConfig, defaultTtl: 7200);
        $token = $this->parse($issuer->issue(userId: '1', channel: 'ch', ttl: 120));
        $exp = $token->claims()->get('exp');

        Assert::instanceOf($exp, \DateTimeImmutable::class);
        $diff = $exp->getTimestamp() - $before->getTimestamp();
        Assert::true($diff >= 119);
        Assert::true($diff <= 121);
    }

    public function defaultTtlIsAppliedWhenNullPassed(): void
    {
        $before = new \DateTimeImmutable();
        $issuer = new SubscriptionTokenIssuer(jwtConfig: $this->jwtConfig, defaultTtl: 1800);
        $token = $this->parse($issuer->issue(userId: '1', channel: 'ch'));
        $exp = $token->claims()->get('exp');

        Assert::instanceOf($exp, \DateTimeImmutable::class);
        $diff = $exp->getTimestamp() - $before->getTimestamp();
        Assert::true($diff >= 1799);
        Assert::true($diff <= 1801);
    }

    public function throwsOnEmptyUserId(): void
    {
        try {
            $this->issuer->issue(userId: '', channel: 'news');
            Assert::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('userId must not be empty');
        }
    }

    public function throwsOnEmptyChannel(): void
    {
        try {
            $this->issuer->issue(userId: '42', channel: '');
            Assert::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('channel must not be empty');
        }
    }

    private function parse(string $jwt): UnencryptedToken
    {
        $token = $this->jwtConfig->parser()->parse($jwt);
        Assert::instanceOf($token, UnencryptedToken::class);

        return $token;
    }
}
