<?php



namespace App\Http\Controllers\Auth;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Modules\Hotels\Models\Hotel;
use App\Services\S3Service;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Http\Controllers\AppBaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;
use Stevebauman\Location\Facades\Location;

class RegisterController extends AppBaseController
{
    public function register(RegisterRequest $request)
    {
        DB::beginTransaction();
        try {
            // Create new user
            $user = User::create([
                'full_name'        => $request->name,
                'email'            => $request->email,
                'phone'            => $request->phone,
                'password'         => Hash::make($request->password),
                'status'           => 'Active'
            ]);

            // Generate API token
            $token = $user->createToken('API Token')->plainTextToken;

            DB::commit();

            return $this->sendResponse([
                'token' => $token,
                'user' => $user,
            ], 'User created successfully.');

        } catch (Exception $e) {
            DB::rollBack();

            // Log the error
            Log::error('Error in updating Register: ', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong!!!',
            ], 500);
        }
    }
    public function userInfo()
    {
        DB::beginTransaction();
        try {
            $userId = auth()->user()->id;
            $user = User::with('hotel', 'hotel.images')->where('id', $userId)->first();

            DB::commit();

            return $this->sendResponse($user, 'User retrieved successfully.');

        } catch (Exception $e) {
            DB::rollBack();

            // Log the error
            Log::error('Error in updating userInfo: ', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong!!!',
            ], 500);
        }
    }
    public function userProfileUpdate(RegisterRequest $request, S3Service $s3)
    {
        DB::beginTransaction();
        try {
            $auth = auth()->user();
            if ($auth->id != $request->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action.',
                ], 403);
            }
            $user = User::where('id', $request->user_id)->first();

            $image_url = $user->image_url;
            $image_path = $user->image_path;
            if($request->hasFile('image')) {
                $file = $request->file('image');
                $result = $s3->upload($file, 'profile');

                if ($result) {
                    $image_url = $result['url'];
                    $image_path = $result['path'];
                }
            }

            $user->update([
                'full_name'    => $request->full_name ?? $user->full_name,
                'email'        => $request->email ?? $user->email,
                'image_url'    => $image_url,
                'image_path'   => $image_path,
                'address'      => $request->address ?? $user->address,
            ]);

            DB::commit();

            return $this->sendResponse([
                'user' => $user,
            ], 'User updated successfully.');

        } catch (Exception $e) {
            DB::rollBack();

            // Log the error
            Log::error('Error in updating User: ', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong!!!',
            ], 500);
        }
    }
    public function changePassword(RegisterRequest $request)
    {
        DB::beginTransaction();
        try {
            $auth = auth()->user();
            if ($auth->id != $request->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action.',
                ], 403);
            }
            $user = User::where('id', $request->user_id)->first();
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password does not match.',
                ], 400);
            }

            $user->update([
                'password'    => Hash::make($request->new_password),
            ]);

            $request->user()->tokens()->delete();

            DB::commit();

            return $this->sendResponse([
                'user' => $user,
            ], 'Password changed successfully.');

        } catch (Exception $e) {
            DB::rollBack();

            // Log the error
            Log::error('Error in updating User: ', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong!!!',
            ], 500);
        }
    }

}
