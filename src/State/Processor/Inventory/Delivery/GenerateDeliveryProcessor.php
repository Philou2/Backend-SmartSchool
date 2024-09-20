<?php
namespace App\State\Processor\Inventory\Delivery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Inventory\Delivery;
use App\Entity\Security\User;
use App\Repository\Inventory\DeliveryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class GenerateDeliveryProcessor implements ProcessorInterface
{

    public function __construct(private readonly ProcessorInterface $processor,
                                private readonly TokenStorageInterface $tokenStorage,
                                private readonly DeliveryRepository $deliveryRepository,
                                private readonly EntityManagerInterface $manager) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {

        $delivery = new Delivery();

        // sale invoice : null
        // contact : null
        // shipping address
        // operation type : null
        // original document

        $deliveries = $this->deliveryRepository->findOneBy([], ['id' => 'DESC']);

        if (!$deliveries){
            $reference = 'WH/OUT/' . str_pad( 1, 5, '0', STR_PAD_LEFT);
        }
        else{
            $filterNumber = preg_replace("/[^0-9]/", '', $deliveries->getReference());
            $number = intval($filterNumber);

            // Utilisation de number_format() pour ajouter des zéros à gauche
            $reference = 'WH/OUT/' . str_pad($number + 1, 5, '0', STR_PAD_LEFT);
        }

        $delivery->setReference($reference);
        // other reference
        // serial number
        $delivery->setDeliveryAt(new \DateTimeImmutable());
        // picture : delivery note
        // description
        // validation section empty here
        $delivery->setStatus('draft');

        $delivery->setBranch($this->getUser()->getBranch());
        $delivery->setInstitution($this->getUser()->getInstitution());
        $delivery->setYear($this->getUser()->getCurrentYear());
        $delivery->setUser($this->getUser());

        $this->manager->persist($delivery);
        $this->manager->flush();

        return $delivery;
    }


    public function getUser(): ?User
    {
        $token = $this->tokenStorage->getToken();

        if (!$token) {
            return null;
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return null;
        }

        return $user;
    }

}
