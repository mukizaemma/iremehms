<div class="bg-light rounded p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Purchase Requisitions</h5>
        <button class="btn btn-primary" wire:click="openRequisitionForm">
            <i class="fa fa-plus me-2"></i>New Requisition
        </button>
    </div>

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="search" wire:model.live.debounce.300ms="search" placeholder="Search...">
                        <label for="search">Search Supplier or Notes</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating">
                        <select class="form-select" id="filter_status" wire:model.live="filter_status">
                            <option value="">All Statuses</option>
                            <option value="DRAFT">Draft</option>
                            <option value="SUBMITTED">Submitted</option>
                            <option value="APPROVED">Approved</option>
                            <option value="REJECTED">Rejected</option>
                            <option value="CANCELLED">Cancelled</option>
                        </select>
                        <label for="filter_status">Status</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating">
                        <select class="form-select" id="filter_department" wire:model.live="filter_department">
                            <option value="">All Departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                        <label for="filter_department">Department</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Requisitions List -->
    <div class="card">
        <div class="card-body">
            @if(count($requisitions) > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Requisition #</th>
                                <th>Supplier</th>
                                <th>Department</th>
                                <th>Requested By</th>
                                <th>Status</th>
                                <th>Total Cost</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($requisitions as $requisition)
                                <tr>
                                    <td><strong>#{{ $requisition['requisition_id'] }}</strong></td>
                                    <td>{{ $requisition['supplier']['name'] ?? 'N/A' }}</td>
                                    <td>{{ $requisition['department']['name'] ?? 'N/A' }}</td>
                                    <td>{{ $requisition['requested_by']['name'] ?? 'N/A' }}</td>
                                    <td>
                                        @php
                                            $statusColors = [
                                                'DRAFT' => 'secondary',
                                                'SUBMITTED' => 'info',
                                                'APPROVED' => 'success',
                                                'REJECTED' => 'danger',
                                                'CANCELLED' => 'warning',
                                            ];
                                            $color = $statusColors[$requisition['status']] ?? 'secondary';
                                        @endphp
                                        <span class="badge bg-{{ $color }}">{{ $requisition['status'] }}</span>
                                    </td>
                                    <td>{{ \App\Helpers\CurrencyHelper::format($requisition['total_estimated_cost'] ?? 0) }}</td>
                                    <td>
                                        {{ isset($requisition['business_date']) && $requisition['business_date']
                                            ? \Carbon\Carbon::parse($requisition['business_date'])->format('M d, Y')
                                            : (\Carbon\Carbon::parse($requisition['created_at'])->format('M d, Y')) }}
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-secondary" wire:click="openComments({{ $requisition['requisition_id'] }})" title="Comments">
                                            <i class="fa fa-comments"></i>
                                        </button>
                                        @if($requisition['status'] === 'APPROVED')
                                            <button class="btn btn-sm btn-info" wire:click="openViewRequisition({{ $requisition['requisition_id'] }})" title="View / Print / Share">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                        @else
                                            <button class="btn btn-sm btn-info" wire:click="openRequisitionForm({{ $requisition['requisition_id'] }})" title="View/Edit">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                        @endif
                                        @if($requisition['status'] === 'DRAFT')
                                            <button class="btn btn-sm btn-primary" wire:click="submitRequisition({{ $requisition['requisition_id'] }})" wire:confirm="Are you sure you want to submit this requisition for approval?" title="Submit">
                                                <i class="fa fa-paper-plane"></i>
                                            </button>
                                        @endif
                                        @if($requisition['status'] === 'SUBMITTED' && (Auth::user()->isSuperAdmin() || Auth::user()->isManager()))
                                            <button class="btn btn-sm btn-success" wire:click="openApprovalModal({{ $requisition['requisition_id'] }})" title="Approve">
                                                <i class="fa fa-check"></i>
                                            </button>
                                        @endif
                                        @if(in_array($requisition['status'], ['DRAFT', 'SUBMITTED']))
                                            <button class="btn btn-sm btn-warning" wire:click="cancelRequisition({{ $requisition['requisition_id'] }})" wire:confirm="Are you sure you want to cancel this requisition? This cannot be undone." title="Cancel">
                                                <i class="fa fa-times"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info">No purchase requisitions found.</div>
            @endif
        </div>
    </div>

    <!-- Requisition Form Modal -->
    @if($showRequisitionForm)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingRequisitionId ? 'Edit' : 'New' }} Purchase Requisition</h5>
                        <button type="button" class="btn-close" wire:click="closeRequisitionForm"></button>
                    </div>
                    <form wire:submit.prevent="saveRequisition">
                        <div class="modal-body">
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="notes" wire:model.defer="notes" style="height: 100px" placeholder="Notes"></textarea>
                                <label for="notes">Notes (Optional)</label>
                            </div>

                            <h6 class="mb-3">Requisition Items</h6>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Location</th>
                                            <th>Quantity</th>
                                            <th>Unit</th>
                                            <th>Est. Unit Cost</th>
                                            <th>Total</th>
                                            <th>Expiry</th>
                                            <th>Notes</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($requisitionItems as $index => $item)
                                            @php
                                                $expiry = $item['expiry_date'] ?? null;
                                                $expiryFormatted = $expiry
                                                    ? \Carbon\Carbon::parse($expiry)->format('Y-m-d')
                                                    : '—';
                                            @endphp
                                            <tr>
                                                <td>
                                                    <select class="form-select form-select-sm" wire:model.live="requisitionItems.{{ $index }}.item_id" required>
                                                        <option value="">Select Item</option>
                                                        @foreach($stocks as $stock)
                                                            <option value="{{ $stock->id }}">{{ $stock->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    <span class="text-muted">
                                                        {{ $item['location_name'] ?? '—' }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" wire:model.defer="requisitionItems.{{ $index }}.quantity_requested" required>
                                                </td>
                                                <td>
                                                    <select class="form-select form-select-sm" wire:model.defer="requisitionItems.{{ $index }}.unit_id">
                                                        <option value="">Select Unit</option>
                                                        <option value="pcs">pcs - Pieces</option>
                                                        <option value="kg">kg - Kilograms</option>
                                                        <option value="g">g - Grams</option>
                                                        <option value="l">l - Liters</option>
                                                        <option value="ml">ml - Milliliters</option>
                                                        <option value="m">m - Meters</option>
                                                        <option value="cm">cm - Centimeters</option>
                                                        <option value="m²">m² - Square Meters</option>
                                                        <option value="m³">m³ - Cubic Meters</option>
                                                        <option value="dozen">dozen - Dozen</option>
                                                        <option value="pair">pair - Pair</option>
                                                        <option value="set">set - Set</option>
                                                        <option value="roll">roll - Roll</option>
                                                        <option value="sheet">sheet - Sheet</option>
                                                        <option value="unit">unit - Unit</option>
                                                        <option value="box">box - Box</option>
                                                        <option value="bottle">bottle - Bottle</option>
                                                        <option value="can">can - Can</option>
                                                        <option value="pack">pack - Pack</option>
                                                        <option value="bag">bag - Bag</option>
                                                        <option value="carton">carton - Carton</option>
                                                        <option value="case">case - Case</option>
                                                        <option value="barrel">barrel - Barrel</option>
                                                        <option value="drum">drum - Drum</option>
                                                        <option value="gallon">gallon - Gallon</option>
                                                        <option value="ounce">ounce - Ounce</option>
                                                        <option value="pound">pound - Pound</option>
                                                        <option value="ton">ton - Ton</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.defer="requisitionItems.{{ $index }}.estimated_unit_cost">
                                                </td>
                                                <td>
                                                    {{ \App\Helpers\CurrencyHelper::format(($item['quantity_requested'] ?? 0) * ($item['estimated_unit_cost'] ?? 0)) }}
                                                </td>
                                                <td>
                                                    {{ $expiryFormatted }}
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" wire:model.defer="requisitionItems.{{ $index }}.notes">
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-danger" wire:click="removeRequisitionItem({{ $index }})" wire:confirm="Remove this item from the requisition?">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <button type="button" class="btn btn-sm btn-success mb-3" wire:click="addRequisitionItem">
                                <i class="fa fa-plus me-2"></i>Add Item
                            </button>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeRequisitionForm" wire:loading.attr="disabled">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" @if($editingRequisitionId) wire:confirm="Save changes to this requisition?" @endif>
                                <span wire:loading.remove>Save</span>
                                <span wire:loading>
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Saving...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- View Requisition Modal (approved – read-only, print, share) -->
    @if($showViewRequisitionId && $viewRequisitionData)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">Requisition #{{ $showViewRequisitionId }} — View / Print / Share</h5>
                        <button type="button" class="btn-close" wire:click="closeViewRequisition"></button>
                    </div>
                    <div class="modal-body">
                        <div id="requisition-print-area">
                            @php $r = $viewRequisitionData; @endphp
                            @php $hotelDoc = \App\Models\Hotel::getHotel(); @endphp
                            @if($hotelDoc)
                                <div class="mb-3">
                                    <x-hotel-document-header :hotel="$hotelDoc" subtitle="Purchase requisition" />
                                </div>
                            @endif
                            <div class="mb-3">
                                <p class="mb-1"><strong>Supplier:</strong> {{ $r['supplier']['name'] ?? 'N/A' }}</p>
                                <p class="mb-1"><strong>Department:</strong> {{ $r['department']['name'] ?? 'N/A' }}</p>
                                <p class="mb-1"><strong>Requested by:</strong> {{ $r['requested_by']['name'] ?? 'N/A' }}</p>
                                <p class="mb-1">
                                    <strong>Date:</strong>
                                    {{ isset($r['business_date']) && $r['business_date']
                                        ? \Carbon\Carbon::parse($r['business_date'])->format('M d, Y')
                                        : (isset($r['created_at']) ? \Carbon\Carbon::parse($r['created_at'])->format('M d, Y') : '') }}
                                </p>
                                @if(!empty($r['notes']))
                                    <p class="mb-0"><strong>Notes:</strong> {{ $r['notes'] }}</p>
                                @endif
                            </div>
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Location</th>
                                        <th>Quantity</th>
                                        <th>Unit</th>
                                        <th>Est. Unit Cost</th>
                                        <th>Total</th>
                                        <th>Expiry</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($r['items'] ?? [] as $item)
                                        <tr>
                                            <td>{{ $item['item']['name'] ?? 'N/A' }}</td>
                                        <td>
                                            {{ $item['item']['stock_location']['name'] ?? ($item['item']['stock_location_id'] ?? '—') }}
                                        </td>
                                            <td>{{ $item['quantity_requested'] ?? 0 }}</td>
                                            <td>{{ $item['unit_id'] ?? '—' }}</td>
                                            <td>{{ \App\Helpers\CurrencyHelper::format($item['estimated_unit_cost'] ?? 0) }}</td>
                                            <td>{{ \App\Helpers\CurrencyHelper::format(($item['quantity_requested'] ?? 0) * ($item['estimated_unit_cost'] ?? 0)) }}</td>
                                        <td>
                                            @php
                                                $useExp = $item['item']['use_expiration'] ?? false;
                                                $expiry = ($useExp && !empty($item['item']['expiration_date'])) ? $item['item']['expiration_date'] : null;
                                                $expiryFormatted = $expiry ? \Carbon\Carbon::parse($expiry)->format('Y-m-d') : '—';
                                            @endphp
                                            {{ $expiryFormatted }}
                                        </td>
                                            <td>{{ $item['notes'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <p class="mb-0"><strong>Total estimated cost:</strong> {{ \App\Helpers\CurrencyHelper::format($r['total_estimated_cost'] ?? 0) }}</p>

                            {{-- Print signature footer --}}
                            <div class="row mt-4">
                                <div class="col-md-4">
                                    <div class="small text-muted mb-1">Prepared by</div>
                                    <div class="fw-semibold border-bottom pb-2" style="min-height: 34px;">
                                        {{ Auth::user()?->name ?? '' }}
                                    </div>
                                </div>
                                <div class="col-md-4 mt-3 mt-md-0">
                                    <div class="small text-muted mb-1">Verified by</div>
                                    <div class="fw-semibold border-bottom pb-2" style="min-height: 34px;">
                                        ________________________
                                    </div>
                                </div>
                                <div class="col-md-4 mt-3 mt-md-0">
                                    <div class="small text-muted mb-1">Approved by</div>
                                    <div class="fw-semibold border-bottom pb-2" style="min-height: 34px;">
                                        ________________________
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer no-print">
                        <button type="button" class="btn btn-secondary" wire:click="closeViewRequisition">Close</button>
                        <a href="https://wa.me/?text={{ urlencode($this->getWhatsAppShareText()) }}" class="btn btn-success" target="_blank" rel="noopener noreferrer" title="Share via WhatsApp">
                            <i class="fa fa-whatsapp me-2"></i>Share via WhatsApp
                        </a>
                        <button type="button" class="btn btn-primary" onclick="window.print();" title="Print">
                            <i class="fa fa-print me-2"></i>Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <style media="print">
            body * { visibility: hidden; }
            .modal.show .modal-dialog, #requisition-print-area, #requisition-print-area * { visibility: visible; }
            #requisition-print-area { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print, .modal-header .btn-close, .modal-footer { display: none !important; }
        </style>
    @endif

    <!-- Comments Modal -->
    @if($showCommentsRequisitionId)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">Comments — Requisition #{{ $showCommentsRequisitionId }}</h5>
                        <button type="button" class="btn-close" wire:click="closeComments"></button>
                    </div>
                    <div class="modal-body">
                        @if (session()->has('message'))
                            <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
                                {{ session('message') }}
                                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                            </div>
                        @endif
                        @if (session()->has('error'))
                            <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                                {{ session('error') }}
                                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Add a comment</label>
                            <div class="input-group">
                                <textarea class="form-control" wire:model.defer="commentBody" rows="2" placeholder="Write a comment..."></textarea>
                                <button type="button" class="btn btn-primary" wire:click="addComment" wire:loading.attr="disabled">
                                    <span wire:loading.remove>Post</span>
                                    <span wire:loading><span class="spinner-border spinner-border-sm"></span></span>
                                </button>
                            </div>
                            @error('commentBody')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="border-top pt-3">
                            <label class="form-label fw-semibold">Comments ({{ count($commentsForModal) }})</label>
                            @if(count($commentsForModal) > 0)
                                <div class="d-flex flex-column gap-3">
                                    @foreach($commentsForModal as $comment)
                                        <div class="card border">
                                            <div class="card-body py-2 px-3">
                                                @if(($editingCommentId ?? null) == $comment['id'])
                                                    <textarea class="form-control form-control-sm mb-2" wire:model.defer="editCommentBody" rows="2"></textarea>
                                                    @error('editCommentBody')
                                                        <div class="text-danger small mb-1">{{ $message }}</div>
                                                    @enderror
                                                    <div class="d-flex gap-1">
                                                        <button type="button" class="btn btn-sm btn-primary" wire:click="updateComment">Update</button>
                                                        <button type="button" class="btn btn-sm btn-secondary" wire:click="cancelEditComment">Cancel</button>
                                                    </div>
                                                @else
                                                    <p class="mb-1 small text-muted">
                                                        <strong>{{ $comment['user']['name'] ?? 'Unknown' }}</strong>
                                                        · {{ \Carbon\Carbon::parse($comment['created_at'])->format('M d, Y H:i') }}
                                                        @if(($comment['user_id'] ?? null) == Auth::id())
                                                            <span class="ms-2">
                                                                <button type="button" class="btn btn-link btn-sm p-0 text-muted" wire:click="startEditComment({{ $comment['id'] }})" title="Edit">Edit</button>
                                                                <button type="button" class="btn btn-link btn-sm p-0 text-danger" wire:click="deleteComment({{ $comment['id'] }})" wire:confirm="Delete this comment?" title="Delete">Delete</button>
                                                            </span>
                                                        @endif
                                                    </p>
                                                    <p class="mb-0">{{ $comment['body'] }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-muted small mb-0">No comments yet. Add one above.</p>
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeComments">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Approval Modal -->
    @if($showApprovalModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">Process Requisition</h5>
                        <button type="button" class="btn-close" wire:click="closeApprovalModal"></button>
                    </div>
                    <form wire:submit.prevent="processApproval">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="approval_action" wire:model.defer="approval_action" required>
                                    <option value="APPROVE">Approve</option>
                                    <option value="REQUEST_MODIFICATION">Request Modification</option>
                                    <option value="REQUEST_CLARIFICATION">Request Clarification</option>
                                </select>
                                <label for="approval_action">Action <span class="text-danger">*</span></label>
                            </div>

                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="approval_notes" wire:model.defer="approval_notes" style="height: 100px" placeholder="Notes" required></textarea>
                                <label for="approval_notes">Notes <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeApprovalModal" wire:loading.attr="disabled">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:confirm="Are you sure you want to process this requisition? This action cannot be undone.">
                                <span wire:loading.remove>Process</span>
                                <span wire:loading>
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Processing...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
