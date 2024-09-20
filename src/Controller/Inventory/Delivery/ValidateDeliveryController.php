<?php

namespace App\Controller\Inventory\Delivery;

use App\Entity\Inventory\Delivery;
use App\Entity\Security\User;
use App\Repository\Inventory\DeliveryItemRepository;
use App\Repository\Inventory\DeliveryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class ValidateDeliveryController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage,
    )
    {
    }

    public function __invoke(DeliveryItemRepository $deliveryItemRepository,
                             DeliveryRepository $deliveryRepository,
                             EntityManagerInterface $entityManager,
                             Request $request): JsonResponse
    {

        $id = $request->get('id');

        $delivery = $deliveryRepository->find($id);

        if(!$delivery instanceof Delivery)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'This data must be type of delivery.'], 404);
        }

        if(!$delivery)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'This delivery is not found.'], 404);
        }

        $deliveryItem = $deliveryItemRepository->findOneBy(['delivery' => $delivery]);

        if(!$deliveryItem)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'Cannot proceed : the cart is empty.'], 404);
        }

        $delivery->setStatus('delivery');


        $entityManager->flush();

        return $this->json(['hydra:member' => '200']);
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
