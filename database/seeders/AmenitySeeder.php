<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Hotel;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    /**
     * Seed amenities for the hotel: General hotel amenities and Room amenities.
     */
    public function run(): void
    {
        $hotel = Hotel::first();
        if (! $hotel) {
            return;
        }

        $roomAmenities = [
            ['name' => 'Air conditioning', 'icon' => 'fa-snowflake', 'sort_order' => 10],
            ['name' => 'TV', 'icon' => 'fa-tv', 'sort_order' => 20],
            ['name' => 'WiFi', 'icon' => 'fa-wifi', 'sort_order' => 30],
            ['name' => 'Safe', 'icon' => 'fa-lock', 'sort_order' => 40],
            ['name' => 'Mini bar', 'icon' => 'fa-wine-bottle', 'sort_order' => 50],
            ['name' => 'Desk', 'icon' => 'fa-desktop', 'sort_order' => 60],
            ['name' => 'Hair dryer', 'icon' => 'fa-wind', 'sort_order' => 70],
            ['name' => 'Private bathroom', 'icon' => 'fa-bath', 'sort_order' => 80],
            ['name' => 'Balcony', 'icon' => 'fa-door-open', 'sort_order' => 90],
            ['name' => 'Room service', 'icon' => 'fa-concierge-bell', 'sort_order' => 100],
        ];

        $hotelAmenities = [
            ['name' => 'Free WiFi', 'icon' => 'fa-wifi', 'sort_order' => 10],
            ['name' => 'Swimming pool', 'icon' => 'fa-swimming-pool', 'sort_order' => 20],
            ['name' => 'Restaurant', 'icon' => 'fa-utensils', 'sort_order' => 30],
            ['name' => 'Bar', 'icon' => 'fa-glass-martini-alt', 'sort_order' => 40],
            ['name' => 'Parking', 'icon' => 'fa-parking', 'sort_order' => 50],
            ['name' => '24-hour front desk', 'icon' => 'fa-clock', 'sort_order' => 60],
            ['name' => 'Luggage storage', 'icon' => 'fa-suitcase', 'sort_order' => 70],
            ['name' => 'Laundry', 'icon' => 'fa-tshirt', 'sort_order' => 80],
            ['name' => 'Airport shuttle', 'icon' => 'fa-shuttle-van', 'sort_order' => 90],
            ['name' => 'Garden', 'icon' => 'fa-leaf', 'sort_order' => 100],
        ];

        foreach ($roomAmenities as $a) {
            Amenity::firstOrCreate(
                [
                    'hotel_id' => $hotel->id,
                    'name' => $a['name'],
                    'type' => Amenity::TYPE_ROOM,
                ],
                [
                    'icon' => $a['icon'],
                    'sort_order' => $a['sort_order'],
                ]
            );
        }

        foreach ($hotelAmenities as $a) {
            Amenity::firstOrCreate(
                [
                    'hotel_id' => $hotel->id,
                    'name' => $a['name'],
                    'type' => Amenity::TYPE_HOTEL,
                ],
                [
                    'icon' => $a['icon'],
                    'sort_order' => $a['sort_order'],
                ]
            );
        }
    }
}
