<?php
/*
 * File name: BookingAPIController.php
 * Last modified: 2021.06.01 at 11:47:24
 * Author: SmarterVision - https://codecanyon.net/user/smartervision
 * Copyright (c) 2021
 */

namespace App\Http\Controllers\API;


use App\Criteria\Bookings\BookingsOfUserCriteria;
use App\Criteria\EServices\EServicesOfUserCriteria;
use App\Criteria\EServices\NearCriteria;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\EProvider;
use App\Models\PromptBooking;
use App\Notifications\NewBooking;
use App\Notifications\StatusChangedBooking;
use App\Repositories\AddressRepository;
use App\Repositories\BookingRepository;
use App\Repositories\BookingStatusRepository;
use App\Repositories\CouponRepository;
use App\Repositories\CustomFieldRepository;
use App\Repositories\EProviderRepository;
use App\Repositories\EServiceRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\OptionRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\PaymentStatusRepository;
use App\Repositories\TaxRepository;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InfyOm\Generator\Criteria\LimitOffsetCriteria;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Prettus\Validator\Exceptions\ValidatorException;
use function Illuminate\Support\Facades\Log;

/**
 * Class BookingController
 * @package App\Http\Controllers\API
 */
class BookingAPIController extends Controller
{
    /** @var  BookingRepository */
    private $bookingRepository;

    /**
     * @var CustomFieldRepository
     */
    private $customFieldRepository;

    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var BookingStatusRepository
     */
    private $bookingStatusRepository;
    /**
     * @var PaymentRepository
     */
    private $paymentRepository;
    /**
     * @var NotificationRepository
     */
    private $notificationRepository;
    /**
     * @var AddressRepository
     */
    private $addressRepository;
    /**
     * @var TaxRepository
     */
    private $taxRepository;
    /**
     * @var EServiceRepository
     */
    private $eServiceRepository;
    /**
     * @var EProviderRepository
     */
    private $eProviderRepository;
    /**
     * @var CouponRepository
     */
    private $couponRepository;
    /**
     * @var OptionRepository
     */
    private $optionRepository;
    /**
     * @var PaymentStatusRepository
     */
    private $paymentStatusRepository;

    public function __construct(BookingRepository $bookingRepo, CustomFieldRepository $customFieldRepo, UserRepository $userRepo
        , BookingStatusRepository $bookingStatusRepo, NotificationRepository $notificationRepo, PaymentRepository $paymentRepo, AddressRepository $addressRepository, TaxRepository $taxRepository, EServiceRepository $eServiceRepository, EProviderRepository $eProviderRepository, CouponRepository $couponRepository, OptionRepository $optionRepository, PaymentStatusRepository $paymentStatusRepository)
    {
        parent::__construct();
        $this->bookingRepository = $bookingRepo;
        $this->customFieldRepository = $customFieldRepo;
        $this->userRepository = $userRepo;
        $this->bookingStatusRepository = $bookingStatusRepo;
        $this->notificationRepository = $notificationRepo;
        $this->paymentRepository = $paymentRepo;
        $this->addressRepository = $addressRepository;
        $this->taxRepository = $taxRepository;
        $this->eServiceRepository = $eServiceRepository;
        $this->eProviderRepository = $eProviderRepository;
        $this->couponRepository = $couponRepository;
        $this->optionRepository = $optionRepository;
        $this->paymentStatusRepository = $paymentStatusRepository;
    }

