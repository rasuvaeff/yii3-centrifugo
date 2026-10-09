<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\Yii3Centrifugo\CentrifugoClientInterface;
use Rasuvaeff\Yii3Centrifugo\CentrifugoException;
use Rasuvaeff\Yii3Centrifugo\CentrifugoTransportException;
use Rasuvaeff\Yii3Centrifugo\Testing\InMemoryCentrifugoClient;

// Application code depends on the interface, not on the HTTP client.
final readonly class ArticleNotifier
{
    public function __construct(private CentrifugoClientInterface $centrifugo) {}

    public function articleUpdated(int $id): bool
    {
        try {
            $this->centrifugo->publish(channel: 'articles', data: ['id' => $id]);
        } catch (CentrifugoException) {
            return false;
        }

        return true;
    }
}

$centrifugo = new InMemoryCentrifugoClient();
$notifier = new ArticleNotifier($centrifugo);

$notifier->articleUpdated(7);
echo 'Published to articles: ' . json_encode($centrifugo->publishedTo('articles'), JSON_THROW_ON_ERROR) . PHP_EOL;

$centrifugo->failNextWith(new CentrifugoTransportException('Centrifugo is down'));
echo 'Second notify succeeded: ' . var_export($notifier->articleUpdated(8), return: true) . PHP_EOL;
