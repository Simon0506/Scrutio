<?php

namespace App\Entity;

use App\Entity\CaseFile;
use App\Entity\PackAccessGrant;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'content_pack', options: ['engine' => 'InnoDB'])]
class ContentPack
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $title;

    #[ORM\Column(length: 140, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column]
    private int $displayOrder;

    #[ORM\Column]
    private int $priceAmountInCents;

    #[ORM\Column(length: 3)]
    private string $currency;

    /** @var Collection<int, CaseFile> */
    #[ORM\OneToMany(mappedBy: 'pack', targetEntity: CaseFile::class)]
    private Collection $cases;

    /** @var Collection<int, PackAccessGrant> */
    #[ORM\OneToMany(mappedBy: 'pack', targetEntity: PackAccessGrant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $accessGrants;

    public function __construct(
        string $title,
        string $slug,
        string $description,
        int $priceAmountInCents,
        int $displayOrder = 1,
        string $currency = 'EUR',
    ) {
        if ($priceAmountInCents < 1 || !preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \InvalidArgumentException('A pack requires a positive price and a 3-letter currency code.');
        }

        $this->title = $title;
        $this->slug = $slug;
        $this->description = $description;
        $this->priceAmountInCents = $priceAmountInCents;
        $this->currency = $currency;
        $this->displayOrder = $displayOrder;
        $this->cases = new ArrayCollection();
        $this->accessGrants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function getPriceAmountInCents(): int
    {
        return $this->priceAmountInCents;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /** @return Collection<int, CaseFile> */
    public function getCases(): Collection
    {
        return $this->cases;
    }

    public function addCase(CaseFile $case): void
    {
        if (!$this->cases->contains($case)) {
            $this->cases->add($case);
            $case->setPack($this);
        }
    }

    public function removeCase(CaseFile $case): void
    {
        if ($this->cases->removeElement($case) && $case->getPack() === $this) {
            $case->setPack(null);
        }
    }

    public function addAccessGrant(PackAccessGrant $grant): void
    {
        if (!$this->accessGrants->contains($grant)) {
            $this->accessGrants->add($grant);
        }
    }
}
