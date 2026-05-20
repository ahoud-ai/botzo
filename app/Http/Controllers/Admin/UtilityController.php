<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

class UtilityController extends BaseController
{
    public function generateDummyData(Request $request)
    {
        $configuredToken = (string) config('utility.api_token', '');
        $providedToken = $request->header('X-API-Token');

        if (empty($configuredToken)) {
            return response()->json([
                'statusCode' => 503,
                'message' => __('UTILITY_API_TOKEN is not configured.'),
            ], 503);
        }

        if (! $providedToken || ! hash_equals($configuredToken, $providedToken)) {
            return response()->json([
                'statusCode' => 401,
                'message' => __('Unauthorized. Invalid X-API-Token.'),
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'organization_id' => 'required|integer|exists:organizations,id',
            'contacts' => 'nullable|integer|min:1|max:100000',
            'team_members' => 'nullable|integer|min:1|max:10000',
            'chats' => 'nullable|integer|min:1|max:500000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'statusCode' => 422,
                'message' => __('The given data was invalid.'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $exitCode = Artisan::call('data:generate-complete', [
            '--organization_id' => (int) $request->input('organization_id'),
            '--contacts' => (int) $request->input('contacts', 100),
            '--team_members' => (int) $request->input('team_members', 10),
            '--chats' => (int) $request->input('chats', 100),
        ]);

        if ($exitCode !== 0) {
            return response()->json([
                'statusCode' => 500,
                'message' => __('Failed to generate dummy data.'),
                'output' => Artisan::output(),
            ], 500);
        }

        return response()->json([
            'statusCode' => 200,
            'message' => __('Dummy data generated successfully.'),
            'output' => Artisan::output(),
        ], 200);
    }
}
