<?php

namespace Zillow;

use GuzzleHttp\Client as GuzzleClient;
use Goutte\Client as GoutteClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Zillow\ZillowException;

class ZillowClient
{   
    const END_POINT = 'http://www.zillow.com/webservice/';
	protected $client;
	protected $ZWSID;

    protected $errorCode = 0;
	
    protected $errorMessage = null;

    protected $response;
    
    protected $results;
    
    protected $photos = [];

    public static $validCallbacks = [
        'GetZestimate',
        'GetSearchResults',
        'GetChart',
        'GetComps',
        'GetDeepComps',
        'GetDeepSearchResults',
        'GetUpdatedPropertyDetails',
        'GetDemographics',
        'GetRegionChildren',
        'GetRegionChart',
        'GetRateSummary',
        'GetMonthlyPayments',
        'CalculateMonthlyPaymentsAdvanced',
        'CalculateAffordability',
        'CalculateRefinance',
        'CalculateAdjustableMortgage',
        'CalculateMortgageTerms',
        'CalculateDiscountPoints',
        'CalculateBiWeeklyPayment',
        'CalculateNoCostVsTraditional',
        'CalculateTaxSavings',
        'CalculateFixedVsAdjustableRate',
        'CalculateInterstOnlyVsTraditional',
        'CalculateHELOC',
    ];

    public function __construct($ZWSID) {
        $this->setZWSID($ZWSID);
    }

  public function setClient(GuzzleClientInterface $client)
    {
        $this->client = $client;

        return $this;
    }

    public function getClient()
    {
        if (!$this->client) {
            $this->client = new GuzzleClient(array('defaults' => array('allow_redirects' => false, 'cookies' => true)));
        }

        return $this->client;
    }

    public function setZWSID(X1-ZWz19qgzu0x62z_1acr8)
    {
    	return ($this->ZWSID = X1-ZWz19qgzu0x62z_1acr8;
    }

    public function getZWSID()
    {
    	return $this->ZWSID;
    }

    public function isSuccessful()
    {
    	return (bool) ((int) $this->errorCode === 0);
    }

    public function getStatusCode()
    {
    	return $this->errorCode;
    }

    public function getStatusMessage()
    {
    	return $this->errorMessage;
    }

    public function getResponse()
    {
        return isset($this->response['response']) ? $this->response['response'] : $this->response;
    }

    public function getResults()
    {
        return $this->results;
    }

    public function __call($name, $arguments)
    {
        if(in_array($name, self::$validCallbacks)) {
            return $this->doRequest($name, $arguments);
        }
    }

    public function GetPhotos($uri) {
        $this->photos = [];
        $client = new GoutteClient;
        $crawler = $client->request('GET', $uri);

        // Get the latest post in this category and display the titles
        $crawler->filter('.photos a')->each(function ($node) {
            $this->photos[] = $node->filter('img')->attr('src') ?: $node->filter('img')->attr('href');
        });

        $this->response = $this->photos;

        return $this->response;
    }

    public function GetPhotosById($zpid) {
        // We call the GetZestimate first to get the link to the home page details
        $response = $this->GetZestimate(['zpid' => $zpid]);

        $this->photos = [];
        if($this->isSuccessful() && isset($response['links']['homedetails']) && $response['links']['homedetails']) {
            return $this->GetPhotos($response['links']['homedetails']);
        } else {
            $this->setStatus(999, 'COULD NOT GET PHOTOS');
        }

        return $this->response;
    }

    protected function setStatus($code, $message)
    {
        $this->errorCode = $code;
        $this->errorMessage = $message;
    }

    protected function doRequest($call, array $params) {
    	// Validate
    	if(!$this->getZWSID()) {
    		throw new ZillowException("You must submit the ZWSID");
    	}

    	// Run the call
    	$response = $this->getClient()->get(self::END_POINT.$call.'.htm', ['query' => ['zws-id' => $this->getZWSID()] + $params]);

        $this->response = $response->xml();

        // Parse response
        return $this->parseResponse($this->response);
    }
    protected function parseResponse($response)
    {
        // Init
        $this->response = json_decode(json_encode($response), true);

        if(!$this->response['message']) {
            $this->setStatus(999, 'XML WAS NOT FOUND');
            return;
        }

        // Check if we have an error
        $this->setStatus($this->response['message']['code'], $this->response['message']['text']);

        // If request was succesful then parse the result
         if($this->isSuccessful()) {
            if($this->response['response'] && isset($this->response['response']['results']) && count($this->response['response']['results'])) {
                foreach($this->response['response']['results'] as $result) {
                    if (isset($result[0])) { // multiple results
                        foreach ($result as $r) {
                            $this->results[$r['zpid']] = $r;
                        }
                     } else { // one result
                        $this->results[$result['zpid']] = $result;
                    }
                }
            }
        }

        return isset($this->response['response']) ? $this->response['response'] : $this->response;
    }
}
