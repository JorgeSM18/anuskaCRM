<?php

namespace App\Entity;

use App\Enum\SupplierStatus;
use App\Repository\SupplierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SupplierRepository::class)]
#[ORM\Index(fields: ['brandName'])]
#[ORM\Index(fields: ['status'])]
class Supplier implements Auditable
{
    use AuditableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'El nombre de la marca es obligatorio.')]
    private string $brandName;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $legalName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $taxId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: 'El email no es válido.')]
    private ?string $email = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(enumType: SupplierStatus::class)]
    private SupplierStatus $status = SupplierStatus::ACTIVE;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Contact $primaryContact = null;

    /** @var Collection<int, Contact> */
    #[ORM\OneToMany(targetEntity: Contact::class, mappedBy: 'supplier', cascade: ['persist'])]
    #[ORM\OrderBy(['firstName' => 'ASC'])]
    private Collection $contacts;

    /** @var Collection<int, PurchaseOrder> */
    #[ORM\OneToMany(targetEntity: PurchaseOrder::class, mappedBy: 'supplier')]
    #[ORM\OrderBy(['orderedAt' => 'DESC'])]
    private Collection $purchaseOrders;

    /** @var Collection<int, Invoice> */
    #[ORM\OneToMany(targetEntity: Invoice::class, mappedBy: 'supplier')]
    #[ORM\OrderBy(['issuedAt' => 'DESC'])]
    private Collection $invoices;

    /** @var Collection<int, Communication> */
    #[ORM\OneToMany(targetEntity: Communication::class, mappedBy: 'supplier')]
    #[ORM\OrderBy(['occurredAt' => 'DESC'])]
    private Collection $communications;

    /** @var Collection<int, Appointment> */
    #[ORM\OneToMany(targetEntity: Appointment::class, mappedBy: 'supplier')]
    #[ORM\OrderBy(['startsAt' => 'DESC'])]
    private Collection $appointments;

    /** @var Collection<int, Document> */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'supplier')]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $documents;

    /** @var Collection<int, Category> */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'suppliers')]
    #[ORM\JoinTable(name: 'supplier_category')]
    #[ORM\JoinColumn(name: 'supplier_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'category_id', onDelete: 'CASCADE')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $categories;

    public function __construct()
    {
        $this->contacts = new ArrayCollection();
        $this->purchaseOrders = new ArrayCollection();
        $this->invoices = new ArrayCollection();
        $this->communications = new ArrayCollection();
        $this->appointments = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->categories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBrandName(): string
    {
        return $this->brandName;
    }

    public function setBrandName(string $brandName): static
    {
        $this->brandName = $brandName;

        return $this;
    }

    public function getLegalName(): ?string
    {
        return $this->legalName;
    }

    public function setLegalName(?string $legalName): static
    {
        $this->legalName = $legalName;

        return $this;
    }

    public function getTaxId(): ?string
    {
        return $this->taxId;
    }

    public function setTaxId(?string $taxId): static
    {
        $this->taxId = $taxId;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getStatus(): SupplierStatus
    {
        return $this->status;
    }

    public function setStatus(SupplierStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isActive(): bool
    {
        return SupplierStatus::ACTIVE === $this->status;
    }

    public function getPrimaryContact(): ?Contact
    {
        return $this->primaryContact;
    }

    public function setPrimaryContact(?Contact $primaryContact): static
    {
        $this->primaryContact = $primaryContact;

        return $this;
    }

    /**
     * @return Collection<int, Contact>
     */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(Contact $contact): static
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
            $contact->setSupplier($this);
        }

        return $this;
    }

    public function removeContact(Contact $contact): static
    {
        $this->contacts->removeElement($contact);

        return $this;
    }

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function getPurchaseOrders(): Collection
    {
        return $this->purchaseOrders;
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }

    /**
     * @return Collection<int, Communication>
     */
    public function getCommunications(): Collection
    {
        return $this->communications;
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function getAppointments(): Collection
    {
        return $this->appointments;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }
}
