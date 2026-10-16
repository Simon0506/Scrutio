<?php

namespace App\Tests\Service;

use App\Entity\CaseFile;
use App\Entity\ContentPack;
use App\Entity\User;
use App\Service\CaseAccessService;
use PHPUnit\Framework\TestCase;

final class CaseAccessServiceTest extends TestCase
{
    public function testProgressionRequiresEveryEarlierExpertiseToPass(): void
    {
        $first = new CaseFile('Première', 'first', 'Test.', 'Simple', 1);
        $second = new CaseFile('Deuxième', 'second', 'Test.', 'Intermédiaire', 2);
        $third = new CaseFile('Troisième', 'third', 'Test.', 'Complexe', 3);
        $cases = [$third, $first, $second];
        $access = new CaseAccessService();

        self::assertTrue($access->isUnlocked($first, $cases, []));
        self::assertFalse($access->isUnlocked($second, $cases, []));
        self::assertFalse($access->isUnlocked($second, $cases, ['first' => ['passed' => false]]));
        self::assertTrue($access->isUnlocked($second, $cases, ['first' => ['passed' => true]]));
        self::assertFalse($access->isUnlocked($third, $cases, ['first' => ['passed' => true]]));
        self::assertFalse($access->isUnlocked($third, $cases, ['second' => ['passed' => true]]));
        self::assertTrue($access->isUnlocked($third, $cases, [
            'first' => ['passed' => true],
            'second' => ['passed' => true],
        ]));
    }

    public function testEachPackHasIndependentProgression(): void
    {
        $pack = new ContentPack('Archives', 'archives', 'Test.', 499);
        $otherPack = new ContentPack('Art', 'art', 'Test.', 499);
        $free = new CaseFile('Libre', 'free', 'Test.', 'Simple', 1);
        $first = new CaseFile('Première', 'first', 'Test.', 'Simple', 2, $pack);
        $second = new CaseFile('Deuxième', 'second', 'Test.', 'Complexe', 3, $pack);
        $other = new CaseFile('Autre', 'other', 'Test.', 'Simple', 1, $otherPack);
        $cases = [$free, $first, $second, $other];
        $access = new CaseAccessService();

        self::assertTrue($access->isUnlocked($first, $cases, []));
        self::assertFalse($access->isUnlocked($second, $cases, []));
        self::assertTrue($access->isUnlocked($second, $cases, ['first' => ['passed' => true]]));
    }

    public function testFreeCaseCanBeAccessedAnonymously(): void
    {
        $case = new CaseFile('Affaire libre', 'affaire-libre', 'Test.', 'Débutant');

        self::assertTrue((new CaseAccessService())->canAccess(null, $case));
    }

    public function testNextCaseFollowsEditorialOrderWithinTheSameSeries(): void
    {
        $pack = new ContentPack('Atelier', 'atelier', 'Test.', 499);
        $first = new CaseFile('Première', 'first', 'Test.', 'Simple', 1);
        $second = new CaseFile('Deuxième', 'second', 'Test.', 'Intermédiaire', 3);
        $third = new CaseFile('Troisième', 'third', 'Test.', 'Complexe', 5);
        $paidFirst = new CaseFile('Atelier 1', 'atelier-first', 'Test.', 'Simple', 2, $pack);
        $paidLast = new CaseFile('Atelier 2', 'atelier-last', 'Test.', 'Complexe', 4, $pack);
        $cases = [$third, $paidLast, $second, $paidFirst, $first];
        $access = new CaseAccessService();

        self::assertSame($second, $access->nextCase($first, $cases));
        self::assertSame($third, $access->nextCase($second, $cases));
        self::assertSame($paidLast, $access->nextCase($paidFirst, $cases));
        self::assertNull($access->nextCase($third, $cases));
        self::assertNull($access->nextCase($paidLast, $cases));
        self::assertNull($access->nextCase($first, []));
    }

    public function testCaseInPackRequiresPackAccess(): void
    {
        $pack = new ContentPack('Pack Valabrègue', 'pack-valabregue', 'Contenu additionnel.', 499);
        $case = new CaseFile('Affaire privée', 'affaire-privee', 'Test.', 'Débutant', pack: $pack);
        $secondCase = new CaseFile('Seconde affaire', 'seconde-affaire', 'Test.', 'Intermédiaire', pack: $pack);
        $user = new User('analyste@example.test');
        $access = new CaseAccessService();

        self::assertFalse($access->canAccess(null, $case));
        self::assertFalse($access->canAccess($user, $case));

        $user->grantPackAccess($pack);

        self::assertTrue($access->canAccess($user, $case));
        self::assertTrue($access->canAccess($user, $secondCase));
    }

    public function testGrantForAnotherPackDoesNotUnlockCase(): void
    {
        $pack = new ContentPack('Pack Valabrègue', 'pack-valabregue-2', 'Contenu additionnel.', 499);
        $otherPack = new ContentPack('Pack Dumas', 'pack-dumas', 'Autre contenu additionnel.', 499);
        $case = new CaseFile('Affaire privée', 'affaire-privee-3', 'Test.', 'Débutant', pack: $pack);
        $user = new User('analyste@example.test');
        $user->grantPackAccess($otherPack);

        self::assertFalse((new CaseAccessService())->canAccess($user, $case));
    }
}
