<?php

namespace App\Controller\Billing\Purchase;

use App\Entity\Billing\Sale\SaleInvoiceItem;
use App\Entity\Security\User;
use App\Repository\Billing\Sale\SaleInvoiceItemRepository;
use App\Repository\Billing\Sale\SaleInvoiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class GetPurchaseReturnInvoiceFeeController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage,
                                )
    {
    }

    public function __invoke(SaleInvoiceItemRepository $saleInvoiceItemRepository,
                             SaleInvoiceRepository $saleInvoiceRepository,
                             Request $request): JsonResponse
    {

        $id = $request->get('id');
        $invoice = $saleInvoiceRepository->find($id);
        if (!$invoice){

            return new JsonResponse(['hydra:description' => 'This invoice is not found.'], 404);
        }

        $saleInvoiceItems = $saleInvoiceItemRepository->findBy(['saleInvoice' => $invoice]);

        $items = [];

        foreach ($saleInvoiceItems as $saleInvoiceItem){
            $items[] = [
                'id' => $saleInvoiceItem->getId(),
                'saleInvoice' => [
                    'id' => $saleInvoiceItem->getSaleInvoice() ? $saleInvoiceItem->getSaleInvoice()->getId() : '',
                    '@id' => '/api/get/sale-invoice/'. $saleInvoiceItem->getSaleInvoice()->getId(),
                    'invoiceNumber' => $saleInvoiceItem->getSaleInvoice() ? $saleInvoiceItem->getSaleInvoice()->getInvoiceNumber() : '',
                ],
                'name' => $saleInvoiceItem->getName(),
                'quantity' => $saleInvoiceItem->getQuantity(),
                'pu' => $saleInvoiceItem->getPu()

            ];
        }


        return $this->json(['hydra:member' => $items]);
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

    public function taxes(SaleInvoiceItem $saleInvoiceItem){
        $taxes = [];

        foreach ($saleInvoiceItem->getTaxes() as $tax){
            $taxes[] = [
                'id' => $tax->getId(),
                'name' => $tax->getName(),
                'rate' => $tax->getRate(),
                'label' => $tax->getLabel(),
            ];
        }
        return $taxes;
    }
}
