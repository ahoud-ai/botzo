<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Organization extends Model {
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = [];
    public $timestamps = true;

    public function listAll(
        $searchTerm,
        $userId = null,
        $organizationType = null,
        $subscriptionStatus = null,
        $planUuid = null,
        $branchesScope = null
    )
    {
        $searchTerm = trim((string) ($searchTerm ?? ''));
        $normalizedPhoneSearch = preg_replace('/\D+/', '', $searchTerm);

        $query = $this->with([
                'teams.user',
                'owner.user',
                'subscription.plan',
                'parentOrganization.subscription.plan',
                'branches' => function ($query) {
                    $query->select(['id', 'uuid', 'name', 'parent_organization_id', 'updated_at'])
                        ->orderBy('name');
                },
            ])
            ->when($userId !== null, function ($query) use ($userId) {
                $query->whereHas('teams', function ($teamsQuery) use ($userId) {
                    $teamsQuery->where('user_id', $userId);
                });
            })
            ->when(in_array($organizationType, ['main', 'branch'], true), function ($query) use ($organizationType) {
                $query->where('organization_type', $organizationType);
            })
            ->when($searchTerm !== '', function ($query) use ($searchTerm, $normalizedPhoneSearch) {
                $like = '%' . $searchTerm . '%';

                $query->where(function ($searchQuery) use ($like, $normalizedPhoneSearch) {
                    $searchQuery->where('name', 'like', $like)
                        ->orWhereHas('owner.user', function ($userQuery) use ($like, $normalizedPhoneSearch) {
                            $userQuery->where('email', 'like', $like)
                                ->orWhere('phone', 'like', $like)
                                ->orWhere('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'like', $like);

                            if ($normalizedPhoneSearch !== '') {
                                $userQuery->orWhereRaw(
                                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '') LIKE ?",
                                    ['%' . $normalizedPhoneSearch . '%']
                                );
                            }
                        });
                });
            })
            ->when(in_array($subscriptionStatus, ['active', 'trial', 'expired'], true), function ($query) use ($subscriptionStatus) {
                $query->where(function ($subscriptionQuery) use ($subscriptionStatus) {
                    $subscriptionQuery
                        ->where(function ($mainQuery) use ($subscriptionStatus) {
                            $mainQuery->where('organization_type', 'main')
                                ->whereHas('subscription', function ($subscriptionRelationQuery) use ($subscriptionStatus) {
                                    $this->applyOperationalSubscriptionFilter($subscriptionRelationQuery, $subscriptionStatus);
                                });
                        })
                        ->orWhere(function ($branchQuery) use ($subscriptionStatus) {
                            $branchQuery->where('organization_type', 'branch')
                                ->whereHas('parentOrganization.subscription', function ($subscriptionRelationQuery) use ($subscriptionStatus) {
                                    $this->applyOperationalSubscriptionFilter($subscriptionRelationQuery, $subscriptionStatus);
                                });
                        });
                });
            })
            ->when($planUuid, function ($query) use ($planUuid) {
                $query->where(function ($subscriptionQuery) use ($planUuid) {
                    $subscriptionQuery
                        ->where(function ($mainQuery) use ($planUuid) {
                            $mainQuery->where('organization_type', 'main')
                                ->whereHas('subscription.plan', function ($planQuery) use ($planUuid) {
                                    $planQuery->where('uuid', $planUuid);
                                });
                        })
                        ->orWhere(function ($branchQuery) use ($planUuid) {
                            $branchQuery->where('organization_type', 'branch')
                                ->whereHas('parentOrganization.subscription.plan', function ($planQuery) use ($planUuid) {
                                    $planQuery->where('uuid', $planUuid);
                                });
                        });
                });
            })
            ->when(in_array($branchesScope, ['with_branches', 'without_branches'], true), function ($query) use ($branchesScope) {
                $query->where('organization_type', 'main');

                if ($branchesScope === 'with_branches') {
                    $query->has('branches');
                } else {
                    $query->doesntHave('branches');
                }
            })
            ->withCount(['teams', 'branches'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return $query;
    }

    public function teams()
    {
        return $this->hasMany(Team::class, 'organization_id');
    }

    public function owner()
    {
        return $this->hasOne(Team::class, 'organization_id', 'id')
            ->whereHas('organizationRole', function ($query) {
                $query->where('name', 'Owner')
                      ->whereNull('organization_id');
            });
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class, 'id', 'organization_id');
    }

    public function parentOrganization()
    {
        return $this->belongsTo(self::class, 'parent_organization_id');
    }

    public function branches()
    {
        return $this->hasMany(self::class, 'parent_organization_id');
    }

    public function employees()
    {
        return $this->hasMany(OrganizationEmployee::class, 'main_organization_id');
    }

    private function applyOperationalSubscriptionFilter($query, string $subscriptionStatus): void
    {
        if ($subscriptionStatus === 'expired') {
            $query->where('valid_until', '<=', now());

            return;
        }

        $query->where('status', $subscriptionStatus)
            ->where('valid_until', '>', now());
    }
}
