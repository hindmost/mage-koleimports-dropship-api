<?php
class Koleimports_Dropship_Model_Sync extends Koleimports_Dropship_Model_Api_Client
{
    const URI_PRODUCTS = '/products?limit=%s&offset=%s';
    const URI_ORDERS = '/orders';
    const N_MAXPAGES = 0;
    const ATTR_PREFIX = 'ds_';
    const ATTR_SET_NAME = 'Dropship Products';
    const ATTR_GROUP_NAME = 'Dropship Product Fields';
    const B_POSTJSON = false;
    const XML_ORDER = '<order>
    <po_number></po_number>
    <notes></notes>
    <ship_options>%s</ship_options>
    <ship_to_address>%s</ship_to_address>
    <items>%s</items>
</order>';
    
    static protected $A_ATTRS = array(
        'modified' => array(
            'label' => 'Modified at',
            'data' => array('frontend_input' => 'text',),
        ),
        'packqty' => array(
            'label' => 'Pack Quantity',
            'data' => array('frontend_input' => 'text', 'is_visible_on_front' => '1',),
        ),
        'type' => array(
            'label' => 'Product Type', 'is_select' => true,
            'data' => array('frontend_input' => 'select',),
        ),
        'brand' => array(
            'label' => 'Brand Name', 'is_select' => true,
            'data' => array('frontend_input' => 'select', 'is_visible_on_front' => '1', 'is_searchable' => '1',),
        ),
        'colors' => array(
            'label' => 'Colors',
            'data' => array('frontend_input' => 'text', 'is_visible_on_front' => '1', 'is_searchable' => '1',),
        ),
        'materials' => array(
            'label' => 'Materials',
            'data' => array('frontend_input' => 'text', 'is_visible_on_front' => '1', 'is_searchable' => '1',),
        ),
        'attributes' => array(
            'label' => 'Attributes',
            'data' => array('frontend_input' => 'text', 'is_visible_on_front' => '1',),
        ),
        'upc' => array(
            'label' => 'Universal Product Code',
            'data' => array('frontend_input' => 'text',),
        ),
    );
    static protected $A_MEDIA_ATTRS = array('image', 'small_image', 'thumbnail');
    static protected $A_CARRIERS = array('FEDEX', 'UPS');
    static protected $A_METHODS = array('GROUND', 'PRIORITY', 'OVERNIGHT');

    protected $bEnable = false;
    protected $nPriceMarkup = 0;
    protected $nPagesize = 25;
    protected $nImportLife = 0;
    protected $nTimeLimit = 30;
    protected $idAttrSet = false;
    protected $idAttrGroup = false;
    protected $aAttrIds = false;
    protected $aAttrOptionsets = array();
    protected $nTimeRun = 0;
    protected $nTimeStop = 0;
    protected $nTimeEnd = 0;
    protected $iStatePage = 0;

