<?php
namespace App\State\Processor\Inventory\Delivery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Inventory\Delivery;
use App\Entity\Inventory\DeliveryItem;
use App\Entity\Security\User;
use App\Repository\Inventory\DeliveryRepository;
use App\Repository\Product\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class CreateDeliveryItemProcessor implements ProcessorInterface
{

    public function __construct(private readonly ProcessorInterface $processor,
                                private readonly TokenStorageInterface $tokenStorage,
                                private readonly Request $request,
                                private readonly EntityManagerInterface $manager,
                                private readonly ItemRepository $itemRepository,
                                private readonly DeliveryRepository $deliveryRepository) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if(!$data instanceof Delivery)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'This delivery is not found.'], 404);
        }

        $delivery = $this->deliveryRepository->find($data->getId());
        if(!$delivery)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'This delivery is not found.'], 404);
        }

        $deliveryData = json_decode($this->request->getContent(), true);

        if (!is_numeric($deliveryData['quantity'])){
            return new JsonResponse(['hydra:description' => 'Quantity must be numeric value!'], 500);
        }

        if ($deliveryData['quantity'] <= 0){
            return new JsonResponse(['hydra:description' => 'Quantity must be upper than 0!'], 500);
        }

        $item = $this->itemRepository->find($this->getIdFromApiResourceId($deliveryData['item']));
        if (!$item){
            return new JsonResponse(['hydra:description' => 'Item not found!'], 500);
        }

        $deliveryItem = new DeliveryItem();

        $deliveryItem->setDelivery($delivery);
        $deliveryItem->setItem($item);
        $deliveryItem->setQuantity($deliveryData['quantity']);

        $deliveryItem->setUser($this->getUser());
        $deliveryItem->setInstitution($this->getUser()->getInstitution());
        $deliveryItem->setYear($this->getUser()->getCurrentYear());

        $this->manager->persist($deliveryItem);
        $this->manager->flush();

        return $this->processor->process($deliveryItem, $operation, $uriVariables, $context);
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
