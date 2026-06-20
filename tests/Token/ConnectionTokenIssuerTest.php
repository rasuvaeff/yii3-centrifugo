<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Token;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\UnencryptedToken;
use Rasuvaeff\Yii3Centrifugo\Token\ConnectionTokenIssuer;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(ConnectionTokenIssuer::class)]
final class ConnectionTokenIssuerTest
{
    private Configuration $jwtConfig;
    private ConnectionTokenIssuer $issuer;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->jwtConfig = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText('test-secret-key-at-least-32-chars-long'),
        );
        $this->issuer = new ConnectionTokenIssuer(jwtConfig: $this->jwtConfig, defaultTtl: 3600);
    }

    public function issueContainsSubClaim(): void
    {
        $token = $this->parse($this->issuer->issue(userId: '42'));

        Assert::same($token->claims()->get('sub'), '42');
    }

    public function issueContainsExpClaim(): void
    {
        $token = $this->parse($this->issuer->issue(userId: '1'));

        Assert::instanceOf($token->claims()->get('exp'), \DateTimeImmutable::class);
    }

    public function issueWithChannelsAddsClaim(): void
    {
        $token = $this->parse($this->issuer->issue(userId: '1', channels: ['news', 'chat']));

        Assert::same($token->claims()->get('channels'), ['news', 'chat']);
    }

    public function issueWithoutChannelsOmitsChannelsClaim(): void
    {
        $token = $this->parse($this->issuer->issue(userId: '1'));

        Assert::false($token->claims()->has('channels'));
    }

    public function issueWithInfoAddsInfoClaim(): void
    {
        $info = ['name' => 'Alice'];
        $token = $this->parse($this->issuer->issue(userId: '1', info: $info));

        Assert::same($token->claims()->get('info'), $info);
    }

    public function issueWithMetaAddsMetaClaim(): void
    {
        $token = $this->parse($this->issuer->issue(userId: '1', meta: ['role' => 'admin']));

        Assert::same($token->claims()->get('meta'), ['role' => 'admin']);
    }

    public function customTtlOverridesDefault(): void
    {
        $before = new \DateTimeImmutable();
        $token = $this->parse($this->issuer->issue(userId: '1', ttl: 60));
        $exp = $token->claims()->get('exp');

        Assert::instanceOf($exp, \DateTimeImmutable::class);
        $diff = $exp->getTimestamp() - $before->getTimestamp();
        Assert::true($diff >= 59);
        Assert::true($diff <= 61);
    }

    public function defaultTtlIsAppliedWhenNullPassed(): void
    {
        $before = new \DateTimeImmutable();
        $issuer = new ConnectionTokenIssuer(jwtConfig: $this->jwtConfig, defaultTtl: 7200);
        $token = $this->parse($issuer->issue(userId: '1'));
        $exp = $token->claims()->get('exp');

        Assert::instanceOf($exp, \DateTimeImmutable::class);
        $diff = $exp->getTimestamp() - $before->getTimestamp();
        Assert::true($diff >= 7199);
        Assert::true($diff <= 7201);
    }

    public function throwsOnEmptyUserId(): void
    {
        try {
            $this->issuer->issue(userId: '');
            Assert::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('userId must not be empty');
        }
    }

    private function parse(string $jwt): UnencryptedToken
    {
        $token = $this->jwtConfig->parser()->parse($jwt);
        Assert::instanceOf($token, UnencryptedToken::class);

        return $token;
    }
}
