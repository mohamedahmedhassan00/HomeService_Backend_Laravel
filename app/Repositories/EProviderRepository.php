<?php
/*
 * File name: EProviderRepository.php
 * Last modified: 2021.01.17 at 17:04:35
 * Author: SmarterVision - https://codecanyon.net/user/smartervision
 * Copyright (c) 2021
 */

namespace App\Repositories;

use App\Models\EProvider;
use Illuminate\Support\Str;
use InfyOm\Generator\Common\BaseRepository;

/**
 * Class EProviderRepository
 * @package App\Repositories
 * @version January 13, 2021, 11:11 am UTC
 *
 * @method EProvider findWithoutFail($id, $columns = ['*'])
 * @method EProvider find($id, $columns = ['*'])
 * @method EProvider first($columns = ['*'])
 */
class EProviderRepository extends BaseRepository
{
    /**
     * @var array
     */
    protected $fieldSearchable = [
        'name',
        'e_provider_type_id',
        'description',
        'phone_number',
        'mobile_number',
        'availability_range',
        'available',
        'featured'
    ];

    /**
     * Configure the Model
     **/
    public function model()
    {
        return EProvider::class;
    }

    public function createProvider($request)
    {
        $provider = $this->create($request->merge([
            'api_token' => Str::random(60),
            'e_provider_type_id' => 3,
            'accepted' => 1,
            'available' => 1,
        ])->except('address'));

        $provider->addresses()->create($request->address);

        $this->setImage($request->image, $provider);

        return $provider;
    }

    public function setImage($image, $provider)
    {
        if (isset($image) && $image) {
            if (is_array($image)){
                foreach ($image as $img) {
                    $provider->addMedia($img)
                        ->toMediaCollection('image');
                }
            } else {
                $provider->addMedia($image)
                    ->toMediaCollection('image');
            }
        }
    }
}
