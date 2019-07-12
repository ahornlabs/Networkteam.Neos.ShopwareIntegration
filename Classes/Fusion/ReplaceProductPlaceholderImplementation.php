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

class ReplaceProductPlaceholderImplementation extends AbstractFusionObject
{

    const PLACEHOLDER_PATTERN = '/\{\{shopware.product.(.*?)\}\}/';
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
        $text = $this->fusionValue('value');
        $productId = $this->fusionValue('productId');

        if ($text === '' || $text === null) {
            return '';
        }

        if (!is_string($text)) {
            throw new Exception(sprintf('Only strings can be processed by this Fusion object, given: "%s".', gettype($text)), 1562926678);
        }

        if ($productId === null || !is_string($productId)) {
            throw new Exception(sprintf('You need to set a product ID to replace placeholders, given: "%s".', gettype($text)), 1562926692);
        }

        $node = $this->fusionValue('node');

        if (!$node instanceof NodeInterface) {
            throw new Exception(sprintf('The current node must be an instance of NodeInterface, given: "%s".', gettype($text)), 1382624087);
        }

        if ($node->getContext()->getWorkspace()->getName() !== 'live') {
            return $text;
        }

        $this->guzzle = new GuzzleClient([
            'base_uri' => $this->shopwareSettings['api'],
            'headers' => [
                'Accept' => 'application/json',
                'SW-Access-Key' => $this->shopwareSettings['key']
            ]
        ]);

        try {
            $response = $this->guzzle->request('GET', 'sales-channel-api/v1/product/' . $productId);
        } catch (GuzzleException $exception) {
            throw new \RuntimeException(sprintf('Uri Getter: %s', $exception->getMessage()), 1560856269, $exception);
        }

        $processedResponse = $this->parseJsonResponse($response)['data'];

        $processedContent = preg_replace_callback(self::PLACEHOLDER_PATTERN, function (array $matches) use ($processedResponse) {
            $productValue = Arrays::getValueByPath($processedResponse, $matches[1]);

            return $productValue === null ? $matches[0] : $productValue;
        }, $text);

        return $processedContent;
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
