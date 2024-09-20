<?php

namespace App\Controller\Billing\Purchase;

use App\Entity\Billing\Sale\SaleReturnInvoice;
use App\Entity\Security\User;
use App\Repository\Billing\Sale\SaleReturnInvoiceItemRepository;
use App\Repository\Billing\Sale\SaleReturnInvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class ValidatePurchaseReturnInvoiceController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage,
    )
    {
    }

    public function __invoke(SaleReturnInvoiceItemRepository $saleReturnInvoiceItemRepository,
                             SaleReturnInvoiceRepository $saleReturnInvoiceRepository,
                             EntityManagerInterface $entityManager,
                             Request $request): JsonResponse
    {

        $id = $request->get('id');

        $saleReturnInvoice = $saleReturnInvoiceRepository->find($id);

        if(!$saleReturnInvoice)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'This return invoice is not found.'], 404);
        }

        if(!$saleReturnInvoice instanceof SaleReturnInvoice)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'This data must be type of return invoice.'], 404);
        }


        $saleReturnInvoiceItem = $saleReturnInvoiceItemRepository->findOneBy(['saleReturnInvoice' => $saleReturnInvoice]);

        if(!$saleReturnInvoiceItem)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'Cannot proceed : the cart is empty.'], 404);
        }

        $saleReturnInvoice->setStatus('invoice');


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
