<?php
namespace ML\DeveloperTest\Setup;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class InstallData implements InstallDataInterface
{
    private $eavSetupFactory;

    public function __construct(EavSetupFactory $eavSetupFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'block_add_to_cart',
            [
                'sort_order'              => 200,
                'type'                    => 'text',
                'label'                   => 'Block Add to Cart',
                'input'                   => 'multiselect',
                'class'                   => '',
                'default'                 => '',
                'source'                  => \Magento\Catalog\Model\Product\Attribute\Source\Countryofmanufacture::class,
                'global'                  => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                'backend'                 => \Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend::class,
                'visible'                 => true,
                'required'                => false,
                'user_defined'            => false,
                'visible_on_front'        => false
            ]
        );

        $setup->endSetup();
    }

}
