<?php
namespace Networkteam\Neos\ShopwareIntegration\Fusion;


use Neos\Flow\Annotations as Flow;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Neos\Neos\Domain\Exception;
use Neos\Utility\Arrays;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Fusion\FusionObjects\AbstractFusionObject;

class ProductsByCategoryImplementation extends AbstractFusionObject
{
    /**
     * @var GuzzleClient
     */
    private $guzzle;

    /**
     * @Flow\InjectConfiguration(package="Networkteam.Neos.ShopwareIntegration")
     * @var array
     */
    protected $shopwareSettings;

    /**
     * Replace Placeholders of a product
     *
     * @return string
     * @throws Exception
     */
    public function evaluate()
    {
        $categoryId = $this->fusionValue('categoryId');

        if (!$categoryId) {
            return [];
        }

        $this->guzzle = new GuzzleClient([
            'base_uri' => $this->shopwareSettings['api'],
            'headers' => [
                'Accept' => 'application/json',
                'SW-Access-Key' => $this->shopwareSettings['key']
            ],
            'query' => [
                'filter[product.categoryTree]' => $categoryId
            ]
        ]);

        try {
            $response = $this->guzzle->request('GET', 'store-api/product');
        } catch (GuzzleException $exception) {
            throw new \RuntimeException(sprintf('Uri Getter: %s', $exception->getMessage()), 1560856269, $exception);
        }

        return $this->parseJsonResponse($response)['data'];
    }

    private function parseJsonResponse(ResponseInterface $response): array
    {
        $responseBody = $response->getBody()->getContents();
        $responseData = json_decode($responseBody, true);
        if ($responseData === null) {
            throw new \RuntimeException(sprintf('Error decoding response from EMS endpoint: %s (%d)', json_last_error_msg(), json_last_error()), 1558971370);
        }
        return $responseData;
    }
}
