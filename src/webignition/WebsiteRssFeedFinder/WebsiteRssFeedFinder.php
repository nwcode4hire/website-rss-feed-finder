<?php
namespace webignition\WebsiteRssFeedFinder;

use webignition\NormalisedUrl\NormalisedUrl;
use webignition\WebResource\WebPage\WebPage;

/**
 *  
 */
class WebsiteRssFeedFinder {
    
    /**
     *
     * @var \webignition\Http\Client\Client
     */
    private $httpClient = null;
    
    
    /**
     *
     * @var \webignition\NormalisedUrl\NormalisedUrl 
     */
    private $rootUrl = null;
    
    
    /**
     *
     * @var WebPage
     */
    private $rootWebPage = null;
    
    
    /**
     * Collection of feed urls
     * 
     * @var array
     */
    private $feedUrls = array();
    
    
    private $supportedFeedTypes = array(
        'application/rss+xml',
        'application/atom+xml'
    );
    
    
    /**
     *
     * @param string $rootUrl
     * @return \webignition\WebsiteSitemapFinder\WebsiteSitemapFinder 
     */
    public function setRootUrl($rootUrl) {        
        $this->rootUrl = new NormalisedUrl($rootUrl);
        $this->feedUrls = array();
        $this->rootWebPage = null;
        return $this;
    }
    
    
    /**
     *
     * @return string
     */
    public function getRootUrl() {
        return (is_null($this->rootUrl)) ? '' : (string)$this->rootUrl;
    }
    
    
    /**
     *
     * @param \webignition\Http\Client\Client $client 
     */
    public function setHttpClient(\webignition\Http\Client\Client $client) {
        $this->httpClient = $client;
    }
    
    
    /**
     *
     * @return \webignition\Http\Client\Client 
     */
    private function getHttpClient() {
        if (is_null($this->httpClient)) {
            $this->httpClient = new \webignition\Http\Client\Client();
            $this->httpClient->redirectHandler()->enable();
        }
        
        return $this->httpClient;
    }

    
    /**
     *
     * @return string
     */
    public function getRssFeedUrls() {
        return $this->getLinkHref('alternate', 'application/rss+xml');
    }
    
    
    /**
     *
     * @return string
     */
    public function getAtomFeedUrls() {
        return $this->getLinkHref('alternate', 'application/atom+xml');
    }
    
    
    /**
     *
     * @param string $rel
     * @param string $type
     * @return string 
     */
    private function getLinkHref($rel, $type) {        
        if (!isset($this->feedUrls[$rel]) || !isset($this->feedUrls[$rel][$type])) {            
            if (!$this->findFeedUrls()) {
                return null;
            }
        }
        
        if (!isset($this->feedUrls[$rel]) || !isset($this->feedUrls[$rel][$type])) { 
            return null;
        }
        
        return $this->feedUrls[$rel][$type];
    }
    
    
    private function findFeedUrls() {        
        $rootWebPage = $this->getRootWebPage();
        if ($rootWebPage == false) {
            return false;
        }        
        
        libxml_use_internal_errors(true);
        
        $feedUrls = array();
        $supportedFeedTypes = $this->supportedFeedTypes;
        try {            
            $rootWebPage->find('link[rel=alternate]')->each(function ($index, \DOMElement $domElement) use (&$feedUrls, $supportedFeedTypes) {                
                foreach ($supportedFeedTypes as $supportedFeedType) {                
                    if ($domElement->getAttribute('type') == $supportedFeedType) {
                        if (!isset($feedUrls['alternate'])) {
                            $feedUrls['alternate'] = array();
                        }
                        
                        if (!isset($feedUrls['alternate'][$supportedFeedType])) {
                            $feedUrls['alternate'][$supportedFeedType] = array();
                        }
                        
                        if (!in_array($domElement->getAttribute('href'), $feedUrls['alternate'][$supportedFeedType])) {
                            $feedUrls['alternate'][$supportedFeedType][] = $domElement->getAttribute('href');
                        }
                    }                    
                }
            });           
        } catch (QueryPath\ParseException $parseException) {
            // Invalid XML              
        }
        
        libxml_use_internal_errors(false);        
        return $this->feedUrls = $feedUrls;
    }
    
    
    /**
     *
     * @return WebPage 
     */
    private function getRootWebPage() {        
        if (is_null($this->rootWebPage)) {
            $this->rootWebPage = $this->retrieveRootWebPage();
        }
        
        return $this->rootWebPage;
    }
    
    
    /**
     *
     * @return boolean|\webignition\WebResource\WebPage\WebPage 
     */
    private function retrieveRootWebPage() {
        $request = new \HttpRequest($this->getRootUrl());        
        
        try {
            $response = $this->getHttpClient()->getResponse($request);
        } catch (\webignition\Http\Client\Exception $httpClientException) {
            return false;
        }
        
        if (!$response->getResponseCode() == 200) {
            return false;
        }
        
        try {
            $webPage = new WebPage();
            $webPage->setContentType($response->getHeader('content-type'));
            $webPage->setContent($response->getBody());

            return $webPage;            
        } catch (\webignition\WebResource\Exception $exception) {
            // Invalid content type (is not the URL of a web page)
            return false;
        }        
    }
    
}