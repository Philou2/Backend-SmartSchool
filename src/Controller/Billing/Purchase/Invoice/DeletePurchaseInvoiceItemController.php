<?php

namespace App\Controller\Billing\Purchase\Invoice;

use App\Entity\Security\User;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemStockRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class DeletePurchaseInvoiceItemController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage,
    )
    {
    }

    public function __invoke(PurchaseInvoiceItemRepository $purchaseInvoiceItemRepository,
                             PurchaseInvoiceItemStockRepository $purchaseInvoiceItemStockRepository,
                             PurchaseInvoiceRepository $purchaseInvoiceRepository,
                             EntityManagerInterface $entityManager,
                             Request $request): JsonResponse
    {

        $id = $request->get('id');

        $purchaseInvoiceItem = $purchaseInvoiceItemRepository->findOneBy(['id' => $id]);
        if (!$purchaseInvoiceItem){
            return new JsonResponse(['hydra:description' => 'This invoice item '.$id.' is not found.'], 404);
        }

        $purchaseInvoiceItemStocks = $purchaseInvoiceItemStockRepository->findBy(['purchaseInvoiceItem' => $purchaseInvoiceItem]);
        if ($purchaseInvoiceItemStocks){
            foreach ($purchaseInvoiceItemStocks as $purchaseInvoiceItemStock){
                $entityManager->remove($purchaseInvoiceItemStock);
            }
        }
        $entityManager->remove($purchaseInvoiceItem);


        $entityManager->flush();

        return $this->json(['hydra:member' => 200]);
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
