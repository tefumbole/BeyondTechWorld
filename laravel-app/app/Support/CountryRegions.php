<?php

namespace App\Support;

class CountryRegions
{
    public static function map()
    {
        return [
            'Cameroon' => ['Adamawa', 'Centre', 'East', 'Far North', 'Littoral', 'North', 'Northwest', 'South', 'Southwest', 'West'],
            'United States' => ['Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California', 'Colorado', 'Connecticut', 'Delaware', 'District of Columbia', 'Florida', 'Georgia', 'Hawaii', 'Idaho', 'Illinois', 'Indiana', 'Iowa', 'Kansas', 'Kentucky', 'Louisiana', 'Maine', 'Maryland', 'Massachusetts', 'Michigan', 'Minnesota', 'Mississippi', 'Missouri', 'Montana', 'Nebraska', 'Nevada', 'New Hampshire', 'New Jersey', 'New Mexico', 'New York', 'North Carolina', 'North Dakota', 'Ohio', 'Oklahoma', 'Oregon', 'Pennsylvania', 'Rhode Island', 'South Carolina', 'South Dakota', 'Tennessee', 'Texas', 'Utah', 'Vermont', 'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming'],
            'Canada' => ['Alberta', 'British Columbia', 'Manitoba', 'New Brunswick', 'Newfoundland and Labrador', 'Northwest Territories', 'Nova Scotia', 'Nunavut', 'Ontario', 'Prince Edward Island', 'Quebec', 'Saskatchewan', 'Yukon'],
            'United Kingdom' => ['England', 'Scotland', 'Wales', 'Northern Ireland'],
            'France' => ['Auvergne-Rhône-Alpes', 'Bourgogne-Franche-Comté', 'Brittany', 'Centre-Val de Loire', 'Corsica', 'Grand Est', 'Hauts-de-France', 'Île-de-France', 'Normandy', 'Nouvelle-Aquitaine', 'Occitanie', 'Pays de la Loire', 'Provence-Alpes-Côte d\'Azur'],
            'Germany' => ['Baden-Württemberg', 'Bavaria', 'Berlin', 'Brandenburg', 'Bremen', 'Hamburg', 'Hesse', 'Lower Saxony', 'Mecklenburg-Vorpommern', 'North Rhine-Westphalia', 'Rhineland-Palatinate', 'Saarland', 'Saxony', 'Saxony-Anhalt', 'Schleswig-Holstein', 'Thuringia'],
            'China' => ['Anhui', 'Beijing', 'Chongqing', 'Fujian', 'Gansu', 'Guangdong', 'Guangxi', 'Guizhou', 'Hainan', 'Hebei', 'Heilongjiang', 'Henan', 'Hong Kong', 'Hubei', 'Hunan', 'Inner Mongolia', 'Jiangsu', 'Jiangxi', 'Jilin', 'Liaoning', 'Macau', 'Ningxia', 'Qinghai', 'Shaanxi', 'Shandong', 'Shanghai', 'Shanxi', 'Sichuan', 'Tianjin', 'Tibet', 'Xinjiang', 'Yunnan', 'Zhejiang'],
            'Nigeria' => ['Abia', 'Abuja', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno', 'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba', 'Yobe', 'Zamfara'],
        ];
    }

    public static function forCountry($country)
    {
        $map = self::map();

        return isset($map[$country]) ? $map[$country] : [];
    }

    public static function label($country)
    {
        $labels = self::labels();

        return isset($labels[$country]) ? $labels[$country] : 'State or region';
    }

    public static function labels()
    {
        return [
            'Cameroon' => 'Region',
            'France' => 'Region',
            'United States' => 'State',
            'Nigeria' => 'State',
            'Germany' => 'State',
            'Australia' => 'State',
            'India' => 'State',
            'Brazil' => 'State',
            'Mexico' => 'State',
            'Canada' => 'Province',
            'China' => 'Province',
            'United Kingdom' => 'Country',
        ];
    }
}
