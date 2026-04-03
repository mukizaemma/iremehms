<?php

namespace App\Support;

use App\Models\User;

final class ProformaInvoicePermissions
{
    /**
     * Director / GM / Manager / Hotel admin (or explicit permission) — verify or reject submissions.
     */
    public static function canVerifyProforma(User $u): bool
    {
        if ($u->isSuperAdmin()) {
            return true;
        }
        if ($u->hasPermission('fo_proforma_verify')) {
            return true;
        }
        $slug = $u->getEffectiveRole()?->slug;

        return in_array($slug, ['manager', 'director', 'general-manager', 'hotel-admin'], true);
    }
}
