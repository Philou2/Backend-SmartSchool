<?php

namespace App\Controller\Billing\Purchase;

use App\Entity\Billing\Sale\SaleReturnInvoiceItem;
use App\Entity\Security\User;
use App\Repository\Billing\Sale\SaleReturnInvoiceItemRepository;
use App\Repository\Billing\Sale\SaleReturnInvoiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class GetPurchaseReturnInvoiceItemController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage,
                                )
    {
    }

    public function __invoke(SaleReturnInvoiceItemRepository $saleReturnInvoiceItemRepository,
                             SaleReturnInvoiceRepository $saleReturnInvoiceRepository,
                             Request $request): JsonResponse
    {

        $id = $request->get('id');
        $returnInvoice = $saleReturnInvoiceRepository->find($id);
        if (!$returnInvoice){

            return new JsonResponse(['hydra:description' => 'This invoice is not found.'], 404);
        }

        $saleReturnInvoiceItems = $saleReturnInvoiceItemRepository->findBy(['saleReturnInvoice' => $returnInvoice]);

        $items = [];

        foreach ($saleReturnInvoiceItems as $saleReturnInvoiceItem){
            $items[] = [
                'id' => $saleReturnInvoiceItem->getId(),
                'saleInvoice' => [
                    'id' => $saleReturnInvoiceItem->getSaleReturnInvoice() ? $saleReturnInvoiceItem->getSaleReturnInvoice()->getId() : '',
                    '@id' => '/api/get/sale-invoice/'. $saleReturnInvoiceItem->getSaleReturnInvoice()->getId(),
                    'invoiceNumber' => $saleReturnInvoiceItem->getSaleReturnInvoice() ? $saleReturnInvoiceItem->getSaleReturnInvoice()->getInvoiceNumber() : '',
                ],
                'item' => [
                    'id' => $saleReturnInvoiceItem->getItem()->getId(),
                    '@id' => '/api/get/items/'. $saleReturnInvoiceItem->getItem()->getId(),
                    'name' => $saleReturnInvoiceItem->getItem() ? $saleReturnInvoiceItem->getItem()->getName() : '',
                    'reference' => $saleReturnInvoiceItem->getItem() ? $saleReturnInvoiceItem->getItem()->getReference() : '',
                    'barcode' => $saleReturnInvoiceItem->getItem() ? $saleReturnInvoiceItem->getItem()->getBarcode() : '',
                    'price' => $saleReturnInvoiceItem->getItem() ? $saleReturnInvoiceItem->getItem()->getPrice() : '',
                    'salePrice' => $saleReturnInvoiceItem->getItem() ? $saleReturnInvoiceItem->getItem()->getSalePrice() : '',
                    'cost' => $saleReturnInvoiceItem->getItem() ? $saleReturnInvoiceItem->getItem()->getCost() : '',
                ],
                'name' => $saleReturnInvoiceItem->getName(),
                'quantity' => $saleReturnInvoiceItem->getQuantity(),
                'amount' => $saleReturnInvoiceItem->getAmount(),
                'pu' => $saleReturnInvoiceItem->getPu(),
                'discount' => $saleReturnInvoiceItem->getDiscount(),
                'discountAmount' => $saleReturnInvoiceItem->getDiscountAmount(),
                'amountTtc' => $saleReturnInvoiceItem->getAmountTtc(),
                'amountWithTaxes' => $saleReturnInvoiceItem->getAmountWithTaxes(),
                'returnQuantity' => $saleReturnInvoiceItem->getReturnQuantity(),

                'taxes' => $this->taxes($saleReturnInvoiceItem),

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

    public function taxes(SaleReturnInvoiceItem $saleReturnInvoiceItem){
        $taxes = [];

        foreach ($saleReturnInvoiceItem->getTaxes() as $tax){
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
