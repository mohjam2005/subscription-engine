<?php
namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::where('is_active', true)->get();
        
        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'billing_cycle' => 'required|in:monthly,yearly,weekly',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'trial_days' => 'integer|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $plan = Plan::create($request->all());
        
        return response()->json([
            'success' => true,
            'data' => $plan
        ], 201);
    }
    
    public function show($id)
    {
        $plan = Plan::find($id);
        
        if (!$plan) {
            return response()->json(['message' => 'Plan not found'], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $plan
        ]);
    }
    
    public function update(Request $request, $id)
    {
        $plan = Plan::find($id);
        
        if (!$plan) {
            return response()->json(['message' => 'Plan not found'], 404);
        }
        
        $plan->update($request->only([
            'name', 'description', 'billing_cycle', 
            'price', 'currency', 'trial_days', 'is_active'
        ]));
        
        return response()->json([
            'success' => true,
            'data' => $plan
        ]);
    }
    
    public function destroy($id)
    {
        $plan = Plan::find($id);
        
        if (!$plan) {
            return response()->json(['message' => 'Plan not found'], 404);
        }
        
        $plan->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Plan deleted'
        ]);
    }
}