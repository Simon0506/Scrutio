<?php

namespace App\Entity;

use App\Entity\ContentPack;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pack_access_grant', options: ['engine' => 'InnoDB'])]
#[ORM\UniqueConstraint(name: 'uniq_pack_access_user_pack', columns: ['user_id', 'pack_id'])]
class PackAccessGrant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'packAccessGrants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(inversedBy: 'accessGrants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ContentPack $pack;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $grantedAt;

    public function __construct(User $user, ContentPack $pack)
    {
        $this->user = $user;
        $this->pack = $pack;
        $this->grantedAt = new \DateTimeImmutable();
        $user->addPackAccessGrant($this);
        $pack->addAccessGrant($this);
    }

    public function getPack(): ContentPack
    {
        return $this->pack;
    }
}
