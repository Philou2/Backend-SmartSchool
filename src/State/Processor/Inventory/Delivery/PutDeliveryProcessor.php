<?php
namespace App\State\Processor\Inventory\Delivery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Inventory\Delivery;
use App\Entity\Security\User;
use App\Repository\Inventory\DeliveryItemRepository;
use App\Repository\Inventory\DeliveryRepository;
use App\Repository\Partner\ContactRepository;
use App\Repository\Security\Institution\BranchRepository;
use App\Repository\Security\SystemSettingsRepository;
use App\Repository\Setting\Inventory\OperationTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PutDeliveryProcessor implements ProcessorInterface
{

    public function __construct(private readonly ProcessorInterface $processor,
                                private readonly TokenStorageInterface $tokenStorage,
                                private readonly Request $request,
                                private readonly EntityManagerInterface $manager,
                                private readonly ContactRepository $contactRepository,
                                private readonly DeliveryItemRepository $deliveryItemRepository,
                                private readonly OperationTypeRepository $operationTypeRepository,
                                private readonly BranchRepository $branchRepository,
                                private readonly SystemSettingsRepository $systemSettingsRepository,
                                Private readonly DeliveryRepository $deliveryRepository) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if(!$data instanceof Delivery)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'This data must be type of delivery.'], 404);
        }

        $delivery = $this->deliveryRepository->find($data->getId());
        if(!$delivery)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'This delivery is not found.'], 404);
        }

        $deliveryData = json_decode($this->request->getContent(), true);

        $branch = !isset($deliveryData['branch']) ? null : $this->branchRepository->find($this->getIdFromApiResourceId($deliveryData['branch']));

        $systemSettings = $this->systemSettingsRepository->findOneBy([]);

        $delivery->setDeliveryAt(new \DateTimeImmutable());
        if (isset($deliveryData['reference'])){
            $delivery->setReference($deliveryData['reference']);
        }

        if (isset($deliveryData['contact'])){
            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $deliveryData['contact']);
            $filterId = intval($filter);
            $contact = $this->contactRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $delivery->setContact($contact);
        }

        if (isset($deliveryData['operationType'])){
            // START: Filter the uri to just take the id and pass it to our object
            $filter = preg_replace("/[^0-9]/", '', $deliveryData['operationType']);
            $filterId = intval($filter);
            $operationType = $this->operationTypeRepository->find($filterId);
            // END: Filter the uri to just take the id and pass it to our object

            $delivery->setOperationType($operationType);
        }

        $deliveryItems = $this->deliveryItemRepository->findBy(['delivery' => $delivery]);

        foreach ($deliveryItems as $deliveryItem){
            $deliveryItem->setYear($delivery->getYear());
        }

        $delivery->setOriginalDocument($deliveryData['OriginalDocument']);
        $delivery->setDescription($deliveryData['description']);
        $delivery->setStatus($deliveryData['status']);
        if($systemSettings) {
            if ($systemSettings->isIsBranches()) {
                $delivery->setBranch($branch);
            } else {
                $delivery->setBranch($this->getUser()->getBranch());
            }
        }

        $this->manager->flush();

        return $this->processor->process($delivery, $operation, $uriVariables, $context);
    }


    public function getIdFromApiResourceId(string $apiId){
        $lastIndexOf = strrpos($apiId, '/');
        $id = substr($apiId, $lastIndexOf+1);
        return intval($id);
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
