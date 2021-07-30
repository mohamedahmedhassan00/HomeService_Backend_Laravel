<?php
/*
 * File name: EProviderAPIController.php
 * Last modified: 2021.05.23 at 16:24:25
 * Author: SmarterVision - https://codecanyon.net/user/smartervision
 * Copyright (c) 2021
 */

namespace App\Http\Controllers\API\EProvider;


use App\Criteria\EProviders\EProvidersOfUserCriteria;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateEProviderRequest;
use App\Http\Resources\EProviderResource;
use App\Models\EProvider;
use App\Repositories\EProviderRepository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use InfyOm\Generator\Criteria\LimitOffsetCriteria;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Prettus\Validator\Exceptions\ValidatorException;

/**
 * Class EProviderController
 * @package App\Http\Controllers\API
 */
class EProviderAPIController extends Controller
{
    /** @var  EProviderRepository */
    private $eProviderRepository;

    public function __construct(EProviderRepository $eProviderRepo)
    {
        $this->eProviderRepository = $eProviderRepo;
        parent::__construct();
    }

    /**
     * Display a listing of the EProvider.
     * GET|HEAD /eProviders
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->eProviderRepository->pushCriteria(new RequestCriteria($request));
            $this->eProviderRepository->pushCriteria(new LimitOffsetCriteria($request));
            $this->eProviderRepository->pushCriteria(new EProvidersOfUserCriteria(auth()->id()));
        } catch (RepositoryException $e) {
            return $this->sendError($e->getMessage());
        }
        $eProviders = $this->eProviderRepository->all();
        $this->filterCollection($request, $eProviders);

        return $this->sendResponse($eProviders->toArray(), 'E Providers retrieved successfully');
    }

    /**
     * Display the specified EProvider.
     * GET|HEAD /eProviders/{id}
     *
     * @param int $id
     *
     * @return JsonResponse
     */
    public function show(int $id, Request $request): JsonResponse
    {
        try {
            $this->eProviderRepository->pushCriteria(new RequestCriteria($request));
            $this->eProviderRepository->pushCriteria(new LimitOffsetCriteria($request));
        } catch (RepositoryException $e) {
            return $this->sendError($e->getMessage());
        }
        $eProvider = $this->eProviderRepository->findWithoutFail($id);
        if (empty($eProvider)) {
            return $this->sendError('EProvider not found');
        }
        $this->filterModel($request, $eProvider);

        return $this->sendResponse($eProvider->toArray(), 'EProvider retrieved successfully');
    }

    public function update(EProvider $e_provider, Request $request)
    {
        if (empty($e_provider)) {
            return $this->sendError('Provider not found');
        }
        try {
            $e_provider = $this->eProviderRepository->update($request->except(['api_token']), $e_provider->id);
        } catch (ValidatorException $e) {
            return $this->sendError($e->getMessage(), 200);
        }

        return $this->sendResponse(new EProviderResource($e_provider), __('lang.updated_successfully', ['operator' => __('lang.e_provider')]));
    }
}
