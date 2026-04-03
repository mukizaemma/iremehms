<?php

namespace App\Livewire;

use App\Models\Hotel;
use App\Models\HotelReview;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Approve/reject guest reviews. Access: Super Admin + Manager.
 */
class ReviewsModeration extends Component
{
    public function mount(): void
    {
        if (! Auth::user()->isEffectiveSuperAdmin() && ! Auth::user()->isEffectiveManager()) {
            abort(403, 'Unauthorized. Only Super Admin or Manager can moderate reviews.');
        }
    }

    public function approve(int $id): void
    {
        $this->authorizeReview($id);
        HotelReview::where('hotel_id', Hotel::getHotel()->id)->findOrFail($id)->update(['is_approved' => true]);
        session()->flash('message', 'Review approved and will show on the public page.');
    }

    public function reject(int $id): void
    {
        $this->authorizeReview($id);
        HotelReview::where('hotel_id', Hotel::getHotel()->id)->findOrFail($id)->update(['is_approved' => false]);
        session()->flash('message', 'Review hidden from the public page.');
    }

    public function delete(int $id): void
    {
        $this->authorizeReview($id);
        HotelReview::where('hotel_id', Hotel::getHotel()->id)->findOrFail($id)->delete();
        session()->flash('message', 'Review deleted.');
    }

    protected function authorizeReview(int $id): void
    {
        if (! Auth::user()->isEffectiveSuperAdmin() && ! Auth::user()->isEffectiveManager()) {
            abort(403, 'Unauthorized.');
        }
    }

    public function render()
    {
        $reviews = Hotel::getHotel()->reviews()->orderByDesc('created_at')->get();

        return view('livewire.reviews-moderation', [
            'reviews' => $reviews,
        ])->layout('livewire.layouts.app-layout');
    }
}
