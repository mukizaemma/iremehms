<div class="min-vh-100 bg-light py-5">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-xl-8 col-lg-10">
                @if($hotel)
                    <div class="text-center mb-3">
                        <h2 class="mb-2">{{ $hotel->name }}</h2>
                        <p class="lead text-muted mb-0">Pre-arrival registration</p>
                    </div>
                @endif

                <div class="card border-0 shadow-sm mb-4 bg-white">
                    <div class="card-body">
                        <p class="mb-0 small text-muted">
                            We use your information only for your stay and for communication from the hotel; we do not share your data with third parties. At reception we will only verify your ID or passport and can pre-assign your room if you register in advance. You may add any private requests below (e.g. health, special care, or preferences) instead of stating them at the desk—we treat all information confidentially.
                        </p>
                    </div>
                </div>

                @if($submitted)
                    <div class="card border-success shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="fa fa-check-circle text-success fa-3x mb-3"></i>
                            <h5 class="text-success mb-2">Registration received</h5>
                            <p class="text-muted mb-4">{{ $submitted_message }}</p>
                            <a href="{{ url()->current() }}" class="btn btn-outline-primary">Register another</a>
                        </div>
                    </div>
                @else
                    <div class="card shadow-sm">
                        <div class="card-body p-4">
                            <form wire:submit="submit" method="post" action="{{ route('welcome') }}">

                                <div class="mb-4">
                                    <label class="form-label fw-semibold fs-6">I am registering as</label>
                                    <div class="d-flex flex-column flex-sm-row gap-2">
                                        <label class="form-check">
                                            <input type="radio" class="form-check-input" name="registration_type" wire:model="registration_type" value="individual">
                                            <span class="form-check-label">Individual</span>
                                        </label>
                                        <label class="form-check">
                                            <input type="radio" class="form-check-input" name="registration_type" wire:model="registration_type" value="group">
                                            <span class="form-check-label">Group</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold fs-6">Existing reservation or organization</label>
                                    <p class="small text-muted mb-2">
                                        If your stay was booked as part of a group or company reservation, please select it below or enter your reservation reference. This helps reception pre-link your details to the correct booking and stay dates.
                                    </p>
                                    <div class="d-flex flex-column flex-sm-row flex-wrap gap-2">
                                        <label class="form-check">
                                            <input type="radio" class="form-check-input" wire:model="reservation_choice" value="none">
                                            <span class="form-check-label">None / I will add later</span>
                                        </label>
                                        @if(count($this->reservationOptions) > 0)
                                            <label class="form-check">
                                                <input type="radio" class="form-check-input" wire:model="reservation_choice" value="select">
                                                <span class="form-check-label">Select group / organization</span>
                                            </label>
                                        @endif
                                        <label class="form-check">
                                            <input type="radio" class="form-check-input" wire:model="reservation_choice" value="type">
                                            <span class="form-check-label">Enter reservation reference</span>
                                        </label>
                                    </div>
                                    @if($reservation_choice === 'select' && count($this->reservationOptions) > 0)
                                        <select class="form-select mt-2" wire:model="reservation_reference">
                                            <option value="">— Select —</option>
                                            @foreach($this->reservationOptions as $opt)
                                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    @if($reservation_choice === 'type')
                                        <input type="text" class="form-control mt-2" wire:model="reservation_reference" placeholder="e.g. RES-20260221-1234">
                                        <p class="small text-muted mb-0 mt-1">
                                            If we cannot automatically find this reference, reception will still be able to look it up or confirm your booking when you arrive.
                                        </p>
                                    @endif
                                </div>

                                @if($registration_type === 'individual')
                                    @include('livewire.public.partials.self-check-in-guest-fields', [
                                        'prefix' => '',
                                        'useIdDetails' => 'use_id_details',
                                        'idDocument' => 'id_document',
                                        'guestName' => 'guest_name',
                                        'guestIdNumber' => 'guest_id_number',
                                        'guestCountry' => 'guest_country',
                                        'guestEmail' => 'guest_email',
                                        'guestPhone' => 'guest_phone',
                                        'guestProfession' => 'guest_profession',
                                        'guestStayPurpose' => 'guest_stay_purpose',
                                        'organization' => 'organization',
                                        'privateNotes' => 'private_notes',
                                    ])
                                @else
                                    <div class="mb-4">
                                        <div class="row g-2 align-items-end mb-3">
                                            <div class="col-sm-4 col-md-3">
                                                <label class="form-label fw-semibold mb-1">Number of people</label>
                                                <input type="number" min="1" max="20" class="form-control form-control-sm" wire:model.live="group_size">
                                            </div>
                                            <div class="col-sm-8 col-md-5">
                                                <p class="small text-muted mb-0">We will create one row per person. You can still add or remove individuals if needed.</p>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label fw-semibold mb-0">Group members</label>
                                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="addGroupGuest"><i class="fa fa-plus me-1"></i>Add another person</button>
                                        </div>
                                        @foreach($group_guests as $index => $guest)
                                            <div class="card mb-3 border">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="small fw-semibold text-muted">Person {{ $index + 1 }}</span>
                                                        @if(count($group_guests) > 1)
                                                            <button type="button" class="btn btn-outline-danger btn-sm" wire:click="removeGroupGuest({{ $index }})"><i class="fa fa-times"></i></button>
                                                        @endif
                                                    </div>
                                                    @include('livewire.public.partials.self-check-in-guest-fields', [
                                                        'prefix' => 'group_guests.' . $index . '.',
                                                        'useIdDetails' => 'group_guests.' . $index . '.use_id_details',
                                                        'idDocument' => 'group_guests.' . $index . '.id_document',
                                                        'guestName' => 'group_guests.' . $index . '.guest_name',
                                                        'guestIdNumber' => 'group_guests.' . $index . '.guest_id_number',
                                                        'guestCountry' => 'group_guests.' . $index . '.guest_country',
                                                        'guestEmail' => 'group_guests.' . $index . '.guest_email',
                                                        'guestPhone' => 'group_guests.' . $index . '.guest_phone',
                                                        'guestProfession' => 'group_guests.' . $index . '.guest_profession',
                                                        'guestStayPurpose' => 'group_guests.' . $index . '.guest_stay_purpose',
                                                        'organization' => 'group_guests.' . $index . '.organization',
                                                        'privateNotes' => 'group_guests.' . $index . '.private_notes',
                                                    ])
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary px-4" wire:loading.attr="disabled">
                                        <span wire:loading.remove>Submit</span>
                                        <span wire:loading>Saving…</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