    function __construct() {
        $this->bEnable = Koleimports_Dropship_Model_Config::isEnabled();
        if (!$this->bEnable) return;
        static $a_names = array(
            'api_id', 'api_key',
            'price_markup', 'import_pagesize', 'import_expire', 'import_timeout',
        );
        $a_vals = array_fill(0, count($a_names), '');
        foreach ($a_names as $i => $name)
            if ($v = Koleimports_Dropship_Model_Config::getVal($name))
                $a_vals[$i] = trim($v);
        list($id, $key, $v1, $v2, $v3, $v4) = $a_vals;
        if (is_numeric($v1))
            $this->nPriceMarkup = $v1;
        if ($v2)
            $this->nPagesize = intval($v2);
        if (is_numeric($v3))
            $this->nImportLife = $v3 * 60 * 60;
        if (is_numeric($v4))
            $this->nTimeLimit = $v4 * 60;
        $b_showbtn = Koleimports_Dropship_Model_Config::isShowButton();
        Mage::log(">Koleimports_Dropship_Model_Sync::__construct(): cfg=('$id', '$key', '$v1', '$v2', '$v3', '$v4'); nPriceMarkup='{$this->nPriceMarkup}'; nPagesize='{$this->nPagesize}'; nImportLife='{$this->nImportLife}'; nTimeLimit='{$this->nTimeLimit}'; b_showbtn='$b_showbtn'\n");
        parent::__construct($id, $key);
        
        $t0 = time();
        ($id = $this->findAttrSet(self::ATTR_SET_NAME)) ||
        ($id = $this->createAttrSet(self::ATTR_SET_NAME));
        if ($id) $this->idAttrSet = $id;
        ($id = $this->findAttrGroup(self::ATTR_GROUP_NAME)) ||
        ($id = $this->createAttrGroup(self::ATTR_GROUP_NAME, $this->idAttrSet));
        if ($id) $this->idAttrGroup = $id;
        Mage::log(" idAttrSet='".$this->idAttrSet."'; idAttrGroup='".$this->idAttrGroup."'\n");
        if (!$this->idAttrSet || !$this->idAttrGroup) return;
        foreach (self::$A_ATTRS as $name => $arr) {
            $code = self::ATTR_PREFIX. $name;
            if (!$this->loadAttrId($code))
                $this->createAttr($code, $arr['label'], $arr['data']);
            if (!isset($arr['is_select'])) continue;
            $this->loadAttrOptions($code);
        }
        Mage::log(" duration=".(time() - $t0)." secs\n<__construct()-end.");
    }

    /**
     * @return bool
     */
    public function isEnabled() {
        return $this->bEnable;
    }

    /**
     * @return bool
     */
    public function runImport() {
        if (!$this->bEnable || !$this->getApiStatus()) return false;
        if (!$this->idAttrSet || !$this->idAttrGroup) return false;
        $this->loadState();
        $t0 = time();
        Mage::log("\n>runImport(): nTimeNow=".self::formatStamp($t0)."; nTimeRun=".self::formatStamp($this->nTimeRun)."; nTimeStop=".self::formatStamp($this->nTimeStop)."; nTimeEnd=".self::formatStamp($this->nTimeEnd)."; nTimeDiff=".($t0 - $this->nTimeEnd)."\n");
        if ($this->nTimeRun && $t0- $this->nTimeRun- $this->nTimeLimit <= 10)
            return false;
        if ($this->nTimeEnd) {
            if ($this->nImportLife && $t0 - $this->nTimeEnd <= $this->nImportLife)
                return false;
            $this->iStatePage = 0;
        }
        if ($this->nTimeStop && $this->iStatePage &&
            $this->nImportLife && $t0 - $this->nTimeStop <= $this->nImportLife)
            $this->iStatePage = 0;
        set_time_limit($this->nTimeLimit? $this->nTimeLimit + 30 : 0);
        $this->nTimeRun = $t0; $this->nTimeStop = $this->nTimeEnd = 0;
        $this->saveState();
        try {
            $this->procImport();
            $this->nTimeEnd = time(); $this->iStatePage = 0;
        }
        catch (Exception $obj) {
            Mage::log("\n>Exception(runImport): msg(".$obj->getCode()."): ".$obj->getMessage()."\n");
        }
        $this->nTimeRun = 0; $this->nTimeStop = time();
        $this->saveState();
        Mage::log(" duration=".(time() - $t0)." secs\n<runImport()-end.\n");
        return true;
    }

    /**
     * @param array $idOrder - ID of the last order in Magento
     * @return bool
     */
    public function runExport($idOrder) {
        if (!$this->bEnable || !$this->getApiStatus()) return false;
        Mage::log("runExport(): idOrder='$idOrder'\n");
        try {
            return $this->procExport($idOrder);
        }
        catch (Exception $obj) {
            Mage::log("\n>Exception(runExport): msg(".$obj->getCode()."): ".$obj->getMessage()."\n");
            return false;
        }
    }


