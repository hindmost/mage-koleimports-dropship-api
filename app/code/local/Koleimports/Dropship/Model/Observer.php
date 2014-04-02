<?php
class Koleimports_Dropship_Model_Observer
{
    /**
     * Handler for the 'checkout_submit_all_after' event
     */
    public function handleOrderSubmit($observer) {
        $o_order = $observer->getData('order');
        if (!is_object($o_order)) return;
        $id = $o_order->getId();
        Mage::log("Koleimports_Dropship_Model_Observer::handleOrderSubmit(): id=$id\n\n");
        if (!Koleimports_Dropship_Model_Config::isOrderSuccess()) return;
        if (!$id) return;
        try {
            Mage::getModel('dropship/sync')->runExport($id);
        }
        catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Handler for the 'checkout_onepage_controller_success_action' event
     */
    public function handleOrderSuccess($observer) {
        $a_ids = $observer->getData('order_ids');
        Mage::log("Koleimports_Dropship_Model_Observer::handleOrderSuccess(): a_ids: ".print_r($a_ids,true)."\n\n");
        if (!Koleimports_Dropship_Model_Config::isOrderSuccess(true)) return;
        if (!is_array($a_ids) && count($a_ids)) return;
        try {
            Mage::getModel('dropship/sync')->runExport($a_ids[0]);
        }
        catch (Exception $e) {
            Mage::logException($e);
        }
        
    }
}
