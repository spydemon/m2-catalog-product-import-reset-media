<?php

namespace Spydemon\CatalogProductImportResetMedia\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as ProductAttributeCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogImportExport\Model\Import\Product as InheritedClass;
use Magento\CatalogImportExport\Model\Import\Product\ImageTypeProcessor;
use Magento\CatalogImportExport\Model\Import\Product\MediaGalleryProcessor;
use Magento\CatalogImportExport\Model\Import\Product\StatusProcessor;
use Magento\CatalogImportExport\Model\Import\Product\StockProcessor;
use Magento\CatalogImportExport\Model\StockItemImporterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Framework\Model\ResourceModel\Db\ObjectRelationProcessor;
use Magento\Framework\Model\ResourceModel\Db\TransactionManagerInterface;
use Magento\Framework\Stdlib\DateTime;
use Magento\ImportExport\Model\Import as ImportExport;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class ImportProduct
 *
 * Note: all this mess could be a lot cleaner if Magento's author decides one day to dispatch a
 * "catalog_product_import_before" event in the \Magento\CatalogImportExport\Model\Import\Product::_importData method.
 */
class ImportProduct extends InheritedClass
{
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ProductCollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var ProductAttributeCollectionFactory
     */
    protected $productAttributeCollectionFactory;

    /**
     * @var string[]|null
     */
    protected $mediaAttributeCodes;

    
    /**
     * ImportProduct constructor.
     *
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\ImportExport\Helper\Data $importExportData
     * @param \Magento\ImportExport\Model\ResourceModel\Import\Data $importData
     * @param \Magento\Eav\Model\Config $config
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper
     * @param \Magento\Framework\Stdlib\StringUtils $string
     * @param ProcessingErrorAggregatorInterface $errorAggregator
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param \Magento\CatalogInventory\Api\StockConfigurationInterface $stockConfiguration
     * @param \Magento\CatalogInventory\Model\Spi\StockStateProviderInterface $stockStateProvider
     * @param \Magento\Catalog\Helper\Data $catalogData
     * @param ImportExport\Config $importConfig
     * @param \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory
     * @param InheritedClass\OptionFactory $optionFactory
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $setColFactory
     * @param InheritedClass\Type\Factory $productTypeFactory
     * @param \Magento\Catalog\Model\ResourceModel\Product\LinkFactory $linkFactory
     * @param \Magento\CatalogImportExport\Model\Import\Proxy\ProductFactory $proxyProdFactory
     * @param \Magento\CatalogImportExport\Model\Import\UploaderFactory $uploaderFactory
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\CatalogInventory\Model\ResourceModel\Stock\ItemFactory $stockResItemFac
     * @param DateTime\TimezoneInterface $localeDate
     * @param DateTime $dateTime
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry
     * @param InheritedClass\StoreResolver $storeResolver
     * @param InheritedClass\SkuProcessor $skuProcessor
     * @param InheritedClass\CategoryProcessor $categoryProcessor
     * @param InheritedClass\Validator $validator
     * @param ObjectRelationProcessor $objectRelationProcessor
     * @param TransactionManagerInterface $transactionManager
     * @param InheritedClass\TaxClassProcessor $taxClassProcessor
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Catalog\Model\Product\Url $productUrl
     * @param StoreManagerInterface $storeManager
     * @param ProductRepositoryInterface $productRepository
     * @param array $data
     * @param array $dateAttrCodes
     * @param CatalogConfig|null $catalogConfig
     * @param ImageTypeProcessor|null $imageTypeProcessor
     * @param MediaGalleryProcessor|null $mediaProcessor
     * @param StockItemImporterInterface|null $stockItemImporter
     * @param DateTimeFactory|null $dateTimeFactory
     * @param StatusProcessor|null $statusProcessor
     * @param StockProcessor|null $stockProcessor
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\ImportExport\Helper\Data $importExportData,
        \Magento\ImportExport\Model\ResourceModel\Import\Data $importData,
        \Magento\Eav\Model\Config $config,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Framework\Stdlib\StringUtils $string,
        \Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface $errorAggregator,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\CatalogInventory\Api\StockConfigurationInterface $stockConfiguration,
        \Magento\CatalogInventory\Model\Spi\StockStateProviderInterface $stockStateProvider,
        \Magento\Catalog\Helper\Data $catalogData,
        \Magento\ImportExport\Model\Import\Config $importConfig,
        \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory,
        \Magento\CatalogImportExport\Model\Import\Product\OptionFactory $optionFactory,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $setColFactory,
        \Magento\CatalogImportExport\Model\Import\Product\Type\Factory $productTypeFactory,
        \Magento\Catalog\Model\ResourceModel\Product\LinkFactory $linkFactory,
        \Magento\CatalogImportExport\Model\Import\Proxy\ProductFactory $proxyProdFactory,
        \Magento\CatalogImportExport\Model\Import\UploaderFactory $uploaderFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\CatalogInventory\Model\ResourceModel\Stock\ItemFactory $stockResItemFac,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        DateTime $dateTime,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry,
        \Magento\CatalogImportExport\Model\Import\Product\StoreResolver $storeResolver,
        \Magento\CatalogImportExport\Model\Import\Product\SkuProcessor $skuProcessor,
        \Magento\CatalogImportExport\Model\Import\Product\CategoryProcessor $categoryProcessor,
        \Magento\CatalogImportExport\Model\Import\Product\Validator $validator,
        \Magento\Framework\Model\ResourceModel\Db\ObjectRelationProcessor $objectRelationProcessor,
        \Magento\Framework\Model\ResourceModel\Db\TransactionManagerInterface $transactionManager,
        \Magento\CatalogImportExport\Model\Import\Product\TaxClassProcessor $taxClassProcessor,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Model\Product\Url $productUrl,
        StoreManagerInterface $storeManager,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        ProductCollectionFactory $productCollectionFactory,
        ProductAttributeCollectionFactory $productAttributeCollectionFactory,
        array $data = [],
        array $dateAttrCodes = [],
        \Magento\Catalog\Model\Config $catalogConfig = null,
        \Magento\CatalogImportExport\Model\Import\Product\ImageTypeProcessor $imageTypeProcessor = null,
        \Magento\CatalogImportExport\Model\Import\Product\MediaGalleryProcessor $mediaProcessor = null,
        \Magento\CatalogImportExport\Model\StockItemImporterInterface $stockItemImporter = null,
        \Magento\Framework\Intl\DateTimeFactory $dateTimeFactory = null,
        \Magento\CatalogImportExport\Model\Import\Product\StatusProcessor $statusProcessor = null,
        \Magento\CatalogImportExport\Model\Import\Product\StockProcessor $stockProcessor = null
    ) {
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productAttributeCollectionFactory = $productAttributeCollectionFactory;
        InheritedClass::__construct(
            $jsonHelper,
            $importExportData,
            $importData,
            $config,
            $resource,
            $resourceHelper,
            $string,
            $errorAggregator,
            $eventManager,
            $stockRegistry,
            $stockConfiguration,
            $stockStateProvider,
            $catalogData,
            $importConfig,
            $resourceFactory,
            $optionFactory,
            $setColFactory,
            $productTypeFactory,
            $linkFactory,
            $proxyProdFactory,
            $uploaderFactory,
            $filesystem,
            $stockResItemFac,
            $localeDate,
            $dateTime,
            $logger,
            $indexerRegistry,
            $storeResolver,
            $skuProcessor,
            $categoryProcessor,
            $validator,
            $objectRelationProcessor,
            $transactionManager,
            $taxClassProcessor,
            $scopeConfig,
            $productUrl,
            $data,
            $dateAttrCodes,
            $catalogConfig,
            $imageTypeProcessor,
            $mediaProcessor,
            $stockItemImporter,
            $dateTimeFactory,
            $productRepository,
            $statusProcessor,
            $stockProcessor
        );
    }

    /**
     * We branch our custom process in the _importData function, but it's not the best place… The problem is that
     * because of all private attributes and methods in the parent class and they huge size, it's finally the best place
     * I found for avoiding to have to rewrite the entire class.
     *
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     * @throws \Exception
     */
    protected function _importData()
    {
        if ($this->getBehavior() != ImportExport::BEHAVIOR_DELETE) {
            $this->resetProductMedia();
        }
        return parent::_importData();
    }

