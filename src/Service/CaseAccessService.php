<?php

namespace App\Service;

use App\Entity\CaseFile;
use App\Entity\User;

final class CaseAccessService
{
    public function canAccess(?User $user, CaseFile $case): bool
    {
        $pack = $case->getPack();

        return $pack === null || ($user?->hasPackAccess($pack) ?? false);
    }

    /** @param iterable<CaseFile> $cases */
    public function nextCase(CaseFile $case, iterable $cases): ?CaseFile
    {
        $next = null;
        foreach ($cases as $candidate) {
            if (
                $candidate->getPack()?->getSlug() === $case->getPack()?->getSlug()
                && $candidate->getDisplayOrder() > $case->getDisplayOrder()
                && ($next === null || $candidate->getDisplayOrder() < $next->getDisplayOrder())
            ) {
                $next = $candidate;
            }
        }

        return $next;
    }

    /** @param iterable<CaseFile> $cases */
    public function isUnlocked(CaseFile $case, iterable $cases, array $summaries): bool
    {
        foreach ($cases as $previous) {
            if (
                $previous->getPack()?->getSlug() === $case->getPack()?->getSlug()
                && $previous->getDisplayOrder() < $case->getDisplayOrder()
                && !($summaries[$previous->getSlug()]['passed'] ?? false)
            ) {
                return false;
            }
        }

        return true;
    }
}
