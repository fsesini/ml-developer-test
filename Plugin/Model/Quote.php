<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);
namespace ML\DeveloperTest\Plugin\Model;

use Magento\Quote\Model\Quote as MagentoQuote;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Locale\Resolver;

class Quote
{
    const XML_PATH_BLOCK_ADD_TO_CART_ENABLE = 'catalog/block_add_to_cart/enable';
    const XML_PATH_BLOCK_ADD_TO_CART_MESSAGE = 'catalog/block_add_to_cart/notice_message';

    const IP2COUNTRY_ACCESS_KEY = 'a1c43202cd3f08618b6e9278032f4bad';
    const IP2COUNTRY_BASE_URL = 'http://api.ipapi.com/api';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var RemoteAddress
     */
    private $remoteAddress;

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var Resolver
     */
    private $store;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param RemoteAddress $remoteAddress
     * @param Curl $curl
     * @param Resolver $store
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        RemoteAddress $remoteAddress,
        Curl $curl,
        Resolver $store
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->remoteAddress = $remoteAddress;
        $this->curl = $curl;
        $this->store = $store;
    }

    /**
     * Prevent product from being added to the cart if user is from blocked country.
     *
     * @param MagentoQuote $subject
     * @param callable $proceed
     * @param mixed $product
     * @param null|float|\Magento\Framework\DataObject $request
     * @param null|string $processMode
     * @return callable|string
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\Exception
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function aroundAddProduct(
        MagentoQuote $subject,
        callable $proceed,
        \Magento\Catalog\Model\Product $product,
        $request = null,
        $processMode = \Magento\Catalog\Model\Product\Type\AbstractType::PROCESS_MODE_FULL
    )
    {
        if ($this->getConfigValue(self::XML_PATH_BLOCK_ADD_TO_CART_ENABLE) == 1) {
            $blockedCountries = $product->getBlockAddToCart() ?? false;
            if (!$blockedCountries) {
                // no countries have been blocked for this product, so we can carry on
                return $proceed($product, $request, $processMode);
            }
            $blockedCountries = explode(',', $blockedCountries);
            // assumes no forward or reverse proxy is being used
            $userIp = $this->remoteAddress->getRemoteAddress();

            if ($userIp) {
                try {
                    $this->curl->get($this->getIp2CountryUrl($userIp));
                    $curlResult = json_decode($this->curl->getBody(), true);
                } catch (\Exception $e) {
                    throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
                }

                if ($curlResult['country_code'] === null || $curlResult['country_name'] === null) {
                    // it wasn't possible to determine what country the user is in, so we carry on
                    return $proceed($product, $request, $processMode);
                } else if (in_array($curlResult['country_code'], $blockedCountries)) {
                    // return message to stop add to cart
                    // the cart model will take care of displaying it as a notice
                    $message = $this->getConfigValue(self::XML_PATH_BLOCK_ADD_TO_CART_MESSAGE);
                    // (assumes message in config contains the string COUNTRY_NAME)
                    return str_replace('COUNTRY_NAME', $curlResult['country_name'], $message);
                }
            }
        }

        return $proceed($product, $request, $processMode);
    }

    /**
     * Get value of given configuration field
     *
     * @param string $configId
     * @return mixed
     */
    private function getConfigValue(string $configId)
    {
        return $this->scopeConfig->getValue($configId, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Return Ip2Country Url with necessary params
     *
     * @param string $ip
     * @return string
     */
    private function getIp2CountryUrl(string $ip) {
        $language = $this->getRequestLanguage();
        return self::IP2COUNTRY_BASE_URL . '/' . $ip . '?access_key=' . self::IP2COUNTRY_ACCESS_KEY . '&fields=country_code,country_name&language=' . $language;
    }

    /**
     * Return request language based on store locale
     *
     * @return string
     */
    private function getRequestLanguage() {
        $locale = $this->store->getLocale();

        // attempt to match the locale language, otherwise go with English
        return match (true) {
            str_contains($locale, 'de_') => 'de',
            str_contains($locale, 'es_') => 'es',
            str_contains($locale, 'fr_') => 'fr',
            str_contains($locale, 'ja_') => 'ja',
            str_contains($locale, 'pt_') => 'pt-br',
            str_contains($locale, 'ru_') => 'ru',
            str_contains($locale, 'zh_') => 'zh',
            default => 'en',
        };
    }
}