    /**
     * @return array|false
     */
    protected function procImport() {
        $n = self::N_MAXPAGES;
        Mage::log(">procImport(): iStatePage='{$this->iStatePage}'; n=$n\n");
        $url = sprintf(self::URI_PRODUCTS, $this->nPagesize, $this->iStatePage* $this->nPagesize);
        for (; (!$n || $this->iStatePage < $n) && $url; $this->iStatePage++) {
            Mage::log("Page #{$this->iStatePage}: url='$url': duration=".(time() - $this->nTimeRun)." secs\n");
            $a_resp = $this->request($url, 'product', 'Read');
            if (!$a_resp) break;
            Mage::log(" a_resp: ".print_r($a_resp,true)."\n");
            if (!is_array($a_resp['products'])) break;
            $a_items = $a_resp['products'];
            Mage::log(" a_items: ".rtrim(print_r($a_items,true))."\n");
            foreach ($a_items as $i => $a_item) {
                if (!is_numeric($i)) continue;
                Mage::log("Item #$i:\n");
                $this->procProduct($a_item);
            }
            if (!is_array($a_items['links'])) break;
            Mage::log(" links: ".rtrim(print_r($a_items['links'],true))."\n");
            $url = false;
            foreach ($a_items['links'] as $a_item) {
                if ($a_item['method'] != 'listNextProducts') continue;
                $url = htmlspecialchars_decode($a_item['url']); break;
            }
        }
    }

    /**
     * @param array $idOrder - ID of the last order in Magento
     * @return bool
     */
    protected function procExport($idOrder) {
        if (!$idOrder) return false;
        $order = Mage::getModel('sales/order')->load($idOrder);
        $carrier = self::filterCarrier($order->getShippingCarrier()->getCarrierCode());
        $method = self::filterShipMethod($order->getData('shipping_description'));
        $a_ship = array_filter(array(
            'carrier' => $carrier,
            'service' => $method,
            //'signature' => 0,
            //'instructions' => '',
        ));
        $o_addr = $order->getShippingAddress();
        $name = $o_addr->getName();
        $a_addr = array_filter(array(
            'first_name' => self::filterName($o_addr->getData('firstname'), $name),
            'last_name' => self::filterName($o_addr->getData('lastname'), $name, true),
            'address_1' => $o_addr->getStreetFull(),
            'city' => $o_addr->getCity(),
            'state' => self::filterRegion($o_addr->getRegionCode()),
            'zipcode' => $o_addr->getData('postcode'),
            'country' => self::filterCountry($o_addr->getCountry()),
        ), 'trim');
        $a_addr_opt = array_filter(array(
            //'company' => '',
            //'address_2' => '',
            //'ext_zipcode' => '',
            //'phone' => $o_addr->getData('telephone'),
        ), 'trim');
        $attrcode = self::ATTR_PREFIX. $this->getRsvAttrName(1);
        Mage::log(">procExport(): idOrder='$idOrder'; carrier='$carrier'; method='$method'; name='$name'; attrcode='$attrcode'\n a_addr: ".rtrim(print_r($a_addr,true))."\n a_addr_opt: ".rtrim(print_r($a_addr_opt,true))."\n");
        if (!$carrier || !count($a_addr)) return false;
        $a_items = array();
        foreach ($order->getAllItems() as $i => $item) {
            $o_prod = Mage::getModel('catalog/product')->load($item->getProductId());
            $id_attrset = $o_prod->getAttributeSetId();
            $sku = $o_prod->getSku();
            $pack = $o_prod->getData($attrcode);
            Mage::log("Item #$i: id_attrset='$id_attrset'; sku='$sku'; pack='$pack'\n");
            if ($id_attrset != $this->idAttrSet || !$sku) continue;
            $a_items[] = array(
                'sku' => $sku,
                'quantity' => intval($item->getQtyToInvoice()* ($pack? $pack : 1))
            );
        }
        Mage::log(" a_items(".count($a_items)."): ".rtrim(print_r($a_items,true))."\n");
        if (!count($a_items)) return false;
        if (self::B_POSTJSON)
            $data = array(
                //'po_number' => '',
                'ship_options' => $a_ship,
                'ship_to_address' => array_merge($a_addr, $a_addr_opt),
                'items' => $a_items,
            );
        else
            $data = sprintf(self::XML_ORDER,
                self::buildPlainXml($a_ship),
                self::buildPlainXml(array_merge($a_addr, $a_addr_opt)),
                array_reduce($a_items, function ($r, $arr) {
                    return $r .= self::buildPlainXml($arr, 'item');
                })
            );
        $a_resp = $this->request(
            self::URI_ORDERS, 'order', 'Create', self::B_POSTJSON, $data
        );
        $b_ok = $this->getLastResponse()->getStatus() != 201;
        Mage::log(" b_ok='$b_ok'; a_resp: ".rtrim(print_r($a_resp,true))."\n");
        return $b_ok;
    }

