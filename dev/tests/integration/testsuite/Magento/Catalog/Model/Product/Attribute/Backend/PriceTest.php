<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Catalog\Model\Product\Attribute\Backend;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;

/**
 * Test class for \Magento\Catalog\Model\Product\Attribute\Backend\Price.
 *
 * @magentoAppArea adminhtml
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PriceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Magento\Catalog\Model\Product\Attribute\Backend\Price
     */
    private $model;

    /**
     * @var \Magento\TestFramework\ObjectManager
     */
    private $objectManager;

    /** @var ProductRepositoryInterface */
    private $productRepository;

    /**
     * @var Product
     */
    private $productResource;

    /**
     * @var \Magento\TestFramework\Fixture\DataFixtureStorage
     */
    private $fixtures;

    protected function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        /** @var ReinitableConfigInterface $reinitiableConfig */
        $reinitiableConfig = $this->objectManager->get(ReinitableConfigInterface::class);
        $reinitiableConfig->setValue(
            'catalog/price/scope',
            \Magento\Store\Model\Store::PRICE_SCOPE_WEBSITE
        );

        $this->model = $this->objectManager->create(
            \Magento\Catalog\Model\Product\Attribute\Backend\Price::class
        );
        $this->productRepository = $this->objectManager->create(
            ProductRepositoryInterface::class
        );
        $this->productResource = $this->objectManager->create(
            Product::class
        );
        $this->fixtures = $this->objectManager->get(DataFixtureStorageManager::class)->getStorage();
        $this->model->setAttribute(
            $this->objectManager->get(
                \Magento\Eav\Model\Config::class
            )->getAttribute(
                'catalog_product',
                'price'
            )
        );
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testSetScopeDefault()
    {
        /* validate result of setAttribute */
        $this->assertEquals(
            \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            $this->model->getAttribute()->getIsGlobal()
        );
        $this->model->setScope($this->model->getAttribute());
        $this->assertEquals(
            \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            $this->model->getAttribute()->getIsGlobal()
        );
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture current_store catalog/price/scope 1
     */
    public function testSetScope()
    {
        $this->model->setScope($this->model->getAttribute());
        $this->assertEquals(
            \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_WEBSITE,
            $this->model->getAttribute()->getIsGlobal()
        );
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoConfigFixture current_store catalog/price/scope 1
     * @magentoConfigFixture current_store currency/options/base GBP
     */
    public function testAfterSave()
    {
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->objectManager->create(\Magento\Store\Model\Store::class);
        $globalStoreId = $store->load('admin')->getId();
        $product = $this->productRepository->get('simple');
        $product->setPrice('9.99');
        $product->setStoreId($globalStoreId);
        $this->productResource->save($product);
        $product = $this->productRepository->get('simple', false, $globalStoreId, true);
        $this->assertEquals('9.990000', $product->getPrice());
    }

    /**
     * @magentoDataFixture Magento\Store\Test\Fixture\Website as:website2
     * @magentoDataFixture Magento\Store\Test\Fixture\Group with:{"website_id":"$website2.id$"} as:store_group2
     * @magentoDataFixture Magento\Store\Test\Fixture\Store with:{"store_group_id":"$store_group2.id$"} as:store2
     * @magentoDataFixture Magento\Store\Test\Fixture\Store with:{"store_group_id":"$store_group2.id$"} as:store3
     * @magentoDataFixture Magento\Catalog\Test\Fixture\Product as:product
     * @magentoConfigFixture current_store catalog/price/scope 1
     * @magentoDbIsolation disabled
     * @magentoAppArea adminhtml
     */
    public function testAfterSaveWithDifferentStores()
    {
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->objectManager->create(
            \Magento\Store\Model\Store::class
        );
        $globalStoreId = $store->load('admin')->getId();
        $secondStoreId = $this->fixtures->get('store2')->getId();
        $thirdStoreId = $this->fixtures->get('store3')->getId();
        $productSku = $this->fixtures->get('product')->getSku();
        /** @var \Magento\Catalog\Model\Product\Action $productAction */
        $productAction = $this->objectManager->create(
            \Magento\Catalog\Model\Product\Action::class
        );

        $product = $this->productRepository->get($productSku);
        $productId = $product->getId();
        $productAction->updateWebsites([$productId], [$store->load('fixture_second_store')->getWebsiteId()], 'add');
        $product->setStoreId($secondStoreId);
        $product->setPrice('9.99');
        $this->productResource->save($product);

        $product = $this->productRepository->get($productSku, false, $globalStoreId, true);
        $this->assertEquals(10, $product->getPrice());

        $product = $this->productRepository->get($productSku, false, $secondStoreId, true);
        $this->assertEquals('9.990000', $product->getPrice());

        $product = $this->productRepository->get($productSku, false, $thirdStoreId, true);
        $this->assertEquals('9.990000', $product->getPrice());
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
     * @magentoConfigFixture current_store catalog/price/scope 1
     * @magentoDbIsolation disabled
     * @magentoAppArea adminhtml
     */
    public function testAfterSaveWithSameCurrency()
    {
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->objectManager->create(
            \Magento\Store\Model\Store::class
        );
        $globalStoreId = $store->load('admin')->getId();
        $secondStoreId = $store->load('fixture_second_store')->getId();
        $thirdStoreId = $store->load('fixture_third_store')->getId();
        /** @var \Magento\Catalog\Model\Product\Action $productAction */
        $productAction = $this->objectManager->create(
            \Magento\Catalog\Model\Product\Action::class
        );

        $product = $this->productRepository->get('simple');
        $productId = $product->getId();
        $productAction->updateWebsites([$productId], [$store->load('fixture_second_store')->getWebsiteId()], 'add');
        $product->setOrigData();
        $product->setStoreId($secondStoreId);
        $product->setPrice('9.99');
        $this->productResource->save($product);

        $product = $this->productRepository->get('simple', false, $globalStoreId, true);
        $this->assertEquals(10, $product->getPrice());

        $product = $this->productRepository->get('simple', false, $secondStoreId, true);
        $this->assertEquals('9.990000', $product->getPrice());

        $product = $this->productRepository->get('simple', false, $thirdStoreId, true);
        $this->assertEquals('9.990000', $product->getPrice());
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppArea adminhtml
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
     * @magentoConfigFixture current_store catalog/price/scope 1
     */
    public function testAfterSaveWithUseDefault()
    {
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->objectManager->create(
            \Magento\Store\Model\Store::class
        );
        $globalStoreId = $store->load('admin')->getId();
        $secondStoreId = $store->load('fixture_second_store')->getId();
        $thirdStoreId = $store->load('fixture_third_store')->getId();
        /** @var \Magento\Catalog\Model\Product\Action $productAction */
        $productAction = $this->objectManager->create(
            \Magento\Catalog\Model\Product\Action::class
        );

        $product = $this->productRepository->get('simple');
        $productId = $product->getId();
        $productAction->updateWebsites([$productId], [$store->load('fixture_second_store')->getWebsiteId()], 'add');
        $product->setOrigData();
        $product->setStoreId($secondStoreId);
        $product->setPrice('9.99');
        $this->productResource->save($product);

        $product = $this->productRepository->get('simple', false, $globalStoreId, true);
        $this->assertEquals(10, $product->getPrice());

        $product = $this->productRepository->get('simple', false, $secondStoreId, true);
        $this->assertEquals('9.990000', $product->getPrice());

        $product = $this->productRepository->get('simple', false, $thirdStoreId, true);
        $this->assertEquals('9.990000', $product->getPrice());

        $product->setStoreId($thirdStoreId);
        $product->setPrice(null);
        $this->productResource->save($product);

        $product = $this->productRepository->get('simple', false, $globalStoreId, true);
        $this->assertEquals(10, $product->getPrice());

        $product = $this->productRepository->get('simple', false, $secondStoreId, true);
        $this->assertEquals(10, $product->getPrice());

        $product = $this->productRepository->get('simple', false, $thirdStoreId, true);
        $this->assertEquals(10, $product->getPrice());
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppArea adminhtml
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
     * @magentoConfigFixture default_store catalog/price/scope 1
     */
    public function testAfterSaveForWebsitesWithDifferentCurrencies()
    {
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->objectManager->create(
            \Magento\Store\Model\Store::class
        );

        /** @var \Magento\Directory\Model\ResourceModel\Currency $rate */
        $rate = $this->objectManager->create(\Magento\Directory\Model\ResourceModel\Currency::class);
        $rate->saveRates([
            'USD' => ['EUR' => 2],
            'EUR' => ['USD' => 0.5]
        ]);

        $globalStoreId = $store->load('admin')->getId();
        $secondStore = $store->load('fixture_second_store');
        $secondStoreId = $store->load('fixture_second_store')->getId();
        $thirdStoreId = $store->load('fixture_third_store')->getId();

        /** @var \Magento\Framework\App\Config\ReinitableConfigInterface $config */
        $config = $this->objectManager->get(\Magento\Framework\App\Config\MutableScopeConfigInterface::class);
        $config->setValue(
            'currency/options/default',
            'EUR',
            \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES,
            'test'
        );

        $productAction = $this->objectManager->create(
            \Magento\Catalog\Model\Product\Action::class
        );
        $product = $this->productRepository->get('simple');
        $productId = $product->getId();
        $productAction->updateWebsites([$productId], [$secondStore->getWebsiteId()], 'add');
        $product->setOrigData();
        $product->setStoreId($globalStoreId);
        $product->setPrice(100);
        $this->productResource->save($product);

        $product = $this->productRepository->get('simple', false, $globalStoreId, true);
        $this->assertEquals(100, $product->getPrice());

        $product = $this->productRepository->get('simple', false, $secondStoreId, true);
        $this->assertEquals(100, $product->getPrice());

        $product = $this->productRepository->get('simple', false, $thirdStoreId, true);
        $this->assertEquals(100, $product->getPrice());
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        /** @var ReinitableConfigInterface $reinitiableConfig */
        $reinitiableConfig = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get(
            ReinitableConfigInterface::class
        );
        $reinitiableConfig->setValue(
            'catalog/price/scope',
            \Magento\Store\Model\Store::PRICE_SCOPE_GLOBAL
        );
    }
}
