<?php

class VTI_VTISeoCore_Model_Observer extends Mage_Core_Model_Abstract
{
    protected $helper;

    public function _construct()
    {
        $this->helper = Mage::helper("vtiseocore");
        parent::_construct();
    }


    private function setRobots($layout)
    {
        return $layout->getUpdate()->addUpdate('<reference name="head"><action method="setRobots"><value>NOINDEX,FOLLOW</value></action></reference>');
    }


    public function changeRobots($observer)
    {
        $action = $observer->getEvent()->getAction();
        $page = $action->getFullActionName();
        $layout = $observer->getEvent()->getLayout();

        switch ($page)
        {
            case "catalog_category_view" :

                if ($this->helper->getConfig("noindexparams")
                && parse_url($action->getRequest()->getRequestUri(), PHP_URL_QUERY)) {
                    $this->setRobots($layout);
                }

                break;

            case "catalogsearch_result_index" :
                if ($this->helper->getConfig("noindexparamssearch")) {
                    $this->setRobots($layout);
                }
                break;

        }

        if ($this->helper->getConfig("noindexparamsparameterpages")
            && parse_url($action->getRequest()->getRequestUri(), PHP_URL_QUERY)) {
            $this->setRobots($layout);
        }

        return $this;
    }

    /* The function to remove the meta keywords tag */

    public function applyTag($observer) {
        if ($this->helper->getConfig('metakw')) {
            $body = $observer->getResponse()->getBody();
            if (strpos(strtolower($body), 'meta name="keywords"') !== false) {
                $body = preg_replace('{(<meta name="keywords"[^>]*?>\n)}i', '', $body);
                
            }
            if (strpos(strtolower($body), 'meta name="description" content=""') !== false) {
                $body = preg_replace('{(<meta name="description"[^>]*?>\n)}i', '', $body);
            }
            
            $observer->getResponse()->setBody($body);
        }
    }

    /* Checks if the page loaded is the canonical version, if not redirects to that version */
    
    public function forceProductCanonical(Varien_Event_Observer $observer)
    {
        if (Mage::getStoreConfig('catalog/seo/product_canonical_tag') && !Mage::getStoreConfig('product_use_categories'))
        {
            if ($this->helper->getConfig('forcecanonical')) {
                // check for normal catalog/product/view controller here
                if(!stristr("catalog",Mage::app()->getRequest()->getModuleName()) && Mage::app()->getRequest()->getControllerName() != "product") return;
                // Maintain querystring if one is set (to maintain tracking URLs such as gclid)
                $querystring = ($_SERVER['QUERY_STRING'] ? '?'.$_SERVER['QUERY_STRING'] : '');
                $product = $observer->getEvent()->getProduct();
                $url = Mage::helper('core/url')->escapeUrl($product->getUrlModel()->getUrl($product, array('_ignore_category'=>true)).$querystring);
                if(Mage::helper('core/url')->getCurrentUrl() != $url){
                    Mage::app()->getFrontController()->getResponse()->setRedirect($url,301);
                    Mage::app()->getResponse()->sendResponse();
                }
            }
        }
    }

    /* On relevant pages, will override the page title with the fallback if one isn't set in the editor */
    
    public function setTitle($observer)
    {
        $actionName = $observer->getEvent()->getAction()->getFullActionName();

        if ($actionName === "cms_index_index"
        || $actionName === "contacts_index_index") {
            return false;
        }

        $layout = $observer->getEvent()->getLayout();
        $title = $this->getTitle();

        if ($title) {
            if ($head = $layout->getBlock('head')) {
                $head->setTitle($title);
            }
        }
    }

    /* On relevant pages, will override the meta desc with the fallback if one isn't set in the editor */
    
    public function setDescription($observer)
    {
        $actionName = $observer->getEvent()->getAction()->getFullActionName();

        if ($actionName === "contacts_index_index") {
            return false;
        }

        $layout = $observer->getEvent()->getLayout();
        $description = $this->getDescription();

        if($description) {
            if ($head = $layout->getBlock('head')) {
                $head->setDescription($description);
            }
        }
    }
	
	public function getDefaultTitle()
    {
        return Mage::getStoreConfig('design/head/default_title');
    }
    
    public function getTitle()
    {
        $pagetype = $this->metaHelper()->getPageType();
        
        if ($pagetype !== false) {
        
            if ($pagetype->_code != "cms") {
                if (!$pagetype->_model->getMetaTitle()) {
                    $this->_data['title'] = $this->setConfigTitle($pagetype->_code);
                } else {
                    $this->_data['title'] = $pagetype->_model->getMetaTitle();
                }
            } else if($pagetype !== false && $pagetype->_code == "cms"){
                $this->_data['title'] = $pagetype->_model->getTitle();
            }

            if (empty($this->_data['title'])) {

                // check if it's a category or product and default to name.
                if($pagetype->_code == "category" || $pagetype->_code == "product"){
                    $this->_data['title'] = $pagetype->_model->getName();
                } else {
                    $this->_data['title'] = $this->getDefaultTitle();
                }
            }
        } else {
            $this->_data['title'] = $this->getDefaultTitle();
        }
        
        return htmlspecialchars(html_entity_decode(trim($this->_data['title']), ENT_QUOTES, 'UTF-8'));
    }
    
    public function setConfigTitle($pagetype)
    {
        if ($this->metaHelper()->config($pagetype.'_title_enabled')) {
            return $this->metaHelper()->getDefaultTitle($pagetype);
        }
    }
    
    public function setConfigMetaDescription($pagetype)
    {
        if ($this->metaHelper()->config($pagetype.'_metadesc_enabled')) {
            return $this->metaHelper()->getDefaultMetaDescription($pagetype);
        }
    }
    
    
    public function getDescription()
    {
        $pagetype = $this->metaHelper()->getPageType();
        
        if ($pagetype !== false) {
            if (!$pagetype->_model->getMetaDescription()) {
                $this->_data['description'] = $this->setConfigMetaDescription($pagetype->_code);
            } else {
                $this->_data['description'] = $pagetype->_model->getMetaDescription();
            }
        }
        
        if (empty($this->_data['description'])) {
            $this->_data['description'] = "";
        }
        return $this->_data['description'];
    }
    
    public function metaHelper()
    {
        return Mage::helper('vtiseocore/meta');
    }
    
    public function setMandatoryAltTag($observer)
    {
        if ($this->helper->getConfig('mandatory_alt')) {
            $observer->getBlock()->setTemplate('vtiseo/catalog/product/helper/gallery.phtml');
        }
    }

}
