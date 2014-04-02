<?php
class Koleimports_Dropship_Model_Config
{
    /**
     * @param string $key
     * @return mixed
     */
    static function getVal($key) {
        return Mage::getStoreConfig("catalog/dropship/$key");
    }

    /**
     * @param string $key
     * @param mixed $val
     * @return mixed
     */
    static function setVal($key, $val) {
        Mage::getConfig()->saveConfig("catalog/dropship/$key", $val)
            ->cleanCache();
        Mage::app()->reinitStores();
    }

    /**
     * @return bool
     */
    static function isEnabled() {
        return (bool)intval(self::getVal('enable'));
    }

    /**
     * @return bool
     */
    static function isShowButton() {
        return self::isEnabled() && intval(self::getVal('import_show_btn'));
    }

    /**
     * @return bool
     */
    static function isOrderSuccess($bSuccess = false) {
        $v = intval(self::getVal('export_on_ordersuccess'));
        $b_ok = self::isEnabled() && ($bSuccess ^ !$v);
        Mage::log(">isOrderSuccess('$bSuccess'): v='$v'; b_ok='$b_ok'\n");
        return $b_ok;
    }
}
