<?php
class Koleimports_Dropship_Block_Adminhtml_Catalog_Product extends Mage_Adminhtml_Block_Catalog_Product
{
    public function __construct() {
        parent::__construct();

        if (!Koleimports_Dropship_Model_Config::isShowButton()) return;
        Mage::log("Koleimports_Dropship_Block_Adminhtml_Catalog_Product::__construct():\n\n");
        $this->_addButton('rundropship', array(
            'label'    => Mage::helper('catalog')->__('Run Dropship Import'),
            'onclick'  => 'setLocation(\'' . $this->getUrl('dropship/syncman/run') .'\')'
        ));
    }
    /*protected function _prepareLayout() {
        Mage::log("Koleimports_Dropship_Block_Adminhtml_Catalog_Product::_prepareLayout():\n\n");
        return parent::_prepareLayout(); 
    }*/
}
