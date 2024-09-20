<?php

namespace App\Controller\Billing\Purchase;

use App\Entity\Security\User;
use App\Repository\Billing\Purchase\PurchaseInvoiceItemRepository;
use App\Repository\Billing\Purchase\PurchaseInvoiceRepository;
use App\Repository\Billing\Purchase\PurchaseSettlementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class DeletePurchaseSettlementController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage,
    )
    {
    }

    public function __invoke(PurchaseInvoiceItemRepository $purchaseInvoiceItemRepository,
                             PurchaseInvoiceRepository $purchaseInvoiceRepository,
                             PurchaseSettlementRepository $purchaseSettlementRepository,
                             EntityManagerInterface $entityManager,
                             Request $request): JsonResponse
    {

        $id = $request->get('id');
        $settlement = $purchaseSettlementRepository->find($id);
        if (!$settlement){
            return new JsonResponse(['hydra:description' => 'This settlement is not found.'], 404);
        }

        if ($settlement->getInvoice()){
            $settlement->getInvoice()?->setBalance($settlement->getInvoice()->getBalance() + $settlement->getAmountPay());
            $settlement->getInvoice()?->setAmountPaid($settlement->getInvoice()->getAmountPaid() - $settlement->getAmountPay());
            $settlement->getInvoice()?->setVirtualBalance($settlement->getInvoice()->getVirtualBalance() + $settlement->getAmountPay());
        }

        if ($settlement->getPurchaseReturnInvoice()){
            $settlement->getPurchaseReturnInvoice()?->setBalance($settlement->getPurchaseReturnInvoice()->getBalance() + $settlement->getAmountPay());
            $settlement->getPurchaseReturnInvoice()?->setAmountPaid($settlement->getPurchaseReturnInvoice()->getAmountPaid() - $settlement->getAmountPay());
            $settlement->getPurchaseReturnInvoice()?->setVirtualBalance($settlement->getPurchaseReturnInvoice()->getVirtualBalance() + $settlement->getAmountPay());
        }

        $entityManager->remove($settlement);
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
