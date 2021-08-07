<?php
/*
 * File name: EarningAPIController.php
 * Last modified: 2021.01.30 at 16:06:55
 * Author: SmarterVision - https://codecanyon.net/user/smartervision
 * Copyright (c) 2021
 */

namespace App\Http\Controllers\API;


use App\Criteria\Earnings\EarningOfUserCriteria;
use App\Http\Controllers\Controller;
use App\Models\Earning;
use App\Repositories\EarningRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InfyOm\Generator\Criteria\LimitOffsetCriteria;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;

/**
 * Class EarningController
 * @package App\Http\Controllers\API
 */
class EarningAPIController extends Controller
{
    /** @var  EarningRepository */
    private $earningRepository;

    public function __construct(EarningRepository $earningRepo)
    {
        $this->earningRepository = $earningRepo;
    }

    /**
     * Display a listing of the Earning.
     * GET|HEAD /earnings
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        try{
            $this->earningRepository->pushCriteria(new RequestCriteria($request));
            $this->earningRepository->pushCriteria(new LimitOffsetCriteria($request));
        } catch (RepositoryException $e) {
            return $this->sendError($e->getMessage());
        }
        $earnings = $this->earningRepository->all();

        return $this->sendResponse($earnings->toArray(), 'Earnings retrieved successfully');
    }

    /**
     * Display the specified Earning.
     * GET|HEAD /earnings/{id}
     *
     * @param int $id
     *
     * @return JsonResponse
     */
    public function show($id)
    {
        /** @var Earning $earning */
        if (!empty($this->earningRepository)) {
            $earning = $this->earningRepository->findWithoutFail($id);
        }

        if (empty($earning)) {
            return $this->sendError('Earning not found');
        }

        return $this->sendResponse($earning->toArray(), 'Earning retrieved successfully');
    }

    public function removeDebt(Request $request)
    {
        try {
            $this->earningRepository->pushCriteria(new EarningOfUserCriteria(auth()->id()));
            $adminEarningSum = $this->earningRepository->all()->sum('admin_earning') - $request->amount_paid;
            $this->earningRepository->all()->map(function ($earning) use($adminEarningSum){
                $earning->admin_earning = 0;
                $earning->save();
            });
            $earning = $this->earningRepository->first();
            $earning->admin_earning = $adminEarningSum;
            $earning->save();

            return $this->sendResponse(null, 'Debt remove successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }

    }
}