    static protected function filterCarrier($v) {
        if (!$v) return '';
        Mage::log(">filterCarrier(): v='$v'\n");
        return in_array($v = strtoupper(trim($v)), self::$A_CARRIERS)? $v : '';
    }

    static protected function filterShipMethod($v) {
        Mage::log(">filterShipMethod(): v='$v'\n");
        foreach (self::$A_METHODS as $s) {
            if (stripos($v, $s)) return $s;
        }
        return self::$A_METHODS[0];
    }

    static protected function filterName($v, $fullname, $bLast = false) {
        if ($v) return $v;
        $arr = array_filter(explode(' ', $fullname), 'trim');
        return count($arr)? ($bLast? array_pop($arr) : $arr[0]) : '';
    }

    static protected function filterRegion($v) {
        return strlen($v) == 2? $v : '';
    }

    static protected function filterCountry($v) {
        return ($v = strtoupper(trim($v))) == 'US' || $v == 'USA'? $v : '';
    }

    protected function procProduct($aData) {
        $this->checkStop();
        if (!is_array($aData)) return false;
        $aData = array_map(
            function ($v) { return is_array($v)? $v : trim($v); },
            $aData
        );
        $id = $this->findProduct($aData['sku']);
        if ($id)
            $this->updateProduct($id, $aData);
        else
            $this->addProduct($aData);
    }

    protected function findProduct($sku) {
        $a_ids = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToFilter('sku', $sku)
            ->getAllIds();
        Mage::log("findProduct(): sku='$sku'; a_ids: ".rtrim(print_r($a_ids,true))."\n");
        return is_array($a_ids) && count($a_ids)? $a_ids[0] : false;
    }

