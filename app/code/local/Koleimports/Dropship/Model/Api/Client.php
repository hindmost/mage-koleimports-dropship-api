<?php
class Koleimports_Dropship_Model_Api_Client
{
    const XML_DECL = '<?xml version="1.0" encoding="utf-8"?>';
    const URL_ROOT = 'https://api.koleimports.com';
    const TYPE_JSON = 'application/vnd.koleimports.ds.%s+json';
    const TYPE_XML = 'application/vnd.koleimports.ds.%s+xml';
    static protected $ACTIONS = array(
        'Create' => 'POST', 'Read' => 'GET', 'Update' => 'PUT', 'Delete' => 'DELETE'
    );

    protected $oHttp = 0;

    /**
     * @param string $id
     * @param string $key
     */
    function __construct($id, $key) {
        if (!$key) return;
        $this->oHttp = new Zend_Http_Client(null, array(
            'timeout' => 40,
            'adapter' => 'Zend_Http_Client_Adapter_Curl',
            'curloptions' => array(
                //CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $id. ':'. $key,
                CURLOPT_HEADER => true,
                CURLINFO_HEADER_OUT => true,
            )
        ));
        Mage::log("Koleimports_Dropship_Model_Api_Client::__construct('$id', '$key'):\n");
    }

    /**
     * @return bool
     */
    public function getApiStatus() {
        return (bool)$this->oHttp;
    }

    /**
     * @return Zend_Http_Response
     */
    public function getLastResponse() {
        return $this->oHttp->getLastResponse();
    }

    /**
     * @param string $sUri - resource URI
     * @param string $sResrc - resource name
     * @param string $sAction - action
     * @param bool $bJson - set content-type to JSON (instead of XML)
     * @param array|string $data - POST request data
     * @return array|string|false
     */
    protected function request($sUri, $sResrc, $sAction = 0, $bJson = true, $data = 0) {
        if (!$this->oHttp) return false;
        if (!$sUri || !$sResrc) return false;
        Mage::log("Koleimports_Dropship_Model_Api_Client::request(): params=('$sUri', '$sResrc', '$sAction', '$bJson')\n data: ".rtrim(print_r($data,true))."\n");
        $url = self::fixUrl($sUri);
        $this->oHttp->setUri($url);
        if (isset(self::$ACTIONS[$sAction]))
            $this->oHttp->setMethod(self::$ACTIONS[$sAction]);
        $type = sprintf($bJson? self::TYPE_JSON : self::TYPE_XML, $sResrc);
        $this->oHttp->setHeaders('Accept', $type);
        if ($data) {
            $this->oHttp->setRawData($bJson? json_encode($data) : $data)
                ->setHeaders('Content-Type', $type);
        }
        $s_resp = $this->oHttp->request()->getBody();
        Mage::log("\n Request Headers:\n".rtrim($this->oHttp->getLastRequest())."\n Response Headers:\n".rtrim($this->oHttp->getLastResponse()->getHeadersAsString(true))."\n");
        if (!$bJson) Mage::log(" Response(".strlen($s_resp)."):\n$s_resp\n");
        return $s_resp? ($bJson? json_decode($s_resp, true) : $s_resp) : false;
    }

    static protected function fixUrl($url) {
        return (!$url || self::isUrl($url))? $url : self::URL_ROOT. $url;
    }

    static protected function isUrl($url) {
        return strpos($url, 'http') === 0;
    }

    /**
     * @param array $arr
     * @param array $tagWrap
     * @return string
     */
    static protected function buildPlainXml($arr, $tagWrap = '') {
        $out = '';
        foreach ($arr as $key => $val)
            $out .= "<$key>$val</$key>";
        return $tagWrap? "<$tagWrap>$out<$tagWrap>" : $out;
    }
}
