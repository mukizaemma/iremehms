<?php

namespace App\Livewire;

use App\Models\Hotel;
use App\Models\HotelGalleryImage;
use App\Models\HotelReview;
use App\Models\HotelVideoTour;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Hotel details / hotel info – Super Admin only. Name, branding, about, gallery, videos, reviews.
 */
class HotelDetails extends Component
{
    use WithFileUploads;

    public string $configTab = 'general';

    public $hotelName = '';
    public $hotelContact = '';
    public $hotelEmail = '';
    public $hotelAddress = '';
    public $mapEmbedCode = '';
    public $primaryColor = '#667eea';
    public $secondaryColor = '#764ba2';
    public $fontFamily = 'Heebo';
    public $logo;
    public $logoPreview;
    public $loginBackground;
    public $loginBackgroundPreview;

    public $fax = '';
    public $reservation_phone = '';
    public $hotel_type = '';
    public $check_in_time = '';
    public $check_out_time = '';
    public $hotel_information = '';

    public $galleryImage;
    public $galleryCaption = '';
    public $videoTitle = '';
    public $videoUrl = '';
    public $videoEmbedCode = '';

    public bool $receiptShowVatSetting = false;

    public bool $reportsShowVatSetting = false;

    public function mount(): void
    {
        if (! Auth::user()->isSuperAdmin() && ! Auth::user()->hasPermission('hotel_configure_details')) {
            abort(403, 'Only Super Admin or users with Hotel: Configure details permission can access Hotel details.');
        }
        $this->loadHotel();
    }

    protected function loadHotel(): void
    {
        $hotel = Hotel::getHotel();
        $this->hotelName = $hotel->name;
        $this->hotelContact = $hotel->contact ?? '';
        $this->hotelEmail = $hotel->email ?? '';
        $this->hotelAddress = $hotel->address ?? '';
        $this->mapEmbedCode = $hotel->map_embed_code ?? '';
        $this->primaryColor = $hotel->primary_color ?? '#667eea';
        $this->secondaryColor = $hotel->secondary_color ?? '#764ba2';
        $this->fontFamily = $hotel->font_family ?? 'Heebo';
        if ($hotel->logo) {
            $this->logoPreview = Storage::url($hotel->logo);
        }
        if ($hotel->login_background_image) {
            $this->loginBackgroundPreview = Storage::url($hotel->login_background_image);
        }
        $this->configTab = request()->get('tab', 'general');
        if (Schema::hasColumn('hotels', 'fax')) {
            $this->fax = $hotel->fax ?? '';
            $this->reservation_phone = $hotel->reservation_phone ?? '';
            $this->hotel_type = $hotel->hotel_type ?? '';
            $this->check_in_time = $hotel->check_in_time ?? '';
            $this->check_out_time = $hotel->check_out_time ?? '';
            $this->hotel_information = $hotel->hotel_information ?? '';
        }

        $this->receiptShowVatSetting = (bool) ($hotel->receipt_show_vat ?? false);
        $this->reportsShowVatSetting = Schema::hasColumn('hotels', 'reports_show_vat')
            ? (bool) ($hotel->reports_show_vat ?? false)
            : false;
    }

    public function setConfigTab(string $tab): void
    {
        $this->configTab = $tab;
    }

    public function updatedLogo(): void
    {
        $this->validate(['logo' => 'image|max:2048']);
        $this->logoPreview = $this->logo->temporaryUrl();
    }

    public function updatedLoginBackground(): void
    {
        $this->validate(['loginBackground' => 'image|max:4096']);
        $this->loginBackgroundPreview = $this->loginBackground->temporaryUrl();
    }

    public function save(): void
    {
        $this->validate([
            'hotelName' => 'required|string|max:255',
            'hotelContact' => 'nullable|string|max:255',
            'hotelEmail' => 'nullable|email|max:255',
            'hotelAddress' => 'nullable|string',
            'primaryColor' => 'required|string',
            'secondaryColor' => 'required|string',
            'fontFamily' => 'required|string',
            'logo' => 'nullable|image|max:2048',
            'loginBackground' => 'nullable|image|max:4096',
        ]);
        $hotel = Hotel::getHotel();
        if ($this->logo) {
            $logoPath = $this->logo->store('logos', 'public');
            $hotel->logo = $logoPath;
            $this->logoPreview = Storage::url($logoPath);
        }
        if ($this->loginBackground) {
            $bgPath = $this->loginBackground->store('login-backgrounds', 'public');
            $hotel->login_background_image = $bgPath;
            $this->loginBackgroundPreview = Storage::url($bgPath);
        }
        $hotel->update([
            'name' => $this->hotelName,
            'contact' => $this->hotelContact,
            'email' => $this->hotelEmail,
            'address' => $this->hotelAddress,
            'map_embed_code' => $this->mapEmbedCode ?: null,
            'primary_color' => $this->primaryColor,
            'secondary_color' => $this->secondaryColor,
            'font_family' => $this->fontFamily,
        ]);
        session()->flash('message', 'Hotel details saved successfully.');
    }