    protected function addProduct($aData) {
        if (!is_array($aData) || !count($aData) || !$aData['inventory']) return false;
        Mage::log("addProduct(): aData: ".rtrim(print_r($aData,true))."\n");
        list($price, $packsize, $qty) = $this->parsePrice($aData);
        $cate = $aData['category']; $subcate = $aData['subcategory'];
        ($id_cate = $this->findCategory($cate)) || ($id_cate = $this->createCategory($cate));
        $id_cate && ($id_subcate = $this->findCategory($subcate)) || ($id_subcate = $this->createCategory($subcate, $id_cate));
        Mage::log(" price='$price'; packsize='$packsize'; qty='$qty'; id_cate='$id_cate'; id_subcate='$id_subcate'; attrname='".$this->getRsvAttrName(1)."'");
        $o_prod = Mage::getModel('catalog/product');
        $o_prod->setSku($sku = $aData['sku'])
            ->setName($aData['title'])
            ->setDescription($aData['description'])
            ->setShortDescription($aData['title'])
            ->setWeight($aData['unit_weight'])
            ->setPrice($price)
            ->setStockData(array('is_in_stock' => $qty? 1 : 0, 'qty' => $qty));
        if ($id_subcate) $o_prod->setCategoryIds(array($id_cate, $id_subcate));
        $o_prod->setAttributeSetId($this->idAttrSet);
        foreach (self::$A_ATTRS as $name => $arr) {
            if (!isset($aData[$name]) || !$aData[$name]) continue;
            $code = self::ATTR_PREFIX. $name;
            if (!isset($arr['is_select'])) {
                $o_prod->setData($code, $aData[$name]);
                continue;
            }
            $a_ids = $this->addAttrOptions($code, self::splitVal($aData[$name]));
            if (!$a_ids) continue;
            $o_prod->setData($code, count($a_ids)>1? array($code => implode(',', $a_ids)) : $a_ids[0]);
        }
        $o_prod->setData(self::ATTR_PREFIX. $this->getRsvAttrName(1), $packsize);
        $o_prod->setCreatedAt(strtotime('now'))
            ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
            ->setTaxClassId(0)
            ->setStatus(1)
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
            ->setWebsiteIds(array(1));
        $o_prod->setMediaGallery(array('images'=>array(), 'values'=>array()));
        $a_img = array_pop($aData['images']);
        $url_img = $a_img['url'];
        $path_img = $this->saveImage($url_img, $sku);
        $o_prod->addImageToMediaGallery($path_img, self::$A_MEDIA_ATTRS, true, false);
        $o_prod->save();
        $id = $o_prod->getId();
        Mage::log(" url_img='$url_img'; path_img='$path_img'\n id(Product)='$id'\n");
        if (!$aData['tags']) return $id;
        $id_store = Mage::app()->getStore()->getId();
        $id_user = Mage::getSingleton('admin/session')->getUser()->getId();
        Mage::log("Tags: id_store='$id_store'; id_user='$id_user'\n");
        $o_tag = Mage::getModel('tag/tag');
        $o_rel = Mage::getModel('tag/tag_relation');
        foreach (self::splitVal($aData['tags']) as $i => $tag) {
            $o_tag->unsetData();
            if (!$o_tag->loadByName($tag)->getId()) {
                $o_tag->setName($tag)
                    //->setFirstCustomerId($id_user)->setFirstStoreId($id_store)
                    ->setStatus($o_tag->getApprovedStatus())
                    ->save();
            }
            $id_tag = $o_tag->getId();
            Mage::log("Tag #$i: '$tag': id_tag='$id_tag'\n");
            $o_rel->unsetData()
                //->setStoreId($id_store)->setCustomerId($id_user)
                ->setProductId($id)
                ->setActive(Mage_Tag_Model_Tag_Relation::STATUS_ACTIVE)
                ->setCreatedAt($o_rel->getResource()->formatDate(time()))
                ->setTagId($id_tag)
                ->save();
        }
        return $id;
    }

    protected function updateProduct($id, $aData) {
        $attrname = $this->getRsvAttrName(0);
        $s_t = $aData[$attrname];
        $o_prod = Mage::getModel('catalog/product')->load($id);
        $s_t0 = $o_prod->getData(self::ATTR_PREFIX. $attrname);
        Mage::log("updateProduct(): id='$id'; attrname='$attrname'; s_t='$s_t' (".strtotime($s_t)."); s_t0='$s_t0' (".strtotime($s_t0).")\n");
        if (strtotime($s_t) <= strtotime($s_t0)) return;
        list($price, $packsize, $qty) = $this->parsePrice($aData);
        $a_stock = array('is_in_stock' => $qty? 1 : 0,'qty' => $qty);
        Mage::log(" price='$price'; packsize='$packsize'; qty='$qty'; a_stock: ".rtrim(print_r($a_stock,true))."\n");
        $o_prod->setStockData($a_stock)
            ->setPrice($price)
            ->setData(self::ATTR_PREFIX. $attrname, $s_t)
            ->setData(self::ATTR_PREFIX. $this->getRsvAttrName(1), $packsize)
            ->save();
    }

