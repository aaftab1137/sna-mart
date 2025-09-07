<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController as BaseController;
use Illuminate\Http\Request;
use App\Models\City;
use App\Models\Country;
use App\Models\State;


class HomeController extends BaseController
{
    public function privacy_policy(Request $request)
    {

        $htmlContent = view('api.privacy_policy')->render();

        return response()->json([
          'success'     => 'true',
          'message'     => 'Privacy policies fetched successfully',
          'data'        => $htmlContent
        ],200);
    }

     public function terms_and_condition(Request $request)
    {

        $htmlContent = view('api.terms_and_condition')->render();

        return response()->json([
          'success'     => 'true',
          'message'     => 'Privacy policies fetched successfully',
          'data'        => $htmlContent
        ],200);
    }



    public function get_countries(Request $request)
    {
        try {
            $getCountry = Country::all();
            return $this->sendResponse($getCountry, 'All Countries Fetched.');
        } catch (\Exception $e) {
            return $this->sendError('Error.', $e->getMessage());
        }
    }

    public function get_states(Request $request, $id)
    {
        try {
            $getStates = State::where('country_id', $id)->get();
            return $this->sendResponse($getStates, 'All States of given Country');
        } catch (\Exception $e) {

            return $this->sendError('Error.', $e->getMessage());
        }
    }

    public function get_cities(Request $request, $id)
    {
        try {
            $getCities = City::where(['state_id' => $id])->get();
            return $this->sendResponse($getCities, 'All Cities');
        } catch (\Exception $e) {
            return $this->sendError('Error.', $e->getMessage());
        }
    }
}
