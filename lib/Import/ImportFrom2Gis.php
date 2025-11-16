<?php

namespace Beeralex\Reviews\Import;

use Bitrix\Main\Web\HttpClient;
use Beeralex\Reviews\Enum\Platforms;

/**
 * @deprecated
 */
class ImportFrom2Gis extends BaseImport
{
    protected array $branches;
    protected string $apiKey;

    public function __construct(\Beeralex\Reviews\Services\ReviewsService $service, array $branches, string $apiKey)
    {
        parent::__construct($service);
        $this->branches = $branches;
        $this->apiKey = $apiKey;
    }

    public function process(): void
    {
        if(empty($this->branches) || !$this->apiKey) return;
        foreach ($this->branches as $id) {
            $reviews = [];
            $offset = 0;
            $limit = 50;

            do {
                $url = sprintf(
                    'https://public-api.reviews.2gis.com/2.0/branches/%s/reviews?limit=%d&offset=%d&is_advertiser=false&fields=meta.providers,meta.branch_rating,meta.branch_reviews_count,meta.total_count,reviews.hiding_reason,reviews.is_verified&without_my_first_review=false&rated=true&sort_by=friends&key=%s&locale=ru_RU',
                    $id,
                    $limit,
                    $offset,
                    $this->apiKey
                );
                $response = $this->fetch($url);
                if (!$response || empty($response['reviews'])) {
                    break;
                }

                foreach ($response['reviews'] as $review) {
                    $rating = (float)($review['rating'] ?? 0);
                    if ($rating < 4) {
                        continue;
                    }
                    $files = $this->downloadPhotos($review);
                    $reviews[] = [
                        'form' => [
                            'eval' => $rating,
                            'review' => $review['text'] ?? '',
                            'user_name' => $review['user']['name'] ?? 'Аноним',
                            'platform' => Platforms::TWO_GIS->value,
                            'contact' => '',
                            'active' => true,
                            'answer' => $review['official_answer'] ? $review['official_answer']['text'] : '',
                            'external_id' => $review['id'],
                        ],
                        'files' => $files['files'],
                        'tmp_paths' => $files['tmp_paths'],
                    ];
                }

                $offset += $limit;
                $total = $response['meta']['total_count'] ?? 0;
            } while ($offset < $total);
            if (!empty($reviews)) {
                $this->import($reviews);
            }
        }
    }
    protected function fetch(string $url): ?array
    {
        $client = new HttpClient([
            'disableSslVerification' => true,
            'socketTimeout' => 10,
            'streamTimeout' => 10,
        ]);

        $client->setHeader('Accept', 'application/json');

        $response = $client->get($url);

        if ($client->getStatus() !== 200 || !$response) {
            return null;
        }

        return json_decode($response, true);
    }

    protected function downloadPhotos(array $review): array
    {
        if (empty($review['photos'])) {
            return [
                'files' => [],
                'tmp_paths' => [],
            ];
        }
        $photos = [
            'files' => [],
            'tmp_paths' => [],
        ];
        foreach ($review['photos'] as $photo) {
            $file = $this->downloadFile($photo['preview_urls']['url'] ?? '');
            if ($file) {
                $this->setToFiles($photos, $file);
            }
        }
        return $photos;
    }
}
