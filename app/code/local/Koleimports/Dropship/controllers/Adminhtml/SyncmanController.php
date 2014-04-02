<?php
class Koleimports_Dropship_Adminhtml_SyncmanController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction() {
        $this->_initAction()->renderLayout();
    }

    public function runAction() {
        Mage::log("Koleimports_Dropship_Adminhtml_DropshipmanController::runAction():\n\n");
        $obj = Mage::getModel('dropship/sync');
        $this->_getSession()->addSuccess($this->__(
            $this->_outState($obj->runImport(), $obj->getState())
        ));
        $this->_redirect('adminhtml/catalog_product/index');
    }

    protected function _outState($b, $arr) {
        list($time_run, , $time_end, $i_page) = $arr;
        $out = 'Koleimports Dropship Import ';
        if ($b)
            $out .= $time_end? 'completed' :
            sprintf('uncompleted/paused by timeout: %d page(s) processed', $i_page);
        else
            $out .= $time_run || $time_end?
            'cancelled: '. ($time_run? 'Another script instance already running' : 'Last import results are not expired') :
            'failed due to unknown error';
        return $out.'.';
    }
}
