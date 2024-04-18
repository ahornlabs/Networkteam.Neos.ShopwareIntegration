<?php
declare(strict_types = 1);
namespace Networkteam\Neos\ShopwareIntegration\ContentRepository\DataSource;

use Neos\Flow\Annotations as Flow;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Neos\Neos\Service\DataSource\AbstractDataSource;
use Neos\ContentRepository\Domain\Model\NodeInterface;

final class ShopwareCategoryDataSource extends AbstractDataSource
{
    static protected $identifier = 'shopware-category';

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
     * @param NodeInterface $node The node that is currently edited (optional)
     * @param array $arguments
     * @return array
     */
    public function getData(NodeInterface $node = null, array $arguments = []): array
    {

        $this->guzzle = new GuzzleClient([
            'base_uri' => $this->shopwareSettings['api'],
            'headers' => [
                'Accept' => 'application/json',
                'SW-Access-Key' => $this->shopwareSettings['key']
            ],
            'query' => [
                'sort' => 'name',
                'limit' => 500
            ],

        ]);

        try {
            $response = $this->guzzle->request('GET', 'store-api/category');
        } catch (GuzzleException $exception) {
            throw new \RuntimeException(sprintf('Uri Getter: %s', $exception->getMessage()), 1560856269, $exception);
        }

        $data = $this->parseJsonResponse($response)['data'];
        $options = array_map(function($category) {
            return [
                'value' => $category['id'],
                'label' => $category['translated']['name']
            ];
        }, $data);

        return $options;
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
