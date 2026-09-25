<?php

namespace App\DataFixtures;

use App\Entity\Appointment;
use App\Entity\Category;
use App\Entity\Communication;
use App\Entity\Contact;
use App\Entity\Fair;
use App\Entity\FairParticipation;
use App\Entity\Invoice;
use App\Entity\PurchaseOrder;
use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\AppointmentStatus;
use App\Enum\AppointmentType;
use App\Enum\CommunicationType;
use App\Enum\ContactRole;
use App\Enum\FairStatus;
use App\Enum\InvoiceStatus;
use App\Enum\OrderStatus;
use App\Enum\SupplierStatus;
use App\Service\InvoiceCalculator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly InvoiceCalculator $calc,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        mt_srand(7); // datos reproducibles

        $this->createUser($manager, 'admin@anuska.local', 'Administrador Anuska', ['ROLE_ADMIN']);
        $this->createUser($manager, 'tienda@anuska.local', 'Tienda Anuska', ['ROLE_USER']);

        // Categorías de producto
        $categories = [];
        foreach (['Punto', 'Baño', 'Complementos', 'Camisería', 'Calcetería', 'Bolsos'] as $name) {
            $category = (new Category())->setName($name);
            $manager->persist($category);
            $categories[] = $category;
        }

        $supplierList = [];
        foreach ($this->brands() as $i => $b) {
            $supplier = (new Supplier())
                ->setBrandName($b['brand'])
                ->setLegalName($b['legal'])
                ->setTaxId($b['cif'])
                ->setAddress('Calle Ejemplo 1, '.$b['city'])
                ->setPhone($b['phone'])
                ->setEmail($b['email'])
                ->setWebsite('https://www.'.$b['slug'].'.example')
                ->setStatus($b['active'] ? SupplierStatus::ACTIVE : SupplierStatus::INACTIVE);
            // Asigna 1-2 categorías rotando
            $supplier->addCategory($categories[$i % \count($categories)]);
            $supplier->addCategory($categories[($i + 2) % \count($categories)]);
            $manager->persist($supplier);
            $supplierList[] = $supplier;

            $this->addContacts($manager, $supplier, $b);
            if ($b['active']) {
                $this->addOrders($manager, $supplier, $i);
                $this->addInvoices($manager, $supplier, $i);
                $this->addCommunications($manager, $supplier, $b);
                $this->addAppointments($manager, $supplier, $i);
            }
        }

        $this->addFairs($manager, $supplierList);

        $manager->flush();
    }

    /**
     * @param list<Supplier> $suppliers
     */
    private function addFairs(ObjectManager $manager, array $suppliers): void
    {
        $fairs = [
            ['Feria Fashion Madrid', 'Madrid', 'IFEMA', '+30 days', FairStatus::CONFIRMED],
            ['Salón de la Moda Barcelona', 'Barcelona', 'Fira de Barcelona', '-60 days', FairStatus::DONE],
            ['Complementos & Accesorios', 'Valencia', 'Feria Valencia', '+90 days', FairStatus::PLANNED],
        ];

        foreach ($fairs as $k => [$name, $city, $location, $when, $status]) {
            $start = new \DateTimeImmutable($when);
            $fair = (new Fair())
                ->setName($name)
                ->setCity($city)
                ->setLocation($location)
                ->setStartsAt($start)
                ->setEndsAt($start->modify('+2 days'))
                ->setEdition('2027')
                ->setStatus($status);
            $manager->persist($fair);

            // 3 proveedores por feria, rotando
            for ($j = 0; $j < 3; ++$j) {
                $supplier = $suppliers[($k * 3 + $j) % \count($suppliers)];
                $participation = (new FairParticipation())
                    ->setFair($fair)
                    ->setSupplier($supplier)
                    ->setFollowUp(0 === $j);
                if (FairStatus::DONE === $status) {
                    $participation->setMeetingNotes('Reunión con '.$supplier->getBrandName().': colección vista, precios revisados.');
                    $participation->setNextAction(0 === $j ? 'Pedir muestrario' : null);
                }
                $manager->persist($participation);
            }
        }
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(ObjectManager $manager, string $email, string $name, array $roles): void
    {
        $user = (new User())->setEmail($email)->setFullName($name)->setRoles($roles);
        $user->setPassword($this->hasher->hashPassword($user, 'anuska1234'));
        $manager->persist($user);
    }

    /**
     * @param array{slug: string, people: list<array{0: string, 1: string}>, ...} $b
     */
    private function addContacts(ObjectManager $manager, Supplier $supplier, array $b): void
    {
        $roles = [ContactRole::SALES_REP, ContactRole::ADMIN, ContactRole::CUSTOMER_SERVICE];
        $count = mt_rand(1, 3);
        for ($j = 0; $j < $count; ++$j) {
            [$first, $last] = $b['people'][$j % \count($b['people'])];
            $contact = (new Contact())
                ->setSupplier($supplier)
                ->setFirstName($first)
                ->setLastName($last)
                ->setRole($roles[$j % \count($roles)])
                ->setJobTitle(0 === $j ? 'Comercial de zona' : null)
                ->setPhone('6'.mt_rand(10000000, 99999999))
                ->setEmail(strtolower($first).'@'.$b['slug'].'.example');
            $manager->persist($contact);
            if (0 === $j) {
                $supplier->setPrimaryContact($contact);
            }
        }
    }

    private function addOrders(ObjectManager $manager, Supplier $supplier, int $seed): void
    {
        $seasons = ['PV27', 'OI27', 'PV28'];
        $statuses = [OrderStatus::PLACED, OrderStatus::CONFIRMED, OrderStatus::RECEIVED, OrderStatus::PARTIALLY_RECEIVED, OrderStatus::DRAFT];
        $count = mt_rand(2, 4);
        for ($j = 0; $j < $count; ++$j) {
            $status = $statuses[($seed + $j) % \count($statuses)];
            $order = (new PurchaseOrder())
                ->setSupplier($supplier)
                ->setNumber(\sprintf('PED-2027-%03d', $seed * 10 + $j))
                ->setOrderedAt(new \DateTimeImmutable('-'.mt_rand(5, 200).' days'))
                ->setSeason($seasons[$j % \count($seasons)])
                ->setStatus($status)
                ->setEstimatedAmount((string) (mt_rand(8, 60) * 100).'.00');
            if (OrderStatus::RECEIVED === $status) {
                $order->setReceivedAt(new \DateTimeImmutable('-'.mt_rand(1, 30).' days'));
                $order->setFinalAmount($order->getEstimatedAmount());
            } elseif (0 === $j) {
                // alguna entrega prevista dentro de la próxima semana (para el panel)
                $order->setExpectedDeliveryAt(new \DateTimeImmutable('+'.mt_rand(1, 6).' days'));
            }
            $manager->persist($order);
        }
    }

    private function addInvoices(ObjectManager $manager, Supplier $supplier, int $seed): void
    {
        $count = mt_rand(1, 3);
        for ($j = 0; $j < $count; ++$j) {
            $base = (string) (mt_rand(5, 40) * 100); // numeric-string
            $vat = $this->calc->vatAmount($base, '21.00');
            $invoice = (new Invoice())
                ->setSupplier($supplier)
                ->setNumber(\sprintf('2027-%03d', $seed * 10 + $j))
                ->setIssuedAt(new \DateTimeImmutable('-'.mt_rand(3, 120).' days'))
                ->setBaseAmount($base)
                ->setVatRate('21.00')
                ->setVatAmount($vat)
                ->setTotal($this->calc->total($base, $vat));

            if (0 === $j) {
                // pendiente y vencida (para el panel)
                $invoice->setStatus(InvoiceStatus::PENDING)->setDueAt(new \DateTimeImmutable('-'.mt_rand(1, 15).' days'));
            } elseif (1 === $j) {
                // pendiente por vencer pronto
                $invoice->setStatus(InvoiceStatus::PENDING)->setDueAt(new \DateTimeImmutable('+'.mt_rand(1, 6).' days'));
            } else {
                $invoice->setStatus(InvoiceStatus::PAID)->setPaidAt(new \DateTimeImmutable('-'.mt_rand(1, 40).' days'));
            }
            $manager->persist($invoice);
        }
    }

    /**
     * @param array{brand: string, slug: string, ...} $b
     */
    private function addCommunications(ObjectManager $manager, Supplier $supplier, array $b): void
    {
        $types = [CommunicationType::EMAIL, CommunicationType::PHONE, CommunicationType::WHATSAPP, CommunicationType::MEETING];
        $subjects = ['Confirmación de pedido', 'Consulta de stock', 'Nueva colección disponible', 'Aviso de envío', 'Revisión de factura'];
        $count = mt_rand(1, 3);
        for ($j = 0; $j < $count; ++$j) {
            $comm = (new Communication())
                ->setSupplier($supplier)
                ->setType($types[$j % \count($types)])
                ->setSubject($subjects[($j + \strlen($b['slug'])) % \count($subjects)])
                ->setOccurredAt(new \DateTimeImmutable('-'.mt_rand(1, 60).' days'))
                ->setBody('Notas de la conversación con '.$b['brand'].'.')
                ->setIsImportant(0 === $j)
                ->setPendingReply(0 === $j);
            $manager->persist($comm);
        }
    }

    private function addAppointments(ObjectManager $manager, Supplier $supplier, int $seed): void
    {
        $types = [AppointmentType::SALES_VISIT, AppointmentType::COLLECTION_PREVIEW, AppointmentType::ORDER_MEETING, AppointmentType::CALL];

        // Una futura pendiente
        $future = (new Appointment())
            ->setTitle('Visita comercial '.$supplier->getBrandName())
            ->setSupplier($supplier)
            ->setContact($supplier->getPrimaryContact())
            ->setType($types[$seed % \count($types)])
            ->setStartsAt(new \DateTimeImmutable('+'.mt_rand(1, 20).' days 10:00'))
            ->setDurationMinutes(60)
            ->setLocation('Tienda')
            ->setStatus(AppointmentStatus::PENDING);
        if (0 === $seed % 3) {
            $future->setReminderAt(new \DateTimeImmutable('-1 hour')); // recordatorio ya activo
        }
        $manager->persist($future);

        // Una pasada realizada
        $past = (new Appointment())
            ->setTitle('Presentación colección '.$supplier->getBrandName())
            ->setSupplier($supplier)
            ->setType(AppointmentType::COLLECTION_PREVIEW)
            ->setStartsAt(new \DateTimeImmutable('-'.mt_rand(10, 60).' days 17:00'))
            ->setStatus(AppointmentStatus::DONE);
        $manager->persist($past);
    }

    /**
     * @return list<array{brand: string, slug: string, legal: string, cif: string, city: string, phone: string, email: string, active: bool, people: list<array{0: string, 1: string}>}>
     */
    private function brands(): array
    {
        $people = [
            [['Laura', 'García'], ['Carlos', 'Ruiz'], ['Marta', 'Solé']],
            [['Ana', 'Ferrer'], ['Pablo', 'Mora']],
            [['Nuria', 'Vidal'], ['Sergio', 'León'], ['Elena', 'Cano']],
        ];
        $data = [
            ['Marlota', 'PV Textiles SL', 'B12345678', 'Madrid', true],
            ['Bimba Line', 'Bimba Moda SA', 'A87654321', 'Barcelona', true],
            ['Punto Sur', 'Punto Sur SL', 'B23456789', 'Valencia', true],
            ['Nudo Atelier', 'Nudo Creaciones SL', 'B34567890', 'Sevilla', true],
            ['Baño & Co', 'Aguas Textil SL', 'B45678901', 'Alicante', true],
            ['Calcetería Ávila', 'Ávila Punto SL', 'B56789012', 'Ávila', true],
            ['Lino Blanco', 'Lino Blanco SA', 'A67890123', 'A Coruña', true],
            ['Trenza Complementos', 'Trenza SL', 'B78901234', 'Zaragoza', true],
            ['Aguamarina Bolsos', 'Aguamarina SL', 'B89012345', 'Málaga', false],
            ['Sombrerería Prat', 'Prat 1920 SL', 'B90123456', 'Barcelona', false],
        ];

        $out = [];
        foreach ($data as $k => [$brand, $legal, $cif, $city, $active]) {
            $slug = strtolower(preg_replace('/[^a-z0-9]/i', '', $brand) ?? 'marca');
            $out[] = [
                'brand' => $brand,
                'slug' => $slug,
                'legal' => $legal,
                'cif' => $cif,
                'city' => $city,
                'phone' => '9'.mt_rand(10000000, 99999999),
                'email' => 'info@'.$slug.'.example',
                'active' => $active,
                'people' => $people[$k % \count($people)],
            ];
        }

        return $out;
    }
}
