<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PromptBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromptBookingController extends Controller
{
    /**
     * Display a listing of the Booking.
     * GET|HEAD /bookings
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index()
    {
        $bookings = PromptBooking::with('category')->whereUserId(auth()->id())->get();

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
        $booking = PromptBooking::with('category')->find($id);
        if (empty($booking)) {
            return $this->sendError('Prompt Booking not found');
        }
        $this->filterModel($request, $booking);
        return $this->sendResponse($booking->toArray(), 'Prompt Booking retrieved successfully');
    }
}
