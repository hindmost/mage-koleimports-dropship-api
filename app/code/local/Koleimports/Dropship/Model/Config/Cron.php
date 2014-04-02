<?php
class Koleimports_Dropship_Model_Config_Cron extends Mage_Core_Model_Config_Data
{
    const CRON_STRING_PATH = 'crontab/jobs/dropship_import/schedule/cron_expr';
    const CRON_MODEL_PATH  = 'crontab/jobs/dropship_import/run/model';
    const RX_EXPR_ELEM = '(?:\d[\d,\-]*|\*(?:\/\d{1,2}|))';

    protected function _afterSave() {
        if (!Koleimports_Dropship_Model_Config::isEnabled())
            return $this;
        Mage::app()->getStore()->resetConfig();
        $expr = Koleimports_Dropship_Model_Config::getVal('cron_expr');
        $rx = '/^ *'.implode(' ', array_fill(0, 5, self::RX_EXPR_ELEM)).' *$/iu';
        $cronexpr = ($expr && preg_match($rx, $expr))? trim($expr) : '';
        Mage::log("Koleimports_Dropship_Model_Config_Cron::_afterSave(): expr='$expr'; cronexpr='$cronexpr'\n");
        try {
            Mage::getModel('core/config_data')
                ->load(self::CRON_STRING_PATH, 'path')
                ->setValue($cronexpr)
                ->setPath(self::CRON_STRING_PATH)
                ->save();

            Mage::getModel('core/config_data')
                ->load(self::CRON_MODEL_PATH, 'path')
                ->setValue((string) Mage::getConfig()->getNode(self::CRON_MODEL_PATH))
                ->setPath(self::CRON_MODEL_PATH)
                ->save();
        }
        catch (Exception $e) {
            Mage::throwException(Mage::helper('adminhtml')->__('Unable to save the cron expression.'));
        }

        return $this;
    }
}