    /**
     * Remove old media pictures assigned to the product before importation of the new ones.
     *
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    protected function resetProductMedia()
    {
        // Media are reset only in the global scope and not on store ones. This behavior is needed since
        // deleting them in a store view will impose to set it again in the same scope. Indeed, the removal
        // will create a new EAV value for the given store_id with the 'no_selection' value.
        // This behavior should be modified if you planned to set medias in store or website scopes one day.
        $this->storeManager->setCurrentStore(0);
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $rowData) {
                $rowSku = $rowData[self::COL_SKU];
                if (@$rowData['reset_images'] == true && ($product = $this->retrieveProductBySku($rowSku))) {
                    $productMediaGallery = $product->getData('media_gallery');
                    // Here, we add the "removed" flag to all images already on the product.
                    $productMediaGallery['images'] = array_map(static function ($e) {
                        $e['removed'] = 1;
                        return $e;
                    }, @$productMediaGallery['images'] ?: []);
                    $product->setData('media_gallery', $productMediaGallery);
                    // This save cause A WAY of slowness… An import that takes 1m13 without it take now 39m36!!
                    // It's the best solution I found for ensuring us that previous medias will correctly be
                    // disassociated from the product and correctly be deleted from the filesystem. This solution should
                    // also be quite future-proof because it seems to be the way Magento core also do media deletion.
                    // The API should thus not be broken in a future Magento release.
                    $this->productRepository->save($product);
                }
            }
        }
        $this->storeManager->reinitStores();
    }

    /**
     * Dumb rewrite of parent method needed because of its private scope.
     *
     * @param string $sku
     * @return \Magento\Catalog\Api\Data\ProductInterface|null
     */
    protected function retrieveProductBySku($sku)
    {
        try {
            $productCollection = $this->productCollectionFactory->create();

            $productCollection->getSelect()->reset('columns')->columns(['entity_id', 'sku']);

            $productCollection
                ->setStore(0)
                ->addFieldToFilter('sku', $sku)
                ->addAttributeToSelect($this->getMediaAttributeCodes());

            $productCollection->getSelect()->limit(1);
            $productCollection->addMediaGalleryData();

            return $productCollection->getFirstItem();

        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * @return string[]
     */
    protected function getMediaAttributeCodes()
    {
        if (null === $this->mediaAttributeCodes) {
            $productAttributeCollection = $this->productAttributeCollectionFactory->create();
            $productAttributeCollection->setFrontendInputTypeFilter('media_image');
            $productAttributeCollection->getSelect()->reset('columns')->columns('attribute_code');

            $this->mediaAttributeCodes = $productAttributeCollection->getConnection()->fetchCol($productAttributeCollection->getSelect());
        }

        return $this->mediaAttributeCodes;
    }
}