    public function saveAbout(): void
    {
        if (! Schema::hasColumn('hotels', 'fax')) {
            session()->flash('error', 'Please run migrations: php artisan migrate');
            return;
        }
        $aboutPayload = [
            'fax' => $this->fax ?: null,
            'reservation_phone' => $this->reservation_phone ?: null,
            'hotel_type' => $this->hotel_type ?: null,
            'check_in_time' => $this->check_in_time ?: null,
            'check_out_time' => $this->check_out_time ?: null,
            'hotel_information' => $this->hotel_information ?: null,
            'receipt_show_vat' => $this->receiptShowVatSetting,
        ];
        if (Schema::hasColumn('hotels', 'reports_show_vat')) {
            $aboutPayload['reports_show_vat'] = $this->reportsShowVatSetting;
        }
        Hotel::getHotel()->update($aboutPayload);
        session()->flash('message', 'About the hotel saved successfully.');
    }

    public function addGalleryImage(): void
    {
        $this->validate([
            'galleryImage' => 'required|image|max:4096',
            'galleryCaption' => 'nullable|string|max:255',
        ]);
        $path = $this->galleryImage->store('hotel-gallery', 'public');
        $hotel = Hotel::getHotel();
        $maxOrder = $hotel->galleryImages()->max('sort_order') ?? 0;
        HotelGalleryImage::create([
            'hotel_id' => $hotel->id,
            'path' => $path,
            'caption' => $this->galleryCaption ?: null,
            'sort_order' => $maxOrder + 1,
        ]);
        $this->galleryImage = null;
        $this->galleryCaption = '';
        session()->flash('message', 'Gallery image added.');
    }

    public function deleteGalleryImage(int $id): void
    {
        $img = HotelGalleryImage::where('hotel_id', Hotel::getHotel()->id)->findOrFail($id);
        Storage::disk('public')->delete($img->path);
        $img->delete();
        session()->flash('message', 'Gallery image removed.');
    }

    public function addVideoTour(): void
    {
        $this->validate([
            'videoTitle' => 'nullable|string|max:255',
            'videoUrl' => 'nullable|string|max:500|url',
            'videoEmbedCode' => 'nullable|string|max:2000',
        ], [], ['videoEmbedCode' => 'embed code']);
        if (empty($this->videoUrl) && empty($this->videoEmbedCode)) {
            session()->flash('error', 'Please provide either a video URL or embed code.');
            return;
        }
        $hotel = Hotel::getHotel();
        $maxOrder = $hotel->videoTours()->max('sort_order') ?? 0;
        HotelVideoTour::create([
            'hotel_id' => $hotel->id,
            'title' => $this->videoTitle ?: null,
            'url' => $this->videoUrl ?: null,
            'embed_code' => $this->videoEmbedCode ?: null,
            'sort_order' => $maxOrder + 1,
        ]);
        $this->videoTitle = '';
        $this->videoUrl = '';
        $this->videoEmbedCode = '';
        session()->flash('message', 'Video tour added.');
    }

    public function deleteVideoTour(int $id): void
    {
        HotelVideoTour::where('hotel_id', Hotel::getHotel()->id)->findOrFail($id)->delete();
        session()->flash('message', 'Video tour removed.');
    }

    public function approveReview(int $id): void
    {
        HotelReview::where('hotel_id', Hotel::getHotel()->id)->findOrFail($id)->update(['is_approved' => true]);
        session()->flash('message', 'Review approved and will show on the public page.');
    }

    public function rejectReview(int $id): void
    {
        HotelReview::where('hotel_id', Hotel::getHotel()->id)->findOrFail($id)->update(['is_approved' => false]);
        session()->flash('message', 'Review hidden from the public page.');
    }

    public function deleteReview(int $id): void
    {
        HotelReview::where('hotel_id', Hotel::getHotel()->id)->findOrFail($id)->delete();
        session()->flash('message', 'Review deleted.');
    }

    public function render()
    {
        return view('livewire.hotel-details')->layout('livewire.layouts.app-layout');
    }
}