    protected function parsePrice($aData) {
        $price = 1; $pack = 1; $qty = 0;
        if (is_array($aData['tiers']) && count($aData['tiers'])) {
            $a_tier = $aData['tiers'][0];
            $price = $a_tier['price'];
            if ($this->nPriceMarkup) $price *= (1+ $this->nPriceMarkup/ 100);
            $pack = intval($a_tier['quantity']);
            if ($aData['inventory'])
                $qty = intval($aData['inventory'] / $pack);
        }
        return array(number_format($price, 2), $pack, $qty);
    }

    protected function findCategory($name) {
        $o_coll = Mage::getModel('catalog/category')->getCollection()
            ->setStoreId(0)
            ->addAttributeToFilter('is_active',1)
            ->addAttributeToFilter('name',$name);
        $a_ids = $o_coll->getAllIds();
        Mage::log("findCategory(): name='$name'; a_ids: ".rtrim(print_r($a_ids,true))."\n");
        return is_array($a_ids) && count($a_ids)? $a_ids[0] : false;
    }

    protected function createCategory($name, $idParent = 0) {
        $path = $idParent? Mage::getModel('catalog/category')->load($idParent)->getPath() : '1/3';
        Mage::log("createCategory(): name='$name'; idParent='$idParent'; path='$path'\n");
        $a_data = array(
            'name' => $name,
            'path' => $path,
            //'description' => $name,
            //'landing_page' => '',
            'display_mode' => 'PRODUCTS_AND_PAGE',
            'is_active' => '1',
            'is_anchor' => '0',
            //'url_key' => strtolower($name),
            'include_in_menu' => '1',
            'custom_use_parent_settings' => '0',
            'custom_apply_to_products' => '0',
            //'image' => null,
        );
        $o_cate = Mage::getModel('catalog/category');
        $o_cate->setStoreId(0);
        $o_cate->addData($a_data);
        $o_cate->setAttributeSetId($o_cate->getDefaultAttributeSetId());
        if (!$o_cate->validate()) return false;
        $o_cate->save();
        $id = $o_cate->getId();
        Mage::log(" id(Attribute)='$id'\n");
        return $id;
    }

    protected function getRsvAttrName($i) {
        $a_names = array_keys(self::$A_ATTRS);
        return $a_names[$i];
    }

    protected function createAttr($code, $label, $aData) {
        $a_data = array(
            'attribute_code' => $code,
            'is_global' => '1',
            'frontend_input' => 'text',
            'default_value_text' => '',
            'default_value_yesno' => '0',
            'default_value_date' => '',
            'default_value_textarea' => '',
            'is_unique' => '0',
            'is_required' => '0',
            'apply_to' => array('simple'),
            'is_configurable' => '0',
            'is_searchable' => '0',
            'is_visible_in_advanced_search' => '0',
            'is_filterable' => '0',
            'is_filterable_in_search' => '0',
            'is_comparable' => '0',
            'is_used_for_price_rules' => '0',
            'is_wysiwyg_enabled' => '0',
            'is_html_allowed_on_front' => '1',
            'is_visible_on_front' => '0',
            'used_in_product_listing' => '0',
            'used_for_sort_by' => '0',
            'frontend_label' => array($label),
            'default_value' => '',
        );
        if ($aData) $a_data = array_merge($a_data, $aData);
        $type = $a_data['frontend_input'];
        $o_attr = Mage::getModel('catalog/resource_eav_attribute');
        if (is_null($o_attr->getIsUserDefined()) || $o_attr->getIsUserDefined() != 0)
            $a_data['backend_type'] = $o_attr->getBackendTypeByInput($type);
        Mage::log("createAttr(): code='$code'; label='$label'; type='$type'\n a_data: ".rtrim(print_r($a_data,true))."\n");
        $o_attr->addData($a_data);
        $o_attr->setEntityTypeId(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId());
        $o_attr->setIsUserDefined(1);
        try {
            $o_attr->save();
        }
        catch (Exception $e) {
            Mage::log("Exception: msg: ".$e->getMessage());
            return false;
        }
        $id = $o_attr->getId();
        if (!$id) return false;
        Mage::log(" id(Attribute)='$id'\n");
        Mage::getModel('eav/entity_setup','core_setup')
            ->addAttributeToSet('catalog_product',$this->idAttrSet,$this->idAttrGroup,$id);
        return $id;
    }

