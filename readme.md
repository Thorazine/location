<p align="center">
<a href="https://packagist.org/packages/thorazine/location"><img src="https://poser.pugx.org/thorazine/location/d/total.svg" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/thorazine/location"><img src="https://poser.pugx.org/thorazine/location/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/thorazine/location"><img src="https://poser.pugx.org/thorazine/location/license.svg" alt="License"></a>
</p>


# Geo data to Geolocation
Get a complete standardized location php array or object from coordinates, address, postal code or IP. Through the Location Facade you can
request the Google and IpInfo API to return the address of a visitor on your website.
This script works out of the box, no need for any keys or registrations.


## What you should keep in mind

This script uses the Google geodata and maps API to request information. Especially with the IP API there is
margin for error. The Google API is quite accurate. However, please
don't use this data as fact but rather as indication.


## How to make it work
Run:
```
composer require thorazine/location
```
That's it

## If you are on Laravel < 5.5
You can't use this version. Please go to the [pre55 branch](https://github.com/Thorazine/location/tree/pre55)

## Other optional stuff
Get the configuration:
```
php artisan vendor:publish --tag=location
```

If you have a Google key add a line to your .env file:
```
GOOGLE_KEY=[key]
```

> This script used to work out of the box without a key, but it doesn't anymore. Thanks Google.
> You can request one [here](https://developers.google.com/maps/documentation/geocoding/get-api-key)
> Do make sure it has sufficiant rights.


These (quick examples):
```php
$location = Location::locale('nl')->coordinatesToAddress(['latitude' => 52.385288, 'longitude' => 4.885361])->get();

$location = Location::locale('nl')->addressToCoordinates(['country' => 'Nederland', 'street' => 'Nieuwe Teertuinen', 'street_number' => 25])->get();

$location = Location::locale('nl')->postalcodeToCoordinates(['postal_code' => '1013 LV', 'street_number' => '25'])->coordinatesToAddress()->get();

$location = Location::locale('nl')->postalcodeToCoordinates(['postal_code' => '1013 LV', 'street_number' => '25'], true)->get();

$location = Location::locale('nl')->ipToCoordinates('46.44.160.221')->coordinatesToAddress()->get(); // if IP resolves properly, which it mostly doesn't

$location = Location::locale('nl')->ipToCoordinates('46.44.160.221', true)->get(); // if IP resolves properly, which it mostly doesn't
```


Will all result in:
```php
$location['latitude'] = 52.385288,
$location['longitude'] = 4.885361;
$location['iso'] = 'NL';
$location['country'] = 'Nederland';
$location['region'] = 'Noord-Holland';
$location['city'] = 'Amsterdam';
$location['street'] = 'Nieuwe Teertuinen';
$location['street_number'] = '25';
$location['postal_code'] = '1013 LV';
```

To return it as object set the ```get()``` function to true: ```get(true)```


## Limit results by country
To limit the search results to only be included when from a set of predefined countries, use the ```countries()``` function.
It accepts iso notation country names as defined by "ISO 3166-1 alpha-2".


## Extended example:
```php
try {
	$location = Location::coordinatesToAddress(['latitude' => 52.385288, 'longitude' => 4.885361])->get(true);

	if($error = Location::error()) {
		dd($error);
	}
}
catch(Exception $e) {
	dd($e->getMessage());
}
```

The result is the default template and starts out as empty and gets filled throughout the call. So if no data is available
the result for that entry will be "". After every call the script resets to it's initial template.


## Chainable functions and their variables

| Functions 					| Values		| Validation	| Type
|-------------------------------|---------------|---------------|---------
| countries()	 				| iso's 		| required		| array
| coordinatesToAddress()		| latitude		| required		| float
|								| longitude		| required		| float
| addressToCoordinates()		| country		| recommended	| string
|								| region		| 				| string
|								| city			| required		| string
|								| street 		| recommended	| string
|								| street_number	| recommended	| string
| postalcodeToCoordinates()		| postal_code	| required		| string
|								| street_number	| recommended	| string
| get()							| true/false	| boolean		| boolean


## Other functions

| Functions 					| Values		| Result
|-------------------------------|---------------|----------------------------------------------
| error()						| none			| Returns any error if there is one
| response()					| none			| Returns the raw response from the Google API



## Debug
With the try catch you can already see what you need. But besides this there is also a cached result of the raw response from the
google API. Please note that this is not the case with the ip request.

```php
$location = Location::coordinatesToAddress(['latitude' => 52.385288, 'longitude' => 4.885361])->get();
Location::response(); // results in raw api response
```
