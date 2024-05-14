<?php
namespace Altitude\SX\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * @var string
     */
    private static $connectionName = 'sales';

	public function upgrade( SchemaSetupInterface $setup, ModuleContextInterface $context ) {
		$installer = $setup;

		$connection = $setup->startSetup()->getConnection(self::$connectionName);

        $connection
        ->addColumn(
            $setup->getTable('quote'),
            'order_instructions',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length' => 0,
                'comment' => 'Order Instructions'
            ]
        );

            $connection
                ->addColumn(
                    $setup->getTable('sales_order'),
                    'order_instructions',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        'length' => 0,
                        'comment' => 'Order Instructions'
                    ]
                );

            $connection
                ->addColumn(
                    $setup->getTable('sales_order_grid'),
                    'order_instructions',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        'length' => 0,
                        'comment' => 'Order Instructions'
                    ]
                );


		$installer->endSetup();
	}
}