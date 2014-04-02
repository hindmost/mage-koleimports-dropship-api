<?php
require_once('app/Mage.php');

Mage::setIsDeveloperMode(true);
ini_set('display_errors', 1); 

Mage::app();

umask(0);

Mage::log("testcron-koleimports-run.php:\n\n");
try {
    Mage::getModel('dropship/sync')->runImport();
    echo "OK";
}
catch (Exception $e) {
    Mage::logException($e);
    Mage::printException($e);
}
