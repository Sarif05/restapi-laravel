<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Wallet;
use Melihovv\Base64ImageDecoder\Base64ImageDecoder;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JwtExceptions;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'pin' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->messages()], 400);
        }

        DB::beginTransaction();
        try {
            $profilePicture = $request->profile_picture ? $this->uploadBase64Image($request->profile_picture) : null;
            $ktp = $request->ktp ? $this->uploadBase64Image($request->ktp) : null;

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'username' => $request->email,
                'password' => bcrypt($request->password),
                'profile_picture' => $profilePicture,
                'ktp' => $ktp,
                'verified' => $ktp ? true : false,
            ]);
            Wallet::create([
                'user_id' => $user->id,
                'balance' => 0,
                'pin' => $request->pin,
                'card_number' => $this->generateCardNumber(16),
            ]);
            DB::commit();
            $token = JWTAuth::fromUser($user);
            $ttl = JWTAuth::factory()->getTTL() * 60;
            $userResponse = getUser($user->email);
            $userResponse->token = $token;
            $userResponse->token_expires_in = $ttl;
            $userResponse->token_type = 'bearer';
            return response()->json($userResponse, 201);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        $validator = Validator::make($credentials, [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->messages()], 400);
        }
        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json(['message' => 'Login Credentials are invalid'], 400);
            }
            $ttl = JWTAuth::factory()->getTTL() * 60;
            $userResponse = getUser($request->email);
            $userResponse->token = $token;
            $userResponse->token_expires_in = $ttl;
            $userResponse->token_type = 'bearer';
            return response()->json($userResponse, 200);
        } catch (JWTException $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    private function generateCardNumber($length){
        $result = '';
        for ($i=0; $i < $length; $i++) { 
            $result .= mt_rand(0, 9);
        }

        $wallet = Wallet::where('card_number', $result)->exists();

        if($wallet){
            return $this->generateCardNumber($length);
        }

        return $result;
    }

    private function uploadBase64Image($base64Image){
        $decoder = new Base64ImageDecoder($base64Image, $allowedFormats = ['jpeg', 'png', 'jpg']);
        
        $decodedContent = $decoder->getDecodedContent();
        $format = $decoder->getFormat();
        $image = Str::random(10).'.'.$format;
        Storage::disk('public')->put($image, $decodedContent);

        return $image;
    }

    public function logout(){
        auth()->logout();
        return response()->json(['message' => 'logout success']);
    }
}
