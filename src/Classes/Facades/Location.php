<?php

namespace Thorazine\Location\Classes\Facades;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Exception;
use StdClass;
use Request;
use Log;
use App;

class Location
{
	/**
	 * If there is an error it'll be in here
	 */
	private $error = NULL;

	/**
	 * The complete response will be given here (array)
	 */
	private $response = NULL;

	/**
	 * If there is an error it'll be in here
	 */
	private $locale = NULL;

	/**
	 *
	 */
	private $ip = NULL;

	/**
	 * all included country iso's
	 */
	private $isos = [];

	/**
	 * The array that holds the data
	 */
	private $returnLocationData = [];

	/**
	 * Hold the guzzle client
	 */
	private $client;

    /**
     * The method for request (GET|POST)
     * @var string
     */
    private $method = 'GET';

	/**
	 * Default template
	 */
	private $locationDataTemplate = [
		'latitude' => '',
		'longitude' => '',
		'iso' => '',
		'country' => '',
		'region' => '',
		'city' => '',
		'street' => '',
		'street_number' => '',
		'postal_code' => '',
	];

	/**
	 * flag to see if we need to follow up and get the address
	 */
	private $shouldRunC2A = false;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->reset();
	}

	/**
	 * Set the locale in. ISO (nl, nl-NL, en, en-GB, etc.)
	 */
	public function locale($locale)
	{
		$this->locale = $locale;
		return $this;
	}

	public function countries(array $isos)
	{
		$this->isos = $isos;
		return $this;
	}

	/**
	 * Get the coordinates from a postal code
	 *
	 * @param string
	 * @param string/integer
	 * @return $this
	 */
	public function postalcodeToCoordinates(array $postalData, $shouldRunC2A = false)
	{
		$this->shouldRunC2A = ($this->shouldRunC2A) ? true : $shouldRunC2A;

		$this->returnLocationData = array_merge($this->returnLocationData, array_only($postalData, ['street_number', 'postal_code']));
		return $this;
	}

	/**
	 * Get coordinates from an address
	 *
	 * @param array
	 * @return $this
	 */
	public function addressToCoordinates(array $addressData = [])
	{
		$this->returnLocationData = array_merge($this->returnLocationData, array_only($addressData, ['country', 'region', 'city', 'street', 'street_number']));
		return $this;
	}

	/**
	 * Get the address from coordinates
	 *
	 * @param array
	 * @return $this
	 */
	public function coordinatesToAddress(array $coordinates = [])
	{
		$this->shouldRunC2A = true;

		$this->returnLocationData = array_merge($this->returnLocationData, array_only($coordinates, ['latitude', 'longitude']));
		return $this;
	}

	/**
	 * Get coordinates from an ip
	 *
	 * @param string (ip)
	 * @return $this
	 */
	public function ipToCoordinates($ip = null, $shouldRunC2A = false)
	{
		$this->shouldRunC2A = ($this->shouldRunC2A) ? true : $shouldRunC2A;

		if(! $ip) {
			$ip = Request::ip();
		}
		$this->ip = $ip;

		return $this;
	}

	/**
	 * Return the results
	 */
	public function get($toObject = false)
	{
		$this->response = null;
		$this->error = null;

		if($this->c2a()) {
			$response = $this->template($toObject);
		}
		elseif($this->a2c()) {
			$response = $this->template($toObject);
		}
		elseif($this->i2c()) {
			$response = $this->template($toObject);
		}
		elseif($this->p2c()) {
			$response = $this->template($toObject);
		}

		if($response) {
			$this->reset();
			return $response;
		}

		throw new Exception(trans('location::errors.not_enough_data'));
	}

	/**
	 * Returns the response variable
	 */
	public function response()
	{
		return $this->response;
	}

	/**
	 * Return the template as array or object
	 *
	 * @param  bool $toObject
	 * @return mixed
	 */
	private function template($toObject)
	{
		if($toObject) {
			$response = new StdClass;

			foreach($this->returnLocationData as $key => $value) {
				$response->{$key} = $value;
			}
			return $response;
		}
		return $this->returnLocationData;
	}

	/**
	 * postal code to coordinate
	 *
	 * @return bool
	 */
	private function p2c()
	{
		if($this->returnLocationData['postal_code'] && ! $this->returnLocationData['latitude']) {
            $this->method = 'GET';
			$this->updateResponseWithResults($this->gateway($this->createAddressUrl()));

			if($this->shouldRunC2A) {
				$this->c2a();
			}

			return true;
		}
	}

	/**
	 * address to coordinates
	 *
	 * @return bool
	 */
	private function a2c()
	{
		if($this->returnLocationData['city'] && ! $this->returnLocationData['latitude']) {
            $this->method = 'GET';
			$this->updateResponseWithResults($this->gateway($this->createAddressUrl()));

			if($this->shouldRunC2A) {
				$this->c2a();
			}

			return true;
		}
	}

	/**
	 * ip to coordinates
	 *
	 * @return bool
	 */
	private function i2c()
	{
		if($this->ip && ! $this->returnLocationData['latitude']) {

            if(!env('GOOGLE_KEY')) {
                throw new Exception("Need an env key for geo request", 401);
            }

            $this->method = 'POST';

			$this->updateResponseWithResults($this->gateway($this->createGeoUrl()));

			if($this->shouldRunC2A) {
				$this->c2a();
			}

			return true;
		}
	}

	/**
	 * coordinates to address
	 *
	 * @return bool
	 */
	private function c2a()
	{
		if($this->returnLocationData['latitude'] && $this->returnLocationData['longitude']) {
            $this->method = 'GET';
			$this->updateResponseWithResults($this->gateway($this->createAddressUrl()));
			return true;
		}
	}

    /**
     * create the url to connect to for geo location
     *
     * @return string Url
     */
    private function createGeoUrl()
    {
        return config('location.google-geo-url').env('GOOGLE_KEY');
    }

	/**
	 * create the url to connect to for maps api
	 *
	 * @return string Url
	 */
	private function createAddressUrl()
	{
		$urlVariables = [
			'language' => $this->locale,
		];

		if(count($this->isos)) {
			$urlVariables['components'] = 'country:'.implode(',', $this->isos);
		}

		// if true it will always be the final stage in getting the address
		if($this->returnLocationData['latitude'] && $this->returnLocationData['longitude']) {
			$urlVariables['latlng'] = $this->returnLocationData['latitude'].','.$this->returnLocationData['longitude'];
			return $this->buildUrl($urlVariables);
		}

		if($this->returnLocationData['city'] && $this->returnLocationData['country']) {
			$urlVariables['address'] = $this->returnLocationData['city'].', '.$this->returnLocationData['country'];
			return $this->buildUrl($urlVariables);
		}

		if($this->returnLocationData['postal_code']) {
			$urlVariables['address'] = $this->returnLocationData['postal_code'].(($this->returnLocationData['street_number']) ? ' '.$this->returnLocationData['street_number'] : '');
			return $this->buildUrl($urlVariables);
		}


	}

	/**
	 * Build the request url
	 */
	private function buildUrl($urlVariables)
	{
		$url = '';

		foreach($urlVariables as $variable => $value) {
			$url .= '&'.$variable.'='.($value);
		}
		return config('location.google-maps-url').env('GOOGLE_KEY').$url;
	}

	/**
	 * Get the data from the gateway
	 * New gateways (like Guzzle) can be added here and can be chosen from the config
	 */
	private function gateway($url)
	{
        if($this->method == 'POST') {
            $result = $this->createClient()->post($url);
        }
        else {
            $result = $this->createClient()->get($url);
        }

		if($result->getStatusCode() != 200) {
			throw new Exception(trans('location::errors.no_connect'));
		}

		$this->response = $result->getBody()->getContents();

		return $this->response;
	}

	/**
	 * fill the response with usefull data as far as we can find
	 */
	private function updateResponseWithResults($json)
	{
		$response = $this->jsonToArray($json);

		if(isset($response['results'][0])) {
			$this->response = $response['results'][0];

			if(@$response['results'][0]['partial_match']) {
				throw new Exception(trans('location::errors.no_results'));
			}

			if(! $this->returnLocationData['country']) {
				$this->returnLocationData['country'] = $this->findInGoogleSet($response, ['country']);
				$this->returnLocationData['iso'] = $this->findInGoogleSet($response, ['country'], 'short_name');
			}

			if(! $this->returnLocationData['region']) {
				$this->returnLocationData['region'] = $this->findInGoogleSet($response, ['administrative_area_level_1']);
			}

			if(! $this->returnLocationData['city']) {
				$this->returnLocationData['city'] = $this->findInGoogleSet($response, ['administrative_area_level_2']);
			}

			if(! $this->returnLocationData['street']) {
				$this->returnLocationData['street'] = $this->findInGoogleSet($response, ['route']);
			}

			if(! $this->returnLocationData['street_number']) {
				$this->returnLocationData['street_number'] = $this->findInGoogleSet($response, ['street_number']);
			}

			if(! $this->returnLocationData['postal_code']) {
				$this->returnLocationData['postal_code'] = $this->findInGoogleSet($response, ['postal_code']);
			}

			if(! $this->returnLocationData['latitude']) {
				$this->returnLocationData['latitude'] = $response['results'][0]['geometry']['location']['lat'];
			}

			if(! $this->returnLocationData['longitude']) {
				$this->returnLocationData['longitude'] = $response['results'][0]['geometry']['location']['lng'];
			}
		}
        elseif(@$response['location']) {
            $this->returnLocationData['latitude'] = $response['location']['lat'];
            $this->returnLocationData['longitude'] = $response['location']['lng'];
        }
		else {
			throw new Exception(trans('location::errors.no_results'));
		}
	}

	/**
	 * Find a value in a response from google
	 *
	 * @param array (googles response)
	 * @param array (attributes to find)
	 * @return string
	 */
	private function findInGoogleSet($response, array $find = [], $type = 'long_name')
	{
		try {
			foreach($response['results'][0]['address_components'] as $data) {
				foreach($data['types'] as $key) {
					if(in_array($key, $find)) {
						return $data[$type];
					}
				}
			}
			return '';
		}
		catch(Exception $e) {
			$this->error = $e;
			return '';
		}

	}


	/**
	 * Convert json string to an array if the syntax is right
	 *
	 * @param string (json)
	 * @return array|null
	 */
	private function jsonToArray($json)
	{
		try {
			$data = json_decode($json, true);
			if(is_array($data)) {
				return $data;
			}
			else {
				$this->error = 'The given data string was not json';
				return [];
			}
		}
		catch(Exception $e) {
			$this->error = $e;
		}
	}

	/**
	 * Create a client
	 *
	 * @return void
	 */
	private function createClient()
	{
		return new Client();
	}

	/**
	 * Reset the class
	 *
	 * @return void
	 */
	private function reset()
	{
		$this->returnLocationData = $this->locationDataTemplate;
		$this->locale = (config('location.language')) ? config('location.language') : App::getLocale();
		$this->ip = null;
		$this->client = null;
	}

}
