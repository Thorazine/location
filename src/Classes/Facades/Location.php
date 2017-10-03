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
	private $error = null;

	/**
	 * The complete response will be given here (array)
	 */
	private $response = null;


	/**
	 * If there is an error it'll be in here
	 */
	private $locale = null;

	/**
	 * The iso's we allow to return
	 */
	private $isos = [];

	/**
	 * Default values
	 */
	private $locationData = [
		'latitude' => '',
		'longitude' => '',
		'country' => '',
		'region' => '',
		'city' => '',
		'iso' => '',
		'street' => '',
		'street_number' => '',
		'postal_code' => '',
	];

	/**
	 * Url variables
	 */
	private $urlVariables = [];

	/**
	 * The array that holds the data
	 */
	private $returnLocationData = [];

	/**
	 * Hold the guzzle client
	 */
	private $client;


	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->returnLocationData = $this->locationData;
	}

	/**
	 * Set the locale in. ISO (nl, nl_NL, en, en_GB, etc.)
	 */
	public function locale($locale)
	{
		$this->locale = $locale;

		return $this;
	}

	public function countries(array $isos)
	{
		$this->urlVariables = array_merge($this->urlVariables, ['components' => 'country:'.implode(',', $isos)]);
		return $this;
	}

	/**
	 * Get the coordinates from a postal code
	 *
	 * @param string
	 * @param string/integer
	 * @return $this
	 */
	public function postalcodeToCoordinates($postalData)
	{
		$this->reset();

		$this->returnLocationData = array_merge($this->returnLocationData, ['postal_code' => $postalData['postal_code'], 'street_number' => @$postalData['street_number']]);

		$this->urlAddPostcode(str_replace(' ', '', $postalData['postal_code']));

		$this->updateResponseWithResults($this->gateway($this->buildUrl()));

		return $this;
	}

	/**
	 * Get coordinates from an address
	 *
	 * @param array
	 * @return $this
	 */
	public function addressToCoordinates(array $address = [])
	{
		$this->reset();

		$this->returnLocationData = array_merge($this->returnLocationData, $address);

		$this->urlAddAddress($address);

		$this->updateResponseWithResults($this->gateway($this->buildUrl()));

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
		$this->reset();

		$this->returnLocationData = array_merge($this->returnLocationData, $coordinates);

		$this->urlAddCoordinates($coordinates);

		$this->updateResponseWithResults($this->gateway($this->buildUrl()));

		return $this;
	}

	/**
	 * Get coordinates from an ip
	 *
	 * @param string (ip)
	 * @return $this
	 */
	public function ipToCoordinates($ip = null)
	{
		if(! $ip) {
			$ip = Request::ip();
		}

		$this->url = 'http://ipinfo.io/'.$ip.'/geo';

		if(in_array($ip, config('location.ip-exceptions'))) {
			$this->returnLocationData = array_merge($this->returnLocationData, config('location.default-template-localhost'));
		}
		else {
			$client = $this->createClient();

			$response = $this->jsonToArray($this->gateway($this->url));

			list($latitude, $longitude) = explode(',', $response['loc']);

			$this->returnLocationData['latitude'] = $latitude;
			$this->returnLocationData['longitude'] = $longitude;
		}

		return $this;
	}


	/**
	 * Return the results
	 */
	public function get($toObject = false)
	{
		if($this->error) {
			Log::error('Could not get location. There was an error.', [
	            'error' => $this->error,
	        ]);
		}

		if($toObject) {
			$response = new StdClass;

			foreach($this->returnLocationData as $key => $value) {
				$response->{$key} = $value;
			}
		}
		else {
			$response = $this->returnLocationData;
		}

		$this->locale = null;
		$this->urlVariables = [];
		$this->returnLocationData = $this->locationData;

		return $response;
	}

	/**
	 * Returns the error variable
	 */
	public function error()
	{
		return $this->error;
	}

	/**
	 * Returns the response variable
	 */
	public function response()
	{
		return $this->response;
	}

	/**
	 * Get the data from the gateway
	 * New gateways (like Guzzle) can be added here and can be chosen from the config
	 */
	private function gateway($url)
	{
		$result = $this->createClient()->get($url);

		if($result->getStatusCode() != 200) {
			throw new Exception('Could not connect');
		}

		return $result->getBody()->getContents();


	}

	/**
	 * Add the locale to the request
	 */
	private function addLanguage()
	{
		if($this->locale) {
			$this->urlVariables = array_merge($this->urlVariables, ['language' => $this->locale]);
		}
		elseif(config('location.language')) {
			$this->urlVariables = array_merge($this->urlVariables, ['language' => config('location.language')]);
		}
		else {
			$this->urlVariables = array_merge($this->urlVariables, ['language' => App::getLocale()]);
		}
	}

	/**
	 * Add the request variables for the postal code request
	 */
	private function urlAddPostcode($postalCode)
	{
		$this->urlVariables = array_merge($this->urlVariables, ['address' => $postalCode]);
	}

	/**
	 * Add the request variables for the address request
	 */
	private function urlAddAddress($address)
	{
		$this->urlVariables = array_merge($this->urlVariables, ['address' => implode(' ', array_values($address))]);
	}

	/**
	 * Add the request variables for the coordinates request
	 */
	private function urlAddCoordinates($coordinates)
	{
		if($coordinates) {
			$this->urlVariables = array_merge($this->urlVariables, ['latlng' => $coordinates['latitude'].','.$coordinates['longitude']]);
		}
		elseif($this->returnLocationData['latitude'] && $this->returnLocationData['longitude']) {
			$this->urlVariables = array_merge($this->urlVariables, ['latlng' => $this->returnLocationData['latitude'].','.$this->returnLocationData['longitude']]);
		}
		else {
			throw new Exception('No coordinates could be found');
		}
	}

	/**
	 * Build the request url
	 */
	private function buildUrl()
	{
		$this->addLanguage();

		$variables = '';

		foreach($this->urlVariables as $variable => $value) {
			if(! $variables) {
				$variables .= '?'.$variable.'='.($value);
			}
			else {
				$variables .= '&'.$variable.'='.($value);
			}
		}

		// dd($variables);
		return config('location.google-request-url').$variables;
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
				throw new Exception("Could not find complete address");
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
		else {
			$this->error = 'No results';
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

	private function reset()
	{
		$this->error = null;
		$this->response = null;
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
}