    protected function findAttrSet($name) {
        $id = Mage::getModel('eav/entity_attribute_set')
            ->load($name, 'attribute_set_name')->getAttributeSetId();
        Mage::log("findAttrSet(): id='$id'\n");
        return $id? $id : false;
    }

    protected function createAttrSet($name) {
        $id_entitytype = Mage::getModel('catalog/product')->getResource()->getTypeId();
        $id_deft = Mage::getModel('eav/entity_setup','core_setup')->getAttributeSetId($id_entitytype, 'Default');
        Mage::log("createAttrSet(): name='$name'; id_entitytype='$id_entitytype'; id_deft='$id_deft'\n");
        $o_attrset = Mage::getModel('eav/entity_attribute_set');
        $o_attrset->setEntityTypeId($id_entitytype);
        $o_attrset->setAttributeSetName($name);
        if (!$o_attrset->validate()) return false;
        try {
            $o_attrset->save();
            $id = $o_attrset->getId();
            if (!$id) return false;
            Mage::log(" id(AttributeSet)='$id'\n");
            $o_attrset->initFromSkeleton($id_deft);
            $o_attrset->save();
            return $id;
        }
        catch (Exception $e) {
            Mage::log("Exception: msg: ".$e->getMessage());
            return false;
        }
    }

    protected function findAttrGroup($name) {
        $id = Mage::getModel('eav/entity_attribute_group')
            ->load($name, 'attribute_group_name')->getAttributeGroupId();
        Mage::log("findAttrGroup(): id='$id'\n");
        return $id? $id : false;
    }

    protected function createAttrGroup($name, $idSet) {
        if (!$idSet) return false;
        Mage::log("createAttrGroup(): name='$name'; idSet='$idSet'\n");
        $o_attrgrp = Mage::getModel('eav/entity_attribute_group');
        $o_attrgrp->setAttributeGroupName($name)
            ->setAttributeSetId($idSet)->setSortOrder(100);
        try {
            $o_attrgrp->save();
            return $o_attrgrp->getId();
        }
        catch (Exception $e) {
            Mage::log("Exception: msg: ".$e->getMessage());
            return false;
        }
    }

    protected function addAttrOptions($sAttrCode, $aLabels) {
        if (!is_array($aLabels) || !count($aLabels) || !isset($this->aAttrOptionsets[$sAttrCode])) return false;
        Mage::log("addAttrOptions(): sAttrCode='$sAttrCode'; aLabels:".rtrim(print_r($aLabels,true))."\n");
        $ra_options = &$this->aAttrOptionsets[$sAttrCode];
        $a_ids = array();
        foreach ($aLabels as $s) {
            if ($this->addAttrOption($sAttrCode, $s, $ra_options))
                $this->loadAttrOptions($sAttrCode);
            if (isset($ra_options[$s])) $a_ids[$ra_options[$s]] = $s;
        }
        Mage::log(" a_ids:".rtrim(print_r($a_ids,true))."\n");
        return count($a_ids)? array_keys($a_ids) : false;
    }