    /**
     * Display a listing of the Booking.
     * GET|HEAD /bookings
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $this->bookingRepository->pushCriteria(new RequestCriteria($request));
            $this->bookingRepository->pushCriteria(new BookingsOfUserCriteria(auth()->id()));
            $this->bookingRepository->pushCriteria(new LimitOffsetCriteria($request));
        } catch (RepositoryException $e) {
            return $this->sendError($e->getMessage());
        }
        $bookings = $this->bookingRepository->all();

        return $this->sendResponse($bookings->toArray(), 'Bookings retrieved successfully');
    }

    /**
     * Display the specified Booking.
     * GET|HEAD /bookings/{id}
     *
     * @param int $id
     *
     * @return JsonResponse
     */
    public function show($id, Request $request)
    {
        try {
            $this->bookingRepository->pushCriteria(new RequestCriteria($request));
            $this->bookingRepository->pushCriteria(new LimitOffsetCriteria($request));
        } catch (RepositoryException $e) {
            return $this->sendError($e->getMessage());
        }
        $booking = $this->bookingRepository->findWithoutFail($id);
        if (empty($booking)) {
            return $this->sendError('Booking not found');
        }
        $this->filterModel($request, $booking);
        return $this->sendResponse($booking->toArray(), 'Booking retrieved successfully');


    }

    /**
 * Store a newly created Booking in storage.
 *
 * @param Request $request
 *
 * @return JsonResponse
 */
    public function store(Request $request): JsonResponse
    {
        try {
            $input = $request->all();
            $this->validate($request, [
                'address.address' => Address::$rules['address'],
                'address.longitude' => Address::$rules['longitude'],
                'address.latitude' => Address::$rules['latitude'],
            ]);
            $address = $this->addressRepository->updateOrCreate(['address' => $input['address']['address']], $input['address']);
            if (empty($address)) {
                return $this->sendError(__('lang.not_found', ['operator', __('lang.address')]));
            } else {
                $input['address'] = $address;
            }
            $eService = $this->eServiceRepository->find($input['e_service']);
            $eProvider = $eService->eProvider;
            $taxes = $eProvider->taxes;
            $input['e_provider'] = $eProvider;
            $input['taxes'] = $taxes;
            $input['e_service'] = $eService;
            $input['booking_Key'] = Str::random(10);
            $input['to_customer'] = 1;
            if (isset($input['options'])) {
                $input['options'] = $this->optionRepository->findWhereIn('id', $input['options']);
            }
            $input['booking_status_id'] = $this->bookingStatusRepository->find(1)->id;
            if (isset($input['coupon_id'])) {
                $input['coupon'] = $this->couponRepository->find($input['coupon_id']);
            }
            $booking = $this->bookingRepository->create($input);
            if (setting('enable_notifications', false)) {
                Notification::send($eProvider->users, new NewBooking($booking));
            }
        } catch (ValidatorException | ModelNotFoundException $e) {
            return $this->sendError($e->getMessage());
        } catch (ValidationException $e) {
            return $this->sendError(array_values($e->errors()));
        }

        return $this->sendResponse($booking->toArray(), __('lang.saved_successfully', ['operator' => __('lang.booking')]));
    }

