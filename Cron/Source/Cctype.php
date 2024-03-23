<?php
/**
 * Payment CC Types Source Model
 *
 * @category    Altitude
 * @package     Altitude_CSDCenPOSAuthInsert

 */

namespace Altitude\CSD\Cron\Source;

class Cctype extends \Magento\Payment\Model\Source\Cctype
{
    /**
     * @return array
     */
    public function getAllowedTypes()
    {
        return array('VI', 'MC', 'AE', 'DI', 'JCB', 'OT');
    }
}
