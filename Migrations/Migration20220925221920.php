<?php

namespace Plugin\dimater_jtl5\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20220925221920 extends Migration implements IMigration
{
    /**
     * Create ginger transaction details table during the ginger plugin installation
     *
     */
    public function up()
    {
        $this->execute('CREATE TABLE IF NOT EXISTS `xplugin_ginger_transaction_details` (
                       `id` int(10) NOT NULL AUTO_INCREMENT,
                       `ginger_order_id` VARCHAR(64) NOT NULL,
                       `merchant_order_id` int(20) NOT NULL,
                        PRIMARY KEY (`id`)
                       ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci'
        );
    }
    
    /**
     * Delete Ginger transaction details table during the ginger plugin uninstallation
     *
     */
    public function down()
    {
        $this->execute('DROP TABLE IF EXISTS `xplugin_ginger_transaction_details`');
    }
}
