<?php
/*
 * File name: UserAPIController.php
 * Last modified: 2021.07.12 at 00:23:32
 * Author: SmarterVision - https://codecanyon.net/user/smartervision
 * Copyright (c) 2021
 */

namespace App\Http\Controllers\API\EProvider;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderUserResource;
use App\Models\EProvider;
use App\Models\User;
use App\Repositories\CustomFieldRepository;
use App\Repositories\EProviderRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UploadRepository;
use App\Repositories\UserRepository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Prettus\Validator\Exceptions\ValidatorException;

class UserAPIController extends Controller
{
    /**
     * @var UserRepository
     */
    private $userRepository;
    private $providerRepository;
    private $uploadRepository;
    private $roleRepository;
    private $customFieldRepository;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(UserRepository $userRepository, EProviderRepository $providerRepository, UploadRepository $uploadRepository, RoleRepository $roleRepository, CustomFieldRepository $customFieldRepo)
    {
        $this->userRepository = $userRepository;
        $this->uploadRepository = $uploadRepository;
        $this->roleRepository = $roleRepository;
        $this->customFieldRepository = $customFieldRepo;
        $this->providerRepository = $providerRepository;
    }

    function login(Request $request)
    {
        try {
            $this->validate($request, [
                'email' => 'required|email',
                'password' => 'required',
            ]);
            if (auth()->attempt(['email' => $request->input('email'), 'password' => $request->input('password')])) {
                // Authentication passed...
                $user = auth()->user();
                if (!$user->hasRole('provider')) {
                    return $this->sendError(__('auth.account_not_accepted'), 200);
                }
                $user->device_token = $request->input('device_token', '');
                $user->save();
                return $this->sendResponse(new ProviderUserResource($user), 'User retrieved successfully');
            } else {
                return $this->sendError(__('auth.failed'), 200);
            }
        } catch (ValidationException $e) {
            return $this->sendError(array_values($e->errors()));
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 200);
        }

    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param array $data
     * @return
     */
    function register(Request $request)
    {
        try {
            $this->validate($request, User::$rules);
            $user = new User;
            $user->name = $request->input('name');
            $user->email = $request->input('email');
            $user->phone_number = $request->input('phone_number');
            $user->phone_verified_at = $request->input('phone_verified_at');
            $user->device_token = $request->input('device_token', '');
            $user->password = Hash::make($request->input('password'));
            $user->api_token = Str::random(60);
            if ($request->input('remember')) {
                $user->remember_token = Str::random(60);
            }
            $user->save();

            $user->assignRole('provider');

            $this->createProvider($user, $request);

        } catch (ValidationException $e) {
            return $this->sendError(array_values($e->errors()));
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 200);
        }


        return $this->sendResponse(new ProviderUserResource($user), 'Provider retrieved successfully');
    }

    function logout(Request $request)
    {
        $user = $this->userRepository->findByField('api_token', $request->input('api_token'))->first();
        if (!$user) {
            return $this->sendError('User not found', 200);
        }
        try {
            auth()->logout();
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 200);
        }
        return $this->sendResponse($user['name'], 'User logout successfully');

    }

    function user(Request $request)
    {
        $user = $this->userRepository->findByField('api_token', $request->input('api_token'))->first();

        if (!$user) {
            return $this->sendError('User not found', 200);
        }

        return $this->sendResponse($user, 'User retrieved successfully');
    }

    function settings(Request $request)
    {
        $settings = setting()->all();
        $settings = array_intersect_key($settings,
            [
                'default_tax' => '',
                'default_currency' => '',
                'default_currency_decimal_digits' => '',
                'app_name' => '',
                'currency_right' => '',
                'enable_paypal' => '',
                'enable_stripe' => '',
                'enable_razorpay' => '',
                'main_color' => '',
                'main_dark_color' => '',
                'second_color' => '',
                'second_dark_color' => '',
                'accent_color' => '',
                'accent_dark_color' => '',
                'scaffold_dark_color' => '',
                'scaffold_color' => '',
                'google_maps_key' => '',
                'fcm_key' => '',
                'mobile_language' => '',
                'app_version' => '',
                'enable_version' => '',
                'distance_unit' => '',
                'default_theme' => '',
            ]
        );

        if (!$settings) {
            return $this->sendError('Settings not found', 200);
        }

        return $this->sendResponse($settings, 'Settings retrieved successfully');
    }

    /**
     * Update the specified User in storage.
     *
     * @param int $id
     * @param Request $request
     *
     */
    public function update($id, Request $request)
    {
        $user = $this->userRepository->findWithoutFail($id);

        if (empty($user)) {
            return $this->sendError('User not found');
        }

        $input = $request->except(['api_token']);

        try {
            $this->validate($request, User::$updateRules);
            if (isset($input['password'])) {
                $input['password'] = Hash::make($request->input('password'));
            }

            $user = $this->userRepository->update($input, $id);

            if ($user->eProviders()->first()){
                $provider = $this->providerRepository->update($request->only([
                    'availability_range',
                    'description',
                    'available',
                    'featured'
                ]), $user->eProviders()->first()->id);

                if (isset($input['address'])) {
                    $provider->addresses()->update($request->address);
                }

                if (isset($input['availability_hours'])) {
                    $hours = $input['availability_hours'];
                    if (is_array($hours)){
                        $provider->availabilityHours()->delete();
                        foreach ($hours as $hour) {
                            $provider->availabilityHours()->create($hour);
                        }
                    }
                }
            }


        } catch (ValidatorException $e) {
            return $this->sendError($e->getMessage(), 200);
        }

        return $this->sendResponse(new ProviderUserResource($user), __('lang.updated_successfully', ['operator' => __('lang.user')]));
    }

    function sendResetLinkEmail(Request $request): JsonResponse
    {
        try {
            $this->validate($request, ['email' => 'required|email|exists:users']);
            $response = Password::broker()->sendResetLink(
                $request->only('email')
            );
            if ($response == Password::RESET_LINK_SENT) {
                return $this->sendResponse(true, 'Reset link was sent successfully');
            } else {
                return $this->sendError('Reset link not sent', 200);
            }
        } catch (ValidationException $e) {
            return $this->sendError($e->getMessage());
        } catch (Exception $e) {
            return $this->sendError("Email not configured in your admin panel settings");
        }

    }

    private function createProvider(User $user, Request $request)
    {
        $eProvider = $this->providerRepository->create([
            'name'  => $request->input('name'),
            'phone_number'  => $request->input('phone_number'),
            'availability_range'  => $request->input('availability_range'),
            'description'  => $request->input('description'),
            'e_provider_type_id' => 3,
            'accepted' => 1,
            'available' => 1,
            'featured' => 1,
        ]);

        $eProvider->users()->attach($user->id);

        $eProvider->addresses()->create(array_merge($request->address, [
            'user_id' => $user->id
        ]));

        $hours = $request->availability_hours;

        if (isset($hours)) {
            if (is_array($hours)){
                foreach ($hours as $hour) {
                    $eProvider->availabilityHours()->create($hour);
                }
            }
        }

        $image = $request->image;
        if (isset($image) && $image) {
            if (is_array($image)){
                foreach ($image as $img) {
                    $eProvider->addMedia($img)
                        ->toMediaCollection('image');
                }
            } else {
                $eProvider->addMedia($image)
                    ->toMediaCollection('image');
            }
        }

        return $eProvider;
    }
}