    protected function addAttrOption($sAttrCode, $sLabel, $aOptions) {
        if (!is_array($aOptions))
            return false;
        if (isset($aOptions[$sLabel]))
            return false;
        if (!$sAttrCode)
            return false;
        $sAttrId = Mage::getResourceModel('eav/entity_attribute')
            ->getIdByCode('catalog_product', $sAttrCode);
        if (!$sAttrId)
            return false;
        $n_opts = count($aOptions);
        Mage::log("addAttrOption(): sAttrCode='$sAttrCode'; sAttrId='$sAttrId'; n_opts='$n_opts'; sLabel='$sLabel'\n");
        $o_attrinfo = Mage::getModel('eav/entity_attribute')->load($sAttrId);
        $value['option'] = array($sLabel,$sLabel);
        $order['option'] = $n_opts+ 1;
        $result = array('value' => $value, 'order' => $order);
        $o_attrinfo->setData('option',$result);
        $o_attrinfo->save();
        return true;
    }

    protected function loadAttrId($sAttrCode) {
        if (isset($this->aAttrIds[$sAttrCode])) return $this->aAttrIds[$sAttrCode];
        $id = Mage::getResourceModel('eav/entity_attribute')
            ->getIdByCode('catalog_product', $sAttrCode);
        if (!$id) return false;
        return $this->aAttrIds[$sAttrCode] = $id;
    }

    protected function loadAttrOptions($sAttrCode) {
        if (!$sAttrCode)
            return false;
        $sAttrId = $this->loadAttrId($sAttrCode);
        if (!$sAttrId)
            return false;
        $a_opts = Mage::getModel('catalog/resource_eav_attribute')
            ->load($sAttrId)
            ->getSource()->getAllOptions();
        Mage::log("loadAttrOptions(): sAttrCode='$sAttrCode'; sAttrId='$sAttrId'\n a_opts(" . count($a_opts) . "):" . print_r($a_opts, true) . "\n");
        if (!count($a_opts))
            return false;
        $a_ret = array();
        foreach ($a_opts as $arr) {
            if (isset($arr['label']) && $arr['label'])
                $a_ret[$arr['label']] = $arr['value'];
        }
        Mage::log(" a_ret:".rtrim(print_r($a_ret,true))."\n");
        $this->aAttrOptionsets[$sAttrCode] = $a_ret;
        return $a_ret;
    }

    protected function saveImage($url, $sku) {
        if (!$url) return false;
        $cont = file_get_contents($url);
        if (!$cont) return false;
        Mage::log("saveImage(): url='$url'; sku='$sku'\n");
        $ext = ($k = strrpos($url, '.'))? substr($url, $k+1) : 'jpg';
        $filepath = Mage::getBaseDir('tmp'). DS. $sku. '.'. $ext;
        file_put_contents($filepath, $cont);
        return $filepath;
    }


    public function getState() {
        return array($this->nTimeRun, $this->nTimeStop, $this->nTimeEnd, $this->iStatePage);
    }

    protected function setState($aData) {
        list($this->nTimeRun, $this->nTimeStop, $this->nTimeEnd, $this->iStatePage) = $aData;
    }

    protected function saveState() {
        $a_data = $this->getState();
        Mage::log("saveState(): a_data: ".rtrim(print_r($a_data,true))."\n");
        Koleimports_Dropship_Model_Config::setVal('rsv_import_state', serialize($a_data));
    }

    protected function loadState() {
        $s = Koleimports_Dropship_Model_Config::getVal('rsv_import_state');
        if (!$s) return false;
        $a_data = unserialize($s);
        if (!is_array($a_data) || count($a_data) != 4) return false;
        Mage::log("loadState(): a_data: ".rtrim(print_r($a_data,true))."\n");
        $this->setState($a_data);
        return true;
    }

    protected function checkStop() {
        if ($this->nTimeLimit && time() - $this->nTimeRun - $this->nTimeLimit > 0)
            throw new Exception('StopByTimeout', 1);
    }

    static protected function splitVal($s) {
        return array_map('trim', explode(',', $s));
    }

    static protected function formatStamp($v) {
        return $v.' ('.date('Y.m.d H:i:s',$v).')';
    }
}
