<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController as BaseController;
use App\Models\MembershipPlan;
use App\Models\UserMembership;
use Illuminate\Http\Request;
use Validator;

class MembershipPlanController extends BaseController
{
    public function createMembershipPlan(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name'        => 'required|string|max:255|unique:membership_plans,name',
                'price'       => 'required|numeric|min:1',
                'duration'    => 'required|integer|min:1',
                'benefit'    => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message'   => $validator->errors()->first(),
                    'status'    => 400,
                    'error'     => true,
                ], 400);
            }

            $plan = MembershipPlan::create($request->all());

            return response()->json([
                'status'    => 201,
                'message'   => 'Membership plan created successfully!',
                'plan'      => $plan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while processing your request.',
                'status'  => 500,
                'error'   => true,
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllMembershipPlans()
    {
        // $plans = MembershipPlan::all();
        $plans = MembershipPlan::orderBy('created_at', 'desc')->get();
        return $this->sendResponse($plans , 'All Membership Plans');
    }

    public function getMembershipPlanById($id)
    {

        $plans = MembershipPlan::find($id);

        if (!$plans) {
            return response()->json([
                'message'       => 'Membership plan not found',
                'status'        => 404,
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Membership plan retrieved successfully',
            'plan' => $plans
        ]);
    }

    public function deleteMembershipPlan($id)
    {

        $plans = MembershipPlan::find($id);

        if (!$plans) {
            return response()->json([
                'message'  => 'Membership Plan not found',
                'status'   => 404,
                'error'    => true,
            ], 404);
        }

        $plans->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Membership plan deleted successfully!'
        ]);
    }

    public function updateMembershipPlan(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name'      => 'required|string|max:255|unique:membership_plans,name,' . $id,
                'price'     => 'required|numeric|min:0',
                'duration'  => 'required|integer|min:1', 
                'benefit'  => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 400, 'message' => $validator->errors()->first()], 400);
            }
            $plan = MembershipPlan::find($id);

            if (!$plan) {
                return response()->json(['status' => 404, 'message' => 'Membership plan not found'], 404);
            }

            $plan->update($request->all());

            return response()->json([
                'status' => 200,
                'message' => 'Membership plan updated successfully!',
                'plan' => $plan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while processing your request.',
                'status'  => 500,
                'error'   => true,
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function getPurchasedMembershipPlan(){

        $purchasedPlan = UserMembership::with('users:id,name,email')->get();
        return $this->sendResponse($purchasedPlan, 'All Purchased Plan retrived successfully');
    }
}
