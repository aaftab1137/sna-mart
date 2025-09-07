<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController as BaseController;
use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Request;
use App\Models\BoostingPlan;
use Validator;

class BoostingPlanController extends BaseController
{
    public function createBoostingPlan(Request $request)
    {

        $validator = Validator::make($request->all(), [

            'price'         => 'required|numeric|min:1|unique:boosting_plans,price',
            'views_per_day' => 'required|string|unique:boosting_plans,views_per_day',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400, 'message' => $validator->errors()->first()], 400);
        }

        $boostingPlan = BoostingPlan::create($request->all());

        return response()->json(['status' => 200, 'message' => 'Boosting Plan Created Successfully!', 'plan' => $boostingPlan]);
    }

    public function getBoostingPlans()
    {
        $plans = BoostingPlan::orderBy('created_at', 'desc')->get();
        return $this->sendResponse('All Boosting Plan', $plans);
    }

    public function deleteBoostingPlan($id)
    {
        $plans = BoostingPlan::find($id);

        if (!$plans) {
            return response()->json([
                'status'    => 404,
                'message'   => 'Boosting Plan not found',
            ], 404);
        }

        $plans->delete();

        return response()->json([
            'status'    => 200,
            'message'   => 'Bossting Plan Deleted Successfully!'
        ]);
    }

    public function updateBoostingPlan(Request $request, $id)
    {
        $plans = BoostingPlan::find($id);

        if (!$plans) {
            return response()->json(['status' => 404, 'message' => 'Boosting Plan not found'], 404);
        }

        $validator = Validator::make($request->all(), [

            'price'         => 'required|numeric|min:1|unique:boosting_plans,price,'.$request->id,
            'views_per_day' => 'required|string|unique:boosting_plans,views_per_day,'.$request->id,
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 404, 'message' => $validator->errors()->first()], 404);
        }

        $plans->update($request->all());

        return response()->json(['status' => 200, 'message' => 'Boosting Plan Updated Successfully!', 'plan' => $plans]);
    }
}
