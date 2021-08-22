<?php

namespace App\Models;

use App\Casts\OptionCollectionCast;
use App\Casts\TaxCollectionCast;
use App\Models\Address;
use App\Models\BookingStatus;
use App\Models\Coupon;
use App\Models\EProvider;
use App\Models\EService;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptBooking extends Model
{

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [
        'user_id' => 'required|exists:users,id',
        'booking_status_id' => 'required|exists:booking_statuses,id',
        'payment_id' => 'nullable|exists:payments,id'
    ];
    public $table = 'prompt_bookings';
    public $fillable = [
        'options',
        'quantity',
        'user_id',
        'booking_status_id',
        'address',
        'payment_id',
        'coupon',
        'booking_key',
        'booking_at',
        'start_at',
        'ends_at',
        'hint',
        'cancel'
    ];
    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'options' => OptionCollectionCast::class,
        'address' => Address::class,
        'coupon' => Coupon::class,
        'booking_status_id' => 'integer',
        'payment_id' => 'integer',
        'duration' => 'double',
        'quantity' => 'integer',
        'user_id' => 'integer',
        'booking_key' => 'string',
        'booking_at' => 'datetime:Y-m-d\TH:i:s.uP',
        'start_at' => 'datetime:Y-m-d\TH:i:s.uP',
        'ends_at' => 'datetime:Y-m-d\TH:i:s.uP',
        'hint' => 'string',
        'cancel' => 'boolean'
    ];

    /**
     * @return BelongsTo
     **/
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * @return BelongsTo
     **/
    public function bookingStatus()
    {
        return $this->belongsTo(BookingStatus::class, 'booking_status_id', 'id');
    }

    /**
     * @return BelongsTo
     **/
    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'id');
    }


}
