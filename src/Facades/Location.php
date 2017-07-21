<?php

namespace Thorazine\Location\Facades;

use Illuminate\Support\Facades\Facade;

class LocationFacade extends Facade {


    protected static function getFacadeAccessor() 
    { 
    	return 'location'; 
    }
}