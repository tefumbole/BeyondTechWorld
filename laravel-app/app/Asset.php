<?php

namespace App;

use DateTime;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo('App\AssetCategory', 'category_id');
    }

    public function department()
    {
        return $this->belongsTo('App\Department', 'department_id');
    }

    public function donor()
    {
        return $this->belongsTo('App\Donor', 'donor_id');
    }

    public function region()
    {
        return $this->belongsTo('App\Region', 'region_id');
    }

    public function station()
    {
        return $this->belongsTo('App\Station', 'station_id');
    }

    public function images()
    {
        return $this->hasMany('App\ImageLibrary', 'asset_id');
    }

    public function AssetTransfers() {
        return $this->hasOne('App\AssetTransfer', 'parent_id', 'id');
    }
    public function AssetSaleDetails() {
        return $this->hasOne('App\AssetSaleDetail', 'asset_id');
    }


    public static function depricationCaluculate($asset){

        $depreciation = 0;
        $book_value = $asset->price;
        $available = 0;
        $available_in_year = 0;
        $consume_in_year = 0;
        $consume = 0;
        $available = 0;

        if ($asset->service_date != null){
            $d1 = new DateTime($asset->service_date);
        } else {
            $d1 = new DateTime($asset->created_at);
        }

        $d2 = new DateTime();
        $interval = $d1->diff($d2);

        $consume = $interval->days;
        if($asset->life_span != null) {
            $total_life_span = $asset->life_span * 365;
            if ($consume > $total_life_span) {
                $depreciation = $asset->price;
            } else {
                $depreciation = ($consume/$total_life_span) * $asset->price;
                $available = $total_life_span - $consume;
                $book_value = ($available/$total_life_span) * $asset->price;
            }
        }
        if($asset->asset_type == 'land') {
            $apprication_increase_percentage = ($consume / 365) * $asset->appreciation;
            $apprication_increase_value = ($apprication_increase_percentage/100) * $asset->price;
            $book_value = $apprication_increase_value + $asset->price;
            $depreciation = - $apprication_increase_value ;
        }

        $depreciation = round($depreciation,2);
        $book_value = round($book_value,2);
        $consume_in_year = round(($consume/365),2);
        $available_in_year = round(($available/365),2);


        return [
            'asset_id' => $asset->id,
            'depreciation' => $depreciation,
            'book_value' => $book_value,
            'consume_in_year' => $consume_in_year,
            'available_in_year' => $available_in_year,
            'available' => $available,
            'consume' => $consume,
        ];
    }
}
