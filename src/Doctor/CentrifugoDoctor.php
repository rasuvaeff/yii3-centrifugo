<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Doctor;

use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Psr\Container\ContainerInterface;
use Rasuvaeff\Yii3Centrifugo\CentrifugoClientInterface;
use Rasuvaeff\Yii3Centrifugo\CentrifugoException;
use Rasuvaeff\Yii3Centrifugo\Internal\Params;
use Rasuvaeff\Yii3Centrifugo\InvalidConfigException;
use Rasuvaeff\Yii3Centrifugo\Token\ConnectionTokenIssuer;

/**
 * Deployment diagnostics without a console dependency: params, server API
 * reachability and API key (`info`), and a round trip of a connection token
 * signed and verified with the configured secret.
 *
 * @api
 */
final readonly class CentrifugoDoctor
{
    /**
     * @param array<array-key, mixed> $params application params
     */
    public function __construct(
        private array $params,
        private ContainerInterface $container,
    ) {}

    public function diagnose(): DoctorReport
    {
        $api = $this->apiConfig();
        $tokens = $this->tokenConfig();

        return new DoctorReport([
            $api,
            $tokens,
            $api->status === CheckStatus::Pass
                ? $this->serverApi()
                : $this->skipped('server API', CheckCategory::Upstream, 'API params are invalid'),
            $tokens->status === CheckStatus::Pass
                ? $this->tokenRoundTrip()
                : $this->skipped('connection token', CheckCategory::Config, 'token params are invalid'),
        ]);
    }

    private function apiConfig(): CheckResult
    {
        try {
            $url = Params::apiUrl($this->params);
            $key = Params::apiKey($this->params);
        } catch (InvalidConfigException $e) {
            return $this->failed('API params', CheckCategory::Config, $e->getMessage());
        }

        return new CheckResult(
            name: 'API params',
            category: CheckCategory::Config,
            status: CheckStatus::Pass,
            details: $url . ($key === '' ? ', empty api_key (Centrifugo must run with api_insecure)' : ''),
        );
    }

    private function tokenConfig(): CheckResult
    {
        try {
            Params::jwtConfiguration($this->params);
            $ttl = Params::tokenTtl($this->params);
        } catch (InvalidConfigException $e) {
            return $this->failed('token params', CheckCategory::Config, $e->getMessage());
        }

        return new CheckResult(
            name: 'token params',
            category: CheckCategory::Config,
            status: CheckStatus::Pass,
            details: sprintf('HS256 secret set, token_ttl %d s', $ttl),
        );
    }

    private function serverApi(): CheckResult
    {
        try {
            $client = $this->container->get(CentrifugoClientInterface::class);
            \assert($client instanceof CentrifugoClientInterface);
        } catch (\Throwable $e) {
            return $this->failed('server API', CheckCategory::Config, 'cannot build the client: ' . $e->getMessage());
        }

        try {
            $info = $client->info();
        } catch (CentrifugoException $e) {
            return $this->failed('server API', CheckCategory::Upstream, $e->getMessage());
        }

        $nodes = isset($info['nodes']) && is_array($info['nodes']) ? count($info['nodes']) : 0;

        return new CheckResult(
            name: 'server API',
            category: CheckCategory::Upstream,
            status: CheckStatus::Pass,
            details: sprintf('info answered, %d node(s)', $nodes),
        );
    }

    private function tokenRoundTrip(): CheckResult
    {
        $config = Params::jwtConfiguration($this->params);
        $jwt = (new ConnectionTokenIssuer(jwtConfig: $config, defaultTtl: Params::tokenTtl($this->params)))
            ->issue(userId: 'centrifugo-doctor');
        $token = $config->parser()->parse($jwt);

        if (!$config->validator()->validate($token, new SignedWith($config->signer(), $config->verificationKey()))) {
            return $this->failed('connection token', CheckCategory::Config, 'token does not verify with the configured secret');
        }

        return new CheckResult(
            name: 'connection token',
            category: CheckCategory::Config,
            status: CheckStatus::Pass,
            details: 'issued and verified (HS256)',
        );
    }

    private function failed(string $name, CheckCategory $category, string $details): CheckResult
    {
        return new CheckResult(name: $name, category: $category, status: CheckStatus::Fail, details: $details);
    }

    private function skipped(string $name, CheckCategory $category, string $details): CheckResult
    {
        return new CheckResult(name: $name, category: $category, status: CheckStatus::Skip, details: $details);
    }
}
