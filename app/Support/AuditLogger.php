<?php

namespace App\Support;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AuditLogger
{
    public static function record(?User $user, string $action, string $description, ?Model $subject = null, ?Carbon $at = null): void
    {
        $request = app()->bound('request') ? request() : null;
        $agent = $request ? mb_substr((string) $request->userAgent(), 0, 1000) : null;

        $audit = new Audit;
        $audit->user_id = $user?->id;
        $audit->company_id = CompanyContext::id();
        $audit->action = $action;
        $audit->description = $description;
        $audit->http_method = $request ? mb_substr($request->method(), 0, 12) : null;
        $audit->path = $request ? mb_substr($request->path(), 0, 255) : null;
        $audit->ip_address = $request?->ip();
        $audit->user_agent = $agent !== '' ? $agent : null;
        $audit->browser = self::browser($agent);

        if ($subject) {
            $audit->subject_type = $subject->getMorphClass();
            $audit->subject_id = $subject->getKey();
        }

        if ($at) {
            $audit->created_at = $at;
            $audit->updated_at = $at;
        }

        $audit->save();
    }

    public static function browser(?string $agent): ?string
    {
        if (! $agent) {
            return null;
        }

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Autre navigateur',
        };

        $system = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return $system ? $browser.' · '.$system : $browser;
    }
}