    /**
     * Store a newly created Booking in storage.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function promptBooking(Request $request): JsonResponse
    {
        try {
            $this->eServiceRepository->pushCriteria(new RequestCriteria($request));
            $this->eServiceRepository->pushCriteria(new EServicesOfUserCriteria(auth()->id()));
            $this->eServiceRepository->pushCriteria(new NearCriteria($request));
            $eServices = $this->eServiceRepository->with('eProvider')->whereBetween('price', $request->range)->whereAvailable(1)
//                ->whereHas('eProvider', function ($query) {
//                    $query->whereAvailable(1);
//                })
                ->whereHas('categories', function ($query) use ($request) {
                    $query->whereId($request->category_id);
                })
                ->inRandomOrder()->take(10)->get();

            if ($eServices->count()) {
                $input = $request->all();
                $this->validate($request, [
                    'address.address' => Address::$rules['address'],
                    'address.longitude' => Address::$rules['longitude'],
                    'address.latitude' => Address::$rules['latitude'],
                ]);
                $address = $this->addressRepository->updateOrCreate(['address' => $input['address']['address'], 'user_id' => auth()->id()], $input['address']);
                if (empty($address)) {
                    return $this->sendError(__('lang.not_found', ['operator', __('lang.address')]));
                } else {
                    $input['address'] = $address;
                }

                if (isset($input['options'])) {
                    $input['options'] = $this->optionRepository->findWhereIn('id', $input['options']);
                }
                $input['booking_status_id'] = $this->bookingStatusRepository->find(1)->id;//???????!
                if (isset($input['coupon_id'])) {
                    $input['coupon'] = $this->couponRepository->find($input['coupon_id']);
                }

                $booking_Key = Str::random(40);
                $input['booking_key'] = $booking_Key;
                $input['user_id'] = auth()->id();

                $promptBooking = PromptBooking::create(array_merge($input, ['category_id' => $request->category_id, 'price_range' => json_encode($request->range)]));

                $promptBooking = PromptBooking::with('category')->find($promptBooking->id);

                foreach ($eServices as $eService) {
                    $eProvider = $eService->eProvider;
                    $taxes = $eProvider->taxes;
                    $input['e_provider'] = $eProvider;
                    $input['taxes'] = $taxes;
                    $input['e_service'] = $eService;
                    $booking = $this->bookingRepository->create($input);
                    if (setting('enable_notifications', false)) {
                        Notification::send($eProvider->users, new NewBooking($booking));
                    }
                }
                return $this->sendResponse($promptBooking->toArray(), __('lang.saved_successfully', ['operator' => __('lang.booking')]));
            } else {
                return $this->sendError('No Service Found');
            }

        } catch (ValidatorException | ModelNotFoundException $e) {
            return $this->sendError($e->getMessage());
        } catch (ValidationException $e) {
            return $this->sendError(array_values($e->errors()));
        } catch (RepositoryException $e) {
            return $this->sendError($e->getMessage());
        }

    }

    /**
     * Update the specified Booking in storage.
     *
     * @param int $id
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function update($id, Request $request): JsonResponse
    {
        $oldBooking = $this->bookingRepository->findWithoutFail($id);
        if (empty($oldBooking)) {
            return $this->sendError('Booking already accepted');
        }
        // $oldStatus = $oldBooking->payment->status;
        $input = $request->all();
        try {
            if ($oldBooking->to_customer && ($oldBooking->e_provider->users()->first()->id == auth()->id() || $oldBooking->user_id == auth()->id())) {
                $booking = $this->bookingRepository->update($input, $id);
            } else {
                $BookingsByKeyNotReceivedCount = $this->bookingRepository->whereBookingKey($oldBooking->booking_key)
                    ->whereHas('bookingStatus', function ($query){
                        $query->where('status', '!=', 'Received');
                    })->count();
                if (!$BookingsByKeyNotReceivedCount) {
                    $booking = $this->bookingRepository->update(array_merge($input, ['to_customer' => 1]), $id);
                    $promptBooking = PromptBooking::whereBookingKey($booking->booking_key)->first();
                    $promptBooking->delete();
                    $providerBookings = $this->bookingRepository->whereBookingKey($booking->booking_key)->whereToCustomer(0)->get();
                    foreach ($providerBookings as $providerBooking)
                    {
                        $providerBooking->delete();
                    }
                } else {
                    return $this->sendError('Booking already accepted');
                }
            }

            if (setting('enable_notifications', false)) {
                if (isset($input['booking_status_id']) && $input['booking_status_id'] != $oldBooking->booking_status_id) {
                    if ($booking->bookingStatus->order < 40) {
                        Notification::send([$booking->user], new StatusChangedBooking($booking));
                    } else {
                        Notification::send($booking->e_provider->users, new StatusChangedBooking($booking));
                    }
                }
            }
            return $this->sendResponse($booking->toArray(), __('lang.saved_successfully', ['operator' => __('lang.booking')]));


        } catch (ValidatorException $e) {
            return $this->sendError($e->getMessage());
        }

    }

}
