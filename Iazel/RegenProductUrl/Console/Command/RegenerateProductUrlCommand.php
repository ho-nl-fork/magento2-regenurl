<?php
namespace Iazel\RegenProductUrl\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Store\Model\Store;

class RegenerateProductUrlCommand extends Command
{
    /**
     * @var ProductUrlRewriteGenerator
     */
    protected $productUrlRewriteGenerator;

    /**
     * @var UrlPersistInterface
     */
    protected $urlPersist;

    /**
     * @var ProductRepositoryInterface
     */
    protected $collection;

    public function __construct(
        \Magento\Framework\App\State $state,
        Collection $collection,
        ProductUrlRewriteGenerator $productUrlRewriteGenerator,
        UrlPersistInterface $urlPersist
    ) {
        $state->setAreaCode('adminhtml');
        $this->collection = $collection;
        $this->productUrlRewriteGenerator = $productUrlRewriteGenerator;
        $this->urlPersist = $urlPersist;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('iazel:regenurl')
            ->setDescription('Regenerate url for given products')
            ->addArgument(
                'pids',
                InputArgument::IS_ARRAY,
                'Products to regenerate'
            )
            ->addOption(
                'store', 's',
                InputOption::VALUE_REQUIRED,
                'Use the specific Store View',
                Store::DEFAULT_STORE_ID
            )
            ;
        return parent::configure();
    }

    public function execute(InputInterface $inp, OutputInterface $out)
    {
        $store_id = $inp->getOption('store');
        $this->collection->addStoreFilter($store_id)->setStoreId($store_id);

        $pids = $inp->getArgument('pids');
        if( !empty($pids) )
            $this->collection->addIdFilter($pids);

        $this->collection->addAttributeToSelect(['url_path', 'url_key']);
        $list = $this->collection->load();
        foreach($list as $product)
        {
            if($store_id === Store::DEFAULT_STORE_ID || isset($store_id))
                $product->setStoreId($store_id);

            $this->urlPersist->deleteByData([
                UrlRewrite::ENTITY_ID => $product->getId(),
                UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE,
                UrlRewrite::REDIRECT_TYPE => 0,
                UrlRewrite::STORE_ID => $store_id
            ]);
            try {
                $this->urlPersist->replace(
                    $this->productUrlRewriteGenerator->generate($product)
                );
            }
            catch(\Exception $e) {
                $out->writeln('<error>Duplicated url for '. $product->getId() .'</error>');
            }
        }
    }
}
