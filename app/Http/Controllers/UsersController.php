<?php

namespace App\Http\Controllers;


use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UsersController extends Controller
{
    /**
     * Get profile information
     * @return JsonResponse
     */
    public function profile(): JsonResponse
    {
        return response()->json([
            'data' => auth('sanctum')->user()
        ]);
    }

    /**
     * Update profile
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'avatar' => 'required|mimes:jpeg,jpg,png|dimensions:max_width=256,max_height=256'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }
        try {
            $user = auth('sanctum')->user();
            $oldAvatar = $user->avatar ?? null;
            $filename = "avatars/" . time() . '.' . $request->file('avatar')->getClientOriginalExtension();
            Storage::disk('public')->put($filename, file_get_contents($request->file('avatar')));
            $user->update([
                'name' => $request->get('name'),
                'avatar' => $filename
            ]);
            if ($oldAvatar) {
                Storage::disk('public')->delete($oldAvatar);
            }
            return response()->json([
                'data' => $user,
                'message' => 'Profile updated successfully'
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => $exception->getMessage()
            ], 500);
        }
    }

    /**
     * Send invitation link
     * @param Request $request
     * @return JsonResponse
     */
    public function invite(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }
        $user = auth('sanctum')->user();
        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Only admin user can invite'
            ], 403);
        }
        $token = hash('sha512', time());
        User::create([
            'email' => $request->get('email'),
            'token' => $token
        ]);
        $link = route('verify', ['token' => $token, 'email' => $request->get('email')]);
        Mail::send([], [], function ($message) use ($request, $link) {
            $message->to($request->get('email'))->subject('Registration Invitation')
                ->setBody('Please click on the link to register. <a href="' . $link . '">Registration Link</a>', 'text/html');
        });
        return response()->json([
            'message' => "User invited successfully"
        ]);
    }

    /**
     * Verify invitation
     * @param Request $request
     * @return JsonResponse
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }
        $user = User::whereEmail($request->get('email'))
            ->whereToken($request->get('token'))->first();
        if ($user) {
            return response()->json([
                'data' => $user
            ]);
        }
        return response()->json([
            'message' => 'Invalid invite link'
        ], 400);
    }

    /**
     * Register account
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'username' => 'required|min:4|max:20|unique:users,username,' . $request->get('email') . ',email',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'token' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }
        $user = User::whereEmail($request->get('email'))
            ->whereToken($request->get('token'))->first();
        if ($user) {
            $pin = $this->generateUniquePin();
            $user->update([
                'name' => $request->get('name'),
                'username' => $request->get('username'),
                'pin' => $pin,
                'password' => bcrypt($request->get('password')),
                'token' => null
            ]);
            Mail::send([], [], function ($message) use ($user, $pin) {
                $message->to($user->email)->subject('Confirm PIN')
                    ->setBody('PIN: ' . $pin, 'text/html');
            });
            return response()->json([
                'message' => 'Please check the email for confirmation pin'
            ], 400);
        }
        return response()->json([
            'message' => 'Invalid token'
        ], 400);
    }

    /**
     * Generate unique pin
     * @return int
     */
    private function generateUniquePin(): int
    {
        $pin = rand(000000, 999999);
        $user = User::wherePin($pin)->first();
        if ($user) {
            return $this->generateUniquePin();
        }
        return $pin;
    }

    /**
     * Confirm registration via pin
     * @param Request $request
     * @return JsonResponse
     */
    public function confirmPin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pin' => 'required|min:6|max:6'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }
        $user = User::wherePin($request->get('pin'))->first();
        if ($user) {
            $user->update([
                'pin' => null,
                'registered_at' => Carbon::now()
            ]);
            return response()->json([
                'message' => 'PIN confirmed successfully'
            ]);
        }
        return response()->json([
            'message' => 'Invalid pin'
        ], 400);
    }
}
